<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateJobRequest;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\ShortTermJob;
use App\Traits\ResolvesCandidate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CandidateRequestedJobController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/jobs/requested
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $this->mergeSearchFilter($request);

        $baseQuery = CandidateJobRequest::with($this->relations())
            ->where('agency_id', $request->current_agency->id)
            ->where('candidate_id', $candidate->id);

        if (! $request->has('filter.status')) {
            $baseQuery->where('status', 'pending');
        }

        $query = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('job_type'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = $this->normalizeSearchValue($value);

                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($search): void {
                        $query->whereHas('client', function (Builder $clientQuery) use ($search): void {
                            $clientQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })->orWhereHas('shortTermJob', function (Builder $jobQuery) use ($search): void {
                            $this->applyJobSearch($jobQuery, $search);
                        })->orWhereHas('longTermJob', function (Builder $jobQuery) use ($search): void {
                            $this->applyJobSearch($jobQuery, $search);
                        });
                    });
                })
            );

        $requests = $query->latest()->paginate($request->integer('per_page', 10));
        $requests->getCollection()->transform(fn (CandidateJobRequest $jobRequest): array => $this->formatRequestCard($jobRequest));

        return $this->sendResponse([
            'notice' => 'These requests come from your previous Clients to work with them again.',
            'requests' => $requests,
        ], 'Requested jobs retrieved.', 200);
    }

    // GET /candidate/jobs/requested/{candidateJobRequest}
    public function show(Request $request, CandidateJobRequest $candidateJobRequest): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if (! $this->canAccessRequest($request, $candidate, $candidateJobRequest)) {
            return $this->sendError('Requested job not found.', [], 404);
        }

        return $this->sendResponse(
            $this->formatRequestDetails($candidateJobRequest->load($this->relations())),
            'Requested job retrieved.',
            200
        );
    }

    // PUT /candidate/jobs/requested/{candidateJobRequest}/approve
    public function approve(Request $request, CandidateJobRequest $candidateJobRequest): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if (! $this->canAccessRequest($request, $candidate, $candidateJobRequest)) {
            return $this->sendError('Requested job not found.', [], 404);
        }

        if ($candidateJobRequest->status !== 'pending') {
            return $this->sendError('Only pending requests can be approved.', [], 422);
        }

        if ($candidateJobRequest->job_type === 'short_term') {
            $this->approveShortTermRequest($candidateJobRequest, $candidate);
        } else {
            $this->approveLongTermRequest($candidateJobRequest, $candidate);
        }

        $candidateJobRequest->update([
            'status' => 'approved',
            'responded_at' => now(),
        ]);

        return $this->sendResponse(
            $this->formatRequestDetails($candidateJobRequest->fresh($this->relations())),
            'Request approved.',
            200
        );
    }

    // PUT /candidate/jobs/requested/{candidateJobRequest}/reject
    public function reject(Request $request, CandidateJobRequest $candidateJobRequest): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if (! $this->canAccessRequest($request, $candidate, $candidateJobRequest)) {
            return $this->sendError('Requested job not found.', [], 404);
        }

        if ($candidateJobRequest->status !== 'pending') {
            return $this->sendError('Only pending requests can be rejected.', [], 422);
        }

        if ($candidateJobRequest->job_type === 'short_term') {
            $this->rejectShortTermRequest($candidateJobRequest, $candidate);
        } else {
            $this->rejectLongTermRequest($candidateJobRequest);
        }

        $candidateJobRequest->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return $this->sendResponse(
            $this->formatRequestDetails($candidateJobRequest->fresh($this->relations())),
            'Request rejected.',
            200
        );
    }

    private function approveShortTermRequest(CandidateJobRequest $jobRequest, Candidate $candidate): void
    {
        $job = $jobRequest->shortTermJob;

        if (! $job) {
            return;
        }

        $updates = ['candidate_id' => $candidate->id];

        if (in_array($job->status, ['marketplace', 'pending_approval'])) {
            $updates['status'] = 'pending_approval';
        }

        $job->update($updates);
    }

    private function approveLongTermRequest(CandidateJobRequest $jobRequest, Candidate $candidate): void
    {
        $application = $jobRequest->longTermJobApplication;

        if (! $application && $jobRequest->longTermJob) {
            $application = LongTermJobApplication::firstOrCreate(
                [
                    'long_term_job_id' => $jobRequest->longTermJob->id,
                    'candidate_id' => $candidate->id,
                ],
                [
                    'agency_id' => $jobRequest->agency_id,
                    'application_message' => $jobRequest->message,
                    'status' => 'pending',
                ]
            );

            $jobRequest->update(['long_term_job_application_id' => $application->id]);
        }

        $application?->update(['status' => 'hired']);
    }

    private function rejectShortTermRequest(CandidateJobRequest $jobRequest, Candidate $candidate): void
    {
        $job = $jobRequest->shortTermJob;

        if ($job && $job->candidate_id === $candidate->id && in_array($job->status, ['marketplace', 'pending_approval'])) {
            $job->update(['candidate_id' => null]);
        }
    }

    private function rejectLongTermRequest(CandidateJobRequest $jobRequest): void
    {
        $jobRequest->longTermJobApplication?->update(['status' => 'rejected']);
    }

    private function formatRequestCard(CandidateJobRequest $jobRequest): array
    {
        $job = $this->resolveJob($jobRequest);

        return [
            'id' => $jobRequest->id,
            'job_id' => $job?->id,
            'job_type' => $jobRequest->job_type,
            'title' => $job?->title,
            'client_name' => $this->formatClientName($jobRequest),
            'cover_image_url' => $job?->cover_image_url,
            'description' => $job?->description,
            'description_preview' => $job?->description ? Str::limit($job->description, 140) : null,
            'services' => $job ? $this->formatServices($job) : [],
            'location' => $job ? $this->formatLocation($job) : null,
            'schedule_summary' => $job ? $this->formatScheduleSummary($jobRequest, $job) : null,
            'compensation' => $job ? $this->formatCompensation($job) : null,
            'request' => $this->formatRequestMeta($jobRequest),
            'actions' => [
                'can_view_details' => true,
                'can_approve' => $jobRequest->status === 'pending',
                'can_reject' => $jobRequest->status === 'pending',
            ],
        ];
    }

    private function formatRequestDetails(CandidateJobRequest $jobRequest): array
    {
        $job = $this->resolveJob($jobRequest);

        return [
            'request' => $this->formatRequestMeta($jobRequest),
            'job' => $job ? $this->formatRequestCard($jobRequest) : null,
            'client' => [
                'id' => $jobRequest->client?->id,
                'name' => $this->formatClientName($jobRequest),
                'email' => $jobRequest->client?->email,
                'mobile' => $jobRequest->client?->mobile,
                'image_url' => $jobRequest->client?->image_url,
            ],
            'children' => $job ? $this->formatChildren($job->children) : collect(),
            'booking_dates' => $jobRequest->job_type === 'short_term' && $job instanceof ShortTermJob
                ? $this->formatShortTermDates($job)
                : null,
            'schedule' => $jobRequest->job_type === 'long_term' && $job instanceof LongTermJob
                ? $this->formatLongTermSchedule($job)
                : null,
            'address' => $job ? $this->formatAddress($job) : null,
            'budget' => $job ? $this->formatCompensation($job) : null,
            'actions' => [
                'can_approve' => $jobRequest->status === 'pending',
                'can_reject' => $jobRequest->status === 'pending',
            ],
            'raw' => $job,
        ];
    }

    private function formatRequestMeta(CandidateJobRequest $jobRequest): array
    {
        return [
            'id' => $jobRequest->id,
            'job_type' => $jobRequest->job_type,
            'status' => $jobRequest->status,
            'status_label' => Str::headline($jobRequest->status),
            'message' => $jobRequest->message,
            'requested_at' => $jobRequest->created_at?->toISOString(),
            'responded_at' => $jobRequest->responded_at?->toISOString(),
        ];
    }

    private function formatChildren(Collection $children): Collection
    {
        return $children->map(fn ($child): array => [
            'id' => $child->id,
            'name' => trim($child->first_name.' '.$child->last_name),
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
            'date_of_birth' => $child->date_of_birth,
            'gender' => $child->gender,
            'interests' => $child->interests,
            'allergies' => $child->allergies,
        ]);
    }

    private function formatShortTermDates(ShortTermJob $job): Collection
    {
        return $job->dates
            ->sortBy(['booking_date', 'start_time'])
            ->values()
            ->map(fn ($date): array => [
                'id' => $date->id,
                'date' => $date->booking_date,
                'start_time' => $this->formatTime($date->start_time),
                'end_time' => $this->formatTime($date->end_time),
                'time_range' => $this->formatTime($date->start_time).' - '.$this->formatTime($date->end_time),
            ]);
    }

    private function formatLongTermSchedule(LongTermJob $job): Collection
    {
        return $job->schedules
            ->sortBy('day_of_week')
            ->values()
            ->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'day_name' => $this->formatDayName((int) $schedule->day_of_week),
                'start_time' => $this->formatTime($schedule->start_time),
                'end_time' => $this->formatTime($schedule->end_time),
                'time_range' => $this->formatTime($schedule->start_time).' - '.$this->formatTime($schedule->end_time),
            ]);
    }

    private function formatScheduleSummary(CandidateJobRequest $jobRequest, ShortTermJob|LongTermJob $job): ?array
    {
        if ($jobRequest->job_type === 'short_term' && $job instanceof ShortTermJob) {
            $date = $job->dates->sortBy(['booking_date', 'start_time'])->first();

            return $date ? [
                'date' => $date->booking_date,
                'time_range' => $this->formatTime($date->start_time).' - '.$this->formatTime($date->end_time),
            ] : null;
        }

        if ($job instanceof LongTermJob) {
            $schedule = $job->schedules->sortBy('day_of_week')->first();

            return $schedule ? [
                'day_name' => $this->formatDayName((int) $schedule->day_of_week),
                'time_range' => $this->formatTime($schedule->start_time).' - '.$this->formatTime($schedule->end_time),
            ] : null;
        }

        return null;
    }

    private function formatServices(ShortTermJob|LongTermJob $job): array
    {
        return collect(['Nanny'])
            ->when($job->has_housekeeper ?? false, fn (Collection $services) => $services->push('House Manager'))
            ->when($job->children?->isNotEmpty(), fn (Collection $services) => $services->push('Baby/Night Nurse'))
            ->values()
            ->all();
    }

    private function formatLocation(ShortTermJob|LongTermJob $job): array
    {
        return [
            'label' => collect([$job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
            'city' => $job->home_city,
            'province' => $job->home_province,
            'country' => $job->country,
        ];
    }

    private function formatAddress(ShortTermJob|LongTermJob $job): array
    {
        return [
            'street_address' => $job->job_address,
            'city' => $job->home_city,
            'province' => $job->home_province,
            'postal_code' => $job->home_postal_code,
            'country' => $job->country,
        ];
    }

    private function formatCompensation(ShortTermJob|LongTermJob $job): array
    {
        return [
            'amount' => $job->compensation_amount,
            'currency' => $job->compensation_currency,
            'type' => $job->compensation_type,
            'label' => '$'.rtrim(rtrim((string) $job->compensation_amount, '0'), '.').'/hr',
        ];
    }

    private function resolveJob(CandidateJobRequest $jobRequest): ShortTermJob|LongTermJob|null
    {
        return $jobRequest->job_type === 'short_term'
            ? $jobRequest->shortTermJob
            : $jobRequest->longTermJob;
    }

    private function formatClientName(CandidateJobRequest $jobRequest): ?string
    {
        return $jobRequest->client ? trim($jobRequest->client->first_name.' '.$jobRequest->client->last_name) : null;
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : null;
    }

    private function formatDayName(int $day): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? 'Unknown';
    }

    private function canAccessRequest(Request $request, Candidate $candidate, CandidateJobRequest $jobRequest): bool
    {
        return $jobRequest->agency_id === $request->current_agency->id
            && $jobRequest->candidate_id === $candidate->id;
    }

    private function relations(): array
    {
        return [
            'client',
            'shortTermJob.children',
            'shortTermJob.dates',
            'longTermJob.children',
            'longTermJob.schedules',
            'longTermJobApplication',
        ];
    }

    private function applyJobSearch(Builder $query, string $search): void
    {
        $query->where('title', 'like', "%{$search}%")
            ->orWhere('home_city', 'like', "%{$search}%")
            ->orWhere('home_province', 'like', "%{$search}%")
            ->orWhere('country', 'like', "%{$search}%")
            ->orWhere('job_address', 'like', "%{$search}%");
    }

    private function mergeSearchFilter(Request $request): void
    {
        if ($request->filled('search') && ! $request->has('filter.search')) {
            $request->merge([
                'filter' => array_merge((array) $request->query('filter', []), [
                    'search' => $request->query('search'),
                ]),
            ]);
        }
    }

    private function normalizeSearchValue(mixed $value): string
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $term): string => trim((string) $term))
            ->filter()
            ->implode(' ');
    }
}
