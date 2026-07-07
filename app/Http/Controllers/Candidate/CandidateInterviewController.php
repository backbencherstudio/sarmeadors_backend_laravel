<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Models\LongTermJobInterview;
use App\Traits\FormatsTime;
use App\Traits\ResolvesCandidate;
use App\Traits\SendsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CandidateInterviewController extends Controller
{
    use FormatsTime;
    use ResolvesCandidate;
    use SendsNotifications;

    // GET /candidate/interviews
    // view=list | view=calendar
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $requestFilter = $request->query('filter', []);
        $queryBuilderFilters = is_array($requestFilter) ? $requestFilter : [];
        $filter = $request->query('status', is_string($requestFilter) ? $requestFilter : ($queryBuilderFilters['status'] ?? 'all'));
        $view = $request->query('view', 'list');

        unset($queryBuilderFilters['status']);

        if ($request->filled('search') && ! isset($queryBuilderFilters['search'])) {
            $queryBuilderFilters['search'] = $request->query('search');
        }

        $request->merge(['filter' => $queryBuilderFilters]);

        $query = QueryBuilder::for(
            LongTermJobInterview::with(['job', 'job.client', 'client', 'application'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $request->current_agency->id)
                // Requests still awaiting the agency are not shown to the candidate;
                // they only see interviews once the agency has scheduled them.
                ->where('status', '!=', 'requested'),
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

                    $query->whereHas('job', function (Builder $jobQuery) use ($search): void {
                        $jobQuery->where('title', 'like', "%{$search}%")
                            ->orWhereHas('client', function (Builder $clientQuery) use ($search): void {
                                $clientQuery->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                })
            )
            ->orderBy('scheduled_date')
            ->orderBy('available_from');

        if ($filter === 'scheduled' || $filter === 'upcoming') {
            // "upcoming" is kept as a backwards-compatible alias for "scheduled".
            $query->where('scheduled_date', '>=', now()->toDateString())
                ->where('status', 'scheduled');
        } elseif ($filter === 'completed') {
            // The candidate joined the meeting, which auto-completes the interview.
            $query->where('status', 'completed');
        } elseif ($filter === 'scheduled_missed') {
            // Still scheduled but the day has passed — the candidate never joined.
            $query->where('status', 'scheduled')
                ->where('scheduled_date', '<', now()->toDateString());
        } elseif ($filter === 'previous') {
            // Backwards-compatible alias covering both completed and missed.
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
                    'status' => $filter,
                    'search' => $queryBuilderFilters['search'] ?? null,
                ],
                'interviews' => $events,
            ], 'Interviews retrieved successfully.', 200);
        }

        $interviews = $query->paginate(20);
        $interviews->getCollection()->transform(fn (LongTermJobInterview $interview): array => $this->formatInterview($interview));

        $next = LongTermJobInterview::with(['job', 'job.client', 'client'])
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id)
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

    // GET /candidate/interviews/{interview}
    public function show(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if ($interview->candidate_id !== $candidate->id || $interview->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        return $this->sendResponse(
            $this->formatInterview($interview->load(['job', 'job.client', 'client', 'application'])),
            'Interview retrieved successfully.',
            200
        );
    }

    // POST /candidate/interviews/{interview}/join
    // Joining the meeting auto-completes the interview and returns the link.
    public function join(Request $request, LongTermJobInterview $interview): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if ($interview->candidate_id !== $candidate->id || $interview->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        if ($interview->status !== 'scheduled') {
            return $this->sendError('This interview is not open to join.', [], 422);
        }

        if (blank($interview->interview_link)) {
            return $this->sendError('No meeting link is available for this interview yet.', [], 422);
        }

        $interview->update(['status' => 'completed']);

        $title = $interview->displayTitle();
        $this->notifyAgencyAdmins(
            $interview->agency_id,
            'interview_completed',
            'Interview Completed',
            trim($candidate->first_name.' '.$candidate->last_name).' joined the interview'
                .($title ? ' for "'.$title.'"' : '').', now marked as completed.',
            null,
            ['interview_id' => $interview->id, 'job_id' => $interview->long_term_job_id],
        );

        return $this->sendResponse([
            'meeting_link' => $interview->interview_link,
            'interview' => $this->formatInterview($interview->fresh()->load(['job', 'job.client', 'client', 'application'])),
        ], 'Interview joined. It has been marked as completed.', 200);
    }

    private function formatInterview(LongTermJobInterview $interview): array
    {
        $job = $interview->job;
        $client = $job?->client ?? $interview->client;
        $description = $interview->description ?: $job?->description;

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
            'meeting' => [
                'type' => $interview->interview_type,
                'link' => $interview->interview_link,
                'can_join' => $interview->status === 'scheduled' && filled($interview->interview_link),
                'join_url' => '/api/candidate/interviews/'.$interview->id.'/join',
            ],
            'client' => [
                'id' => $client?->id,
                'name' => trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: null,
                'email' => $client?->email,
                'mobile' => $client?->mobile,
                'image_url' => $client?->image_url,
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
        ];
    }

    private function formatCalendarEvent(LongTermJobInterview $interview): array
    {
        $job = $interview->job;
        $client = $job?->client ?? $interview->client;
        $date = $interview->scheduled_date;
        $description = $interview->description ?: $job?->description;
        $clientName = $client ? trim(($client->first_name ?? '').' '.($client->last_name ?? '')) : null;
        $canJoin = $interview->status === 'scheduled' && filled($interview->interview_link);

        return [
            'id' => 'interview_'.$interview->id.'_'.($date?->toDateString() ?? 'unscheduled'),
            'interview_id' => $interview->id,
            'job_id' => $interview->long_term_job_id,
            'application_id' => $interview->long_term_job_application_id,
            'job_type' => 'long_term',
            'job_type_label' => 'Long-term',
            'title' => $interview->displayTitle(),
            'client' => [
                'id' => $client?->id,
                'name' => $clientName,
                'email' => $client?->email,
                'mobile' => $client?->mobile,
                'image_url' => $client?->image_url,
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
                'join_url' => '/api/candidate/interviews/'.$interview->id.'/join',
            ],
            'special_note' => $interview->special_note,
            'modal' => [
                'title' => $interview->displayTitle(),
                'subtitle' => $clientName,
                'date' => $date?->format('d M, D'),
                'time_range' => $this->formatTimeRange($interview->available_from, $interview->available_to),
                'can_join' => $canJoin,
            ],
        ];
    }

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

    private function formatCompensation(?LongTermJob $job): array
    {
        return [
            'amount' => $job?->compensation_amount,
            'currency' => $job?->compensation_currency,
            'type' => $job?->compensation_type,
            'label' => $job?->compensation_amount !== null
                ? '$'.number_format($job->compensation_amount, 2).'/hr'
                : null,
        ];
    }

    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
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

        if ($interview->status === 'completed') {
            return 'completed';
        }

        // Still scheduled but the day has passed: the candidate never joined.
        if ($interview->scheduled_date?->isBefore(now()->startOfDay())) {
            return 'scheduled_missed';
        }

        return 'scheduled';
    }
}
