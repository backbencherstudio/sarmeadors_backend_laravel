<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobApplication;
use App\Traits\FormatsJobPosting;
use App\Traits\FormatsMoney;
use App\Traits\FormatsTime;
use App\Traits\MergesSearchFilter;
use App\Traits\ResolvesCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ShortTermJobApplicationController extends Controller
{
    use FormatsJobPosting;
    use FormatsMoney;
    use FormatsTime;
    use MergesSearchFilter;
    use ResolvesCandidate;

    // GET /candidate/jobs/short-term-marketplace
    public function marketplace(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if ($request->filled('search') && ! $request->has('filter.search')) {
            $request->merge([
                'filter' => array_merge((array) $request->query('filter', []), [
                    'search' => $request->query('search'),
                ]),
            ]);
        }

        $baseQuery = ShortTermJob::with(['dates', 'children', 'location', 'client'])
            ->where('agency_id', $request->current_agency->id)
            ->where('status', 'marketplace')
            ->whereNull('candidate_id')
            ->whereDoesntHave('applications', fn ($q) => $q->where('candidate_id', $candidate->id));

        if ($request->has('location_id')) {
            $baseQuery->where('location_id', $request->query('location_id'));
        }

        $query = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = collect(is_array($value) ? $value : [$value])
                        ->map(fn (mixed $term): string => trim((string) $term))
                        ->filter()
                        ->implode(' ');

                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('home_city', 'like', "%{$search}%")
                            ->orWhere('home_province', 'like', "%{$search}%")
                            ->orWhere('country', 'like', "%{$search}%")
                            ->orWhere('job_address', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    });
                })
            );

        $jobs = $query->latest()->paginate(20);
        $jobs->getCollection()->transform(fn (ShortTermJob $job): array => $this->formatMarketplaceCard($job, $candidate));

        return $this->sendResponse($jobs, 'Marketplace jobs retrieved.', 200);
    }

    // GET /candidate/jobs/short-term-marketplace/{shortTermJob}
    public function showMarketplace(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if ($shortTermJob->agency_id !== $request->current_agency->id || $shortTermJob->status !== 'marketplace') {
            return $this->sendError('Job not found.', [], 404);
        }

        $shortTermJob->load(['client', 'children', 'dates']);

        return $this->sendResponse($this->formatJobDetails($shortTermJob, $candidate), 'Marketplace job retrieved.', 200);
    }

    // GET /candidate/jobs/short-term-applications
    // Returns marketplace jobs the candidate has expressed interest in
    public function index(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $this->mergeSearchFilter($request);

        $baseQuery = ShortTermJob::with([
            'dates',
            'children',
            'location',
            'client',
            'applications' => fn ($q) => $q->where('candidate_id', $candidate->id),
        ])
            ->where('agency_id', $request->current_agency->id)
            ->where(function ($query) use ($candidate) {
                $query->where('candidate_id', $candidate->id)
                    ->orWhereHas('applications', fn ($q) => $q->where('candidate_id', $candidate->id));
            });

        $query = QueryBuilder::for($baseQuery, $request)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = $this->normalizeSearchValue($value);

                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('home_city', 'like', "%{$search}%")
                            ->orWhere('home_province', 'like', "%{$search}%")
                            ->orWhere('country', 'like', "%{$search}%")
                            ->orWhere('job_address', 'like', "%{$search}%")
                            ->orWhereHas('client', function (Builder $clientQuery) use ($search): void {
                                $clientQuery->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
                })
            );

        $jobs = $query->latest()->paginate($request->integer('per_page', 10));
        $jobs->getCollection()->transform(fn (ShortTermJob $job): array => $this->formatAppliedJobCard($job, $candidate));

        return $this->sendResponse([
            'notice' => 'Applied job records that have been closed for more than 30 days will be automatically deleted.',
            'jobs' => $jobs,
        ], 'Applied short-term jobs retrieved.', 200);
    }

    // GET /candidate/jobs/short-term-applications/{shortTermJob}
    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $hasApplication = ShortTermJobApplication::where('short_term_job_id', $shortTermJob->id)
            ->where('candidate_id', $candidate->id)
            ->exists();

        if ($shortTermJob->agency_id !== $request->current_agency->id
            || ($shortTermJob->candidate_id !== $candidate->id && ! $hasApplication)) {
            return $this->sendError('Applied job not found.', [], 404);
        }

        $shortTermJob->load([
            'client',
            'children',
            'dates',
            'applications' => fn ($q) => $q->where('candidate_id', $candidate->id),
        ]);

        return $this->sendResponse($this->formatAppliedJobDetails($shortTermJob, $candidate), 'Applied short-term job retrieved.', 200);
    }

    // POST /candidate/jobs/short-term/{shortTermJob}/apply
    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        if ($shortTermJob->agency_id !== $request->current_agency->id || $shortTermJob->status !== 'marketplace') {
            return $this->sendError('This job is not available.', [], 422);
        }

        if ($shortTermJob->candidate_id) {
            return $this->sendError('This job already has a hired candidate.', [], 422);
        }

        $existing = ShortTermJobApplication::where('short_term_job_id', $shortTermJob->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existing) {
            return $this->sendError('You have already applied to this job.', [], 422);
        }

        $validated = $request->validate([
            'application_message' => 'nullable|string|max:2000',
        ]);

        $application = ShortTermJobApplication::create([
            'short_term_job_id' => $shortTermJob->id,
            'candidate_id' => $candidate->id,
            'agency_id' => $shortTermJob->agency_id,
            'application_message' => $validated['application_message'] ?? null,
        ]);

        return $this->sendResponse($application, 'Application submitted successfully.', 201);
    }

    // DELETE /candidate/jobs/short-term/{shortTermJob}/apply
    public function destroy(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate) {
            return $this->sendError('Candidate profile not found.', [], 404);
        }

        $application = ShortTermJobApplication::where('short_term_job_id', $shortTermJob->id)
            ->where('candidate_id', $candidate->id)
            ->where('status', 'pending')
            ->first();

        if ($application) {
            $application->delete();

            return $this->sendResponse([], 'Application withdrawn.', 200);
        }

        // Legacy claim flow: the candidate was set directly as the pending hire
        if ($shortTermJob->candidate_id !== $candidate->id) {
            return $this->sendError('No pending application found.', [], 404);
        }

        if (! in_array($shortTermJob->status, ['marketplace', 'pending_approval'])) {
            return $this->sendError('Cannot withdraw from a job that is already running or completed.', [], 422);
        }

        $shortTermJob->update(['candidate_id' => null]);

        return $this->sendResponse([], 'Withdrawn successfully.', 200);
    }

    /**
     * Shared job card plus the marketplace context for this candidate.
     *
     * @return array<string, mixed>
     */
    private function formatMarketplaceCard(ShortTermJob $job, Candidate $candidate): array
    {
        $hasApplied = $job->candidate_id === $candidate->id
            || ShortTermJobApplication::where('short_term_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->exists();

        return array_merge($this->formatJobCard($job), [
            'client_name' => $this->formatClientName($job->client),
            'has_applied' => $hasApplied,
            'can_apply' => $job->status === 'marketplace' && is_null($job->candidate_id) && ! $hasApplied,
        ]);
    }

    private function formatAppliedJobCard(ShortTermJob $job, Candidate $candidate): array
    {
        return array_merge($this->formatMarketplaceCard($job, $candidate), [
            'application' => $this->formatApplicationMeta($job, $candidate),
            'interview' => null,
            'actions' => [
                'can_view_details' => true,
                'can_open_interview' => false,
            ],
        ]);
    }

    private function formatAppliedJobDetails(ShortTermJob $job, Candidate $candidate): array
    {
        return array_merge($this->formatJobDetails($job, $candidate), [
            'application' => $this->formatApplicationMeta($job, $candidate),
            'interview' => null,
            'actions' => [
                'can_view_details' => true,
                'can_open_interview' => false,
            ],
        ]);
    }

    /**
     * Application meta from the candidate's application row when one exists,
     * falling back to the legacy direct-assignment shape (candidate_id set on
     * the job without an application record).
     *
     * @return array<string, mixed>
     */
    private function formatApplicationMeta(ShortTermJob $job, Candidate $candidate): array
    {
        $application = $job->relationLoaded('applications')
            ? $job->applications->firstWhere('candidate_id', $candidate->id)
            : ShortTermJobApplication::where('short_term_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->first();

        if (! $application) {
            return [
                'id' => $job->id,
                'type' => 'short_term_assignment',
                'status' => $job->status,
                'status_label' => $this->formatStatusLabel($job->status),
                'applied_at' => $job->updated_at?->toISOString(),
            ];
        }

        return [
            'id' => $application->id,
            'type' => 'short_term_application',
            'message' => $application->application_message,
            'status' => $application->status,
            'status_label' => $this->formatStatusLabel($application->status),
            'job_status' => $job->status,
            'job_status_label' => $this->formatStatusLabel($job->status),
            'applied_at' => $application->created_at?->toISOString(),
        ];
    }

    private function formatJobDetails(ShortTermJob $job, Candidate $candidate): array
    {
        return [
            'job' => $this->formatMarketplaceCard($job, $candidate),
            'client' => [
                'id' => $job->client?->id,
                'name' => $this->formatClientName($job->client),
                'email' => $job->client?->email,
                'mobile' => $job->client?->mobile,
                'image_url' => $job->client?->image_url,
            ],
            'children' => $this->formatChildren($job->children),
            'booking_dates' => $job->dates
                ->sortBy(['booking_date', 'start_time'])
                ->values()
                ->map(fn ($date): array => [
                    'id' => $date->id,
                    'date' => $date->booking_date,
                    'start_time' => $this->formatTime($date->start_time),
                    'end_time' => $this->formatTime($date->end_time),
                    'time_range' => $this->formatTime($date->start_time).' - '.$this->formatTime($date->end_time),
                ]),
            'address' => $this->formatAddress($job),
            'budget' => $this->formatCompensation($job),
        ];
    }

    private function formatCompensation(ShortTermJob $job): array
    {
        return [
            'amount' => $job->compensation_amount,
            'currency' => $job->compensation_currency,
            'type' => $job->compensation_type,
            'label' => $this->formatHourlyRate($job->compensation_amount),
        ];
    }

    private function formatClientName($client): ?string
    {
        return $client ? trim($client->first_name.' '.$client->last_name) : null;
    }

    private function formatStatusLabel(?string $status): ?string
    {
        return match ($status) {
            'pending', 'pending_approval' => 'Pending',
            'marketplace' => 'Available',
            'running' => 'Running Job',
            'completed' => 'Closed Job',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            default => $status ? Str::headline($status) : null,
        };
    }
}
