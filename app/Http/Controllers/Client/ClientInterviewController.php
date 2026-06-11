<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RescheduleInterviewRequest;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobInterview;
use App\Traits\FormatsTime;
use App\Traits\PresentsCandidate;
use App\Traits\ResolvesClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClientInterviewController extends Controller
{
    use FormatsTime;
    use PresentsCandidate;
    use ResolvesClient;

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
                ->whereIn('long_term_job_id', $jobIds),
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
                ->where('status', 'scheduled');
        } elseif ($filter === 'previous') {
            $query->where(function ($q) {
                $q->where('scheduled_date', '<', now()->toDateString())
                    ->orWhere('status', 'completed');
            });
        } elseif ($filter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        if ($view === 'calendar') {
            $month = $request->query('month', now()->month);
            $year = $request->query('year', now()->year);

            $interviews = $query->whereMonth('scheduled_date', $month)
                ->whereYear('scheduled_date', $year)
                ->get();

            $grouped = $interviews
                ->map(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview))
                ->groupBy('date');

            return $this->sendResponse([
                'view' => 'calendar',
                'month' => (int) $month,
                'year' => (int) $year,
                'interviews' => $grouped,
            ], 'Interviews retrieved successfully.', 200);
        }

        $interviews = $query->paginate(20);
        $interviews->getCollection()->transform(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview));

        $next = LongTermJobInterview::with(['job', 'candidate'])
            ->whereIn('long_term_job_id', $jobIds)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
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

        $interview->update([
            'scheduled_date' => $validated['scheduled_date'],
            'available_from' => $validated['available_from'],
            'available_to' => $validated['available_to'],
            'reschedule_reason' => $validated['reason'],
        ]);

        return $this->sendResponse(
            $this->formatInterview($interview->fresh()->load(['job', 'candidate'])),
            'Interview rescheduled successfully.',
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

        return $this->sendResponse([], 'Interview cancelled successfully.', 200);
    }

    private function ownsInterview(Client $client, LongTermJobInterview $interview): bool
    {
        return $interview->agency_id === $client->agency_id
            && $interview->job?->client_id === $client->id;
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
        $canChange = $interview->status === 'scheduled' && ! $this->changeDeadlinePassed($interview);

        return [
            'id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
            'application_id' => $interview->long_term_job_application_id,
            'title' => $job?->title,
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

    private function formatTimeRange(?string $from, ?string $to): ?string
    {
        if (! $from || ! $to) {
            return null;
        }

        return $this->formatTime($from).' - '.$this->formatTime($to);
    }

    private function resolvePeriod(LongTermJobInterview $interview): string
    {
        if ($interview->status === 'cancelled') {
            return 'cancelled';
        }

        if ($interview->status === 'completed' || $interview->scheduled_date?->isBefore(now()->startOfDay())) {
            return 'previous';
        }

        return 'upcoming';
    }
}
