<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RescheduleInterviewRequest;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobInterview;
use App\Traits\FormatsMoney;
use App\Traits\FormatsTime;
use App\Traits\PresentsCandidate;
use App\Traits\ResolvesClient;
use App\Traits\SendsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClientInterviewController extends Controller
{
    use FormatsMoney;
    use FormatsTime;
    use PresentsCandidate;
    use ResolvesClient;
    use SendsNotifications;

    /**
     * Cancelling or rescheduling must inform the admin at least this many
     * hours before the scheduled interview start.
     */
    private const CHANGE_DEADLINE_HOURS = 4;

    // GET /client/interviews
    // view=list | view=calendar
    public function index(Request $request): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        $jobIds = LongTermJob::where('client_id', $client->id)->pluck('id');

        $requestFilter = $request->query('filter', []);
        $queryBuilderFilters = is_array($requestFilter) ? $requestFilter : [];
        $filter = $request->query('period', is_string($requestFilter) ? $requestFilter : ($queryBuilderFilters['period'] ?? 'all'));
        $view = $request->query('view', 'list');

        unset($queryBuilderFilters['period']);

        if ($request->filled('search') && ! isset($queryBuilderFilters['search'])) {
            $queryBuilderFilters['search'] = $request->query('search');
        }

        $request->merge(['filter' => $queryBuilderFilters]);

        $query = QueryBuilder::for(
            LongTermJobInterview::with(['job', 'candidate', 'application'])
                ->where('agency_id', $client->agency_id)
                ->where(function (Builder $scope) use ($jobIds, $client): void {
                    $scope->whereIn('long_term_job_id', $jobIds)
                        ->orWhere('client_id', $client->id);
                }),
            $request
        )
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = collect(is_array($value) ? $value : [$value])
                        ->map(fn (mixed $term): string => trim((string) $term))
                        ->filter()
                        ->implode(' ');

                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $searchQuery) use ($search): void {
                        $searchQuery->whereHas('job', function (Builder $jobQuery) use ($search): void {
                            $jobQuery->where('title', 'like', "%{$search}%");
                        })->orWhereHas('candidate', function (Builder $candidateQuery) use ($search): void {
                            $candidateQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                    });
                })
            )
            ->orderBy('scheduled_date')
            ->orderBy('available_from');

        if ($filter === 'upcoming') {
            $query->where('scheduled_date', '>=', now()->toDateString())
                ->whereIn('status', ['requested', 'scheduled']);
        } elseif ($filter === 'previous') {
            $query->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhere(function ($past) {
                        $past->where('status', 'scheduled')
                            ->where('scheduled_date', '<', now()->toDateString());
                    });
            });
        } elseif ($filter === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'declined']);
        }

        if ($view === 'calendar') {
            $month = $request->query('month', now()->month);
            $year = $request->query('year', now()->year);

            $events = $query->whereMonth('scheduled_date', $month)
                ->whereYear('scheduled_date', $year)
                ->get()
                ->map(fn (LongTermJobInterview $interview): array => $this->formatCalendarEvent($interview))
                ->values();

            return $this->sendResponse([
                'view' => 'calendar',
                'month' => (int) $month,
                'year' => (int) $year,
                'filters' => [
                    'period' => $filter,
                    'search' => $queryBuilderFilters['search'] ?? null,
                ],
                'events' => $events,
            ], 'Interviews retrieved successfully.', 200);
        }

        $interviews = $query->paginate(20);
        $interviews->getCollection()->transform(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview));

        $next = LongTermJobInterview::with(['job', 'candidate'])
            ->where('agency_id', $client->agency_id)
            ->where(function (Builder $scope) use ($jobIds, $client): void {
                $scope->whereIn('long_term_job_id', $jobIds)
                    ->orWhere('client_id', $client->id);
            })
            ->where('scheduled_date', '>=', now()->toDateString())
            ->whereIn('status', ['requested', 'scheduled'])
            ->orderBy('scheduled_date')
            ->orderBy('available_from')
            ->first();

        return $this->sendResponse([
            'view' => 'list',
            'next_interview' => $next ? $this->formatInterview($next) : null,
            'interviews' => $interviews,
        ], 'Interviews retrieved successfully.', 200);
    }

    // GET /client/interviews/{interview}
    public function show(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        if (! $this->ownsInterview($client, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        return $this->sendResponse(
            $this->formatInterview($interview->load(['job', 'candidate', 'application'])),
            'Interview retrieved successfully.',
            200
        );
    }

    // PUT /client/interviews/{interview}/reschedule
    public function reschedule(RescheduleInterviewRequest $request, LongTermJobInterview $interview): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        if (! $this->ownsInterview($client, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        if ($interview->status !== 'scheduled') {
            return $this->sendError('Only scheduled interviews can be rescheduled.', [], 422);
        }

        if ($this->changeDeadlinePassed($interview)) {
            return $this->sendError(
                sprintf('Interviews can only be rescheduled at least %d hours before the scheduled time.', self::CHANGE_DEADLINE_HOURS),
                [],
                422
            );
        }

        $validated = $request->validated();

        // A reschedule is a *request*: the proposed slot is stored separately so
        // the confirmed meeting is untouched until the agency approves it.
        $interview->update([
            'reschedule_date' => $validated['scheduled_date'],
            'reschedule_from' => $validated['available_from'],
            'reschedule_to' => $validated['available_to'],
            'reschedule_reason' => $validated['reason'],
            'reschedule_requested_at' => now(),
        ]);

        $interview = $interview->fresh()->load(['job', 'candidate']);

        $body = sprintf(
            '%s requested to reschedule the interview for "%s" to %s (%s). Awaiting agency confirmation.',
            trim($client->first_name.' '.$client->last_name),
            $interview->displayTitle(),
            $interview->reschedule_date?->format('M d, Y'),
            $this->formatTime($interview->reschedule_from).' - '.$this->formatTime($interview->reschedule_to)
        );
        $meta = [
            'interview_id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
            'reason' => $validated['reason'],
        ];

        $this->notifyAgencyAdmins($request->current_agency->id, 'interview_reschedule_requested', 'Interview Reschedule Requested', $body, null, $meta);

        return $this->sendResponse(
            $this->formatInterview($interview),
            'Reschedule request sent to the agency.',
            200
        );
    }

    // DELETE /client/interviews/{interview}
    public function cancel(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        if (! $this->ownsInterview($client, $interview)) {
            return $this->sendError('Not found.', [], 404);
        }

        if ($interview->status !== 'scheduled') {
            return $this->sendError('Only scheduled interviews can be cancelled.', [], 422);
        }

        if ($this->changeDeadlinePassed($interview)) {
            return $this->sendError(
                sprintf('Interviews can only be cancelled at least %d hours before the scheduled time.', self::CHANGE_DEADLINE_HOURS),
                [],
                422
            );
        }

        $interview->update(['status' => 'cancelled']);

        $interview->loadMissing(['job', 'candidate']);

        $body = sprintf(
            '%s cancelled the interview for "%s" scheduled on %s (%s).',
            trim($client->first_name.' '.$client->last_name),
            $interview->job?->title,
            $interview->scheduled_date?->format('M d, Y'),
            $this->formatTime($interview->available_from).' - '.$this->formatTime($interview->available_to)
        );
        $meta = [
            'interview_id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
        ];

        $this->notifyAgencyAdmins($request->current_agency->id, 'interview_cancelled', 'Interview Cancelled', $body, null, $meta);
        $this->notifyPortalUser($request->current_agency->id, $interview->candidate?->email, 'interview_cancelled', 'Interview Cancelled', $body, null, $meta);

        return $this->sendResponse([], 'Interview cancelled successfully.', 200);
    }

    private function ownsInterview(Client $client, LongTermJobInterview $interview): bool
    {
        return $interview->agency_id === $client->agency_id
            && ($interview->client_id === $client->id || $interview->job?->client_id === $client->id);
    }

    private function changeDeadlinePassed(LongTermJobInterview $interview): bool
    {
        $startsAt = $interview->startsAt();

        return $startsAt === null || now()->gte($startsAt->copy()->subHours(self::CHANGE_DEADLINE_HOURS));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInterview(LongTermJobInterview $interview): array
    {
        $job = $interview->job;
        $candidate = $interview->candidate;
        $description = $interview->description ?: $job?->description;
        $canChange = $interview->status === 'scheduled'
            && ! $interview->hasPendingReschedule()
            && ! $this->changeDeadlinePassed($interview);

        return [
            'id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
            'application_id' => $interview->long_term_job_application_id,
            'title' => $interview->displayTitle(),
            'description' => $description,
            'description_preview' => $description ? Str::limit($description, 120) : null,
            'date' => $interview->scheduled_date?->toDateString(),
            'day' => $interview->scheduled_date?->format('d'),
            'month' => $interview->scheduled_date?->format('M'),
            'time' => [
                'from' => $this->formatTime($interview->available_from),
                'to' => $this->formatTime($interview->available_to),
                'range' => $this->formatTimeRange($interview->available_from, $interview->available_to),
            ],
            'status' => $interview->status,
            'period' => $this->resolvePeriod($interview),
            'awaiting_agency' => $interview->isAwaitingAgency(),
            'pending_reschedule' => $interview->hasPendingReschedule(),
            'reschedule_request' => $interview->hasPendingReschedule() ? [
                'date' => $interview->reschedule_date?->toDateString(),
                'from' => $this->formatTime($interview->reschedule_from),
                'to' => $this->formatTime($interview->reschedule_to),
                'reason' => $interview->reschedule_reason,
            ] : null,
            'meeting' => [
                'type' => $interview->interview_type,
                'link' => $interview->interview_link,
                'can_join' => $interview->status === 'scheduled' && filled($interview->interview_link),
            ],
            'actions' => [
                'can_reschedule' => $canChange,
                'can_cancel' => $canChange,
                'change_deadline_hours' => self::CHANGE_DEADLINE_HOURS,
            ],
            'candidate' => [
                'id' => $candidate?->id,
                'name' => $candidate ? $this->candidateFullName($candidate) : null,
                'email' => $candidate?->email,
                'mobile' => $candidate?->mobile,
                'image_url' => $candidate?->image_url,
            ],
            'job' => [
                'id' => $job?->id,
                'title' => $job?->title,
                'address' => $job?->job_address,
                'city' => $job?->home_city,
                'province' => $job?->home_province,
                'postal_code' => $job?->home_postal_code,
                'country' => $job?->country,
                'compensation_amount' => $job?->compensation_amount,
                'compensation_currency' => $job?->compensation_currency,
                'compensation_type' => $job?->compensation_type,
            ],
            'special_note' => $interview->special_note,
            'reschedule_reason' => $interview->reschedule_reason,
        ];
    }

    /**
     * Flat calendar event: a self-contained interview payload with a composite
     * event id, structured location/compensation blocks, display labels,
     * action URLs, and the modal summary block.
     *
     * @return array<string, mixed>
     */
    private function formatCalendarEvent(LongTermJobInterview $interview): array
    {
        $job = $interview->job;
        $candidate = $interview->candidate;
        $date = $interview->scheduled_date;
        $description = $interview->description ?: $job?->description;
        $candidateName = $candidate ? $this->candidateFullName($candidate) : null;
        $canChange = $interview->status === 'scheduled'
            && ! $interview->hasPendingReschedule()
            && ! $this->changeDeadlinePassed($interview);
        $canJoin = $interview->status === 'scheduled' && filled($interview->interview_link);

        return [
            'id' => 'interview_'.$interview->id.'_'.($date?->toDateString() ?? 'unscheduled'),
            'interview_id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
            'application_id' => $interview->long_term_job_application_id,
            'job_type' => 'long_term',
            'job_type_label' => 'Long-term',
            'title' => $interview->displayTitle(),
            'candidate' => [
                'id' => $candidate?->id,
                'name' => $candidateName,
                'email' => $candidate?->email,
                'mobile' => $candidate?->mobile,
                'image_url' => $candidate?->image_url,
            ],
            'cover_image_url' => $job?->cover_image_url,
            'description' => $description,
            'description_preview' => $description ? Str::limit($description, 120) : null,
            'location' => $this->formatLocation($job),
            'date' => $date?->toDateString(),
            'date_label' => $date?->format('M d, Y'),
            'day' => $date?->format('d'),
            'month' => $date?->format('M'),
            'time' => [
                'from' => $this->formatTime($interview->available_from),
                'to' => $this->formatTime($interview->available_to),
                'range' => $this->formatTimeRange($interview->available_from, $interview->available_to),
            ],
            'compensation' => $this->formatCompensation($job),
            'status' => $interview->status,
            'status_label' => $this->formatStatusLabel($interview->status),
            'period' => $this->resolvePeriod($interview),
            'meeting' => [
                'type' => $interview->interview_type,
                'link' => $interview->interview_link,
                'can_join' => $canJoin,
            ],
            'awaiting_agency' => $interview->isAwaitingAgency(),
            'pending_reschedule' => $interview->hasPendingReschedule(),
            'reschedule_request' => $interview->hasPendingReschedule() ? [
                'date' => $interview->reschedule_date?->toDateString(),
                'from' => $this->formatTime($interview->reschedule_from),
                'to' => $this->formatTime($interview->reschedule_to),
                'reason' => $interview->reschedule_reason,
            ] : null,
            'special_note' => $interview->special_note,
            'reschedule_reason' => $interview->reschedule_reason,
            'actions' => [
                'can_reschedule' => $canChange,
                'can_cancel' => $canChange,
                'change_deadline_hours' => self::CHANGE_DEADLINE_HOURS,
                'view_details_url' => "/api/client/interviews/{$interview->id}",
                'reschedule_url' => "/api/client/interviews/{$interview->id}/reschedule",
                'cancel_url' => "/api/client/interviews/{$interview->id}",
            ],
            'modal' => [
                'title' => $interview->displayTitle(),
                'subtitle' => $candidateName,
                'date' => $date?->format('d M, D'),
                'time_range' => $this->formatTimeRange($interview->available_from, $interview->available_to),
                'can_join' => $canJoin,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLocation(?LongTermJob $job): array
    {
        return [
            'label' => collect([
                $job?->job_address,
                $job?->home_city,
                $job?->home_province,
                $job?->country,
            ])->filter()->implode(', ') ?: null,
            'street_address' => $job?->job_address,
            'city' => $job?->home_city,
            'province' => $job?->home_province,
            'postal_code' => $job?->home_postal_code,
            'country' => $job?->country,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCompensation(?LongTermJob $job): array
    {
        return [
            'amount' => $job?->compensation_amount,
            'currency' => $job?->compensation_currency,
            'type' => $job?->compensation_type,
            'label' => $job?->compensation_amount !== null
                ? $this->formatHourlyRate($job->compensation_amount)
                : null,
        ];
    }

    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
            'requested' => 'Requested',
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'declined' => 'Declined',
            default => Str::headline($status),
        };
    }

    private function formatTimeRange(?string $from, ?string $to): ?string
    {
        if (! $from || ! $to) {
            return null;
        }

        return $this->formatTime($from).' - '.$this->formatTime($to);
    }

    private function resolvePeriod(LongTermJobInterview $interview): string
    {
        if (in_array($interview->status, ['cancelled', 'declined'], true)) {
            return 'cancelled';
        }

        if ($interview->status === 'completed' || $interview->scheduled_date?->isBefore(now()->startOfDay())) {
            return 'previous';
        }

        return 'upcoming';
    }
}
