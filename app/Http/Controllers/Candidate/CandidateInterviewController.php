<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJobInterview;
use App\Traits\FormatsTime;
use App\Traits\ResolvesCandidate;
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
        $filter = $request->query('period', is_string($requestFilter) ? $requestFilter : ($queryBuilderFilters['period'] ?? 'all'));
        $view = $request->query('view', 'list');

        unset($queryBuilderFilters['period']);

        if ($request->filled('search') && ! isset($queryBuilderFilters['search'])) {
            $queryBuilderFilters['search'] = $request->query('search');
        }

        $request->merge(['filter' => $queryBuilderFilters]);

        $query = QueryBuilder::for(
            LongTermJobInterview::with(['job', 'job.client', 'client', 'application'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $request->current_agency->id),
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

    private function formatInterview(LongTermJobInterview $interview): array
    {
        $job = $interview->job;
        $client = $job?->client ?? $interview->client;
        $description = $interview->description ?: $job?->description;

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
