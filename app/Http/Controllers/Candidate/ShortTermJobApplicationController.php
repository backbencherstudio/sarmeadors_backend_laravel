<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ShortTermJob;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ShortTermJobApplicationController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/short-term-marketplace
    public function marketplace(Request $request): JsonResponse
    {
        try {
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
                ->whereNull('candidate_id');

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
            $jobs->getCollection()->transform(fn (ShortTermJob $job): array => $this->formatJobCard($job, $candidate));

            return $this->sendResponse($jobs, 'Marketplace jobs retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/short-term-marketplace/{shortTermJob}
    public function showMarketplace(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($shortTermJob->agency_id !== $request->current_agency->id || $shortTermJob->status !== 'marketplace') {
                return $this->sendError('Job not found.', [], 404);
            }

            $shortTermJob->load(['client', 'children', 'dates']);

            return $this->sendResponse($this->formatJobDetails($shortTermJob, $candidate), 'Marketplace job retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/short-term-applications
    // Returns marketplace jobs the candidate has expressed interest in
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            return $this->sendResponse([], 'Short-term jobs do not use a separate applications table. Use the assigned jobs endpoint instead.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/short-term/{shortTermJob}/apply
    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
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

            // For short-term jobs, expressing interest sets the candidate as the pending hire
            $shortTermJob->update(['candidate_id' => $candidate->id]);

            return $this->sendResponse($shortTermJob->fresh()->load(['dates', 'client']), 'Interest submitted. Awaiting client/agency confirmation.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /candidate/jobs/short-term/{shortTermJob}/apply
    public function destroy(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('You are not the selected candidate for this job.', [], 404);
            }

            if (! in_array($shortTermJob->status, ['marketplace', 'pending_approval'])) {
                return $this->sendError('Cannot withdraw from a job that is already running or completed.', [], 422);
            }

            $shortTermJob->update(['candidate_id' => null]);

            return $this->sendResponse([], 'Withdrawn successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function formatJobCard(ShortTermJob $job, Candidate $candidate): array
    {
        return [
            'id' => $job->id,
            'job_type' => 'short_term',
            'title' => $job->title,
            'client_name' => $this->formatClientName($job->client),
            'cover_image_url' => $job->cover_image_url,
            'description' => $job->description,
            'description_preview' => $job->description ? Str::limit($job->description, 140) : null,
            'services' => $this->formatServices($job),
            'location' => $this->formatLocation($job),
            'compensation' => $this->formatCompensation($job),
            'status' => $job->status,
            'has_applied' => $job->candidate_id === $candidate->id,
            'can_apply' => $job->status === 'marketplace' && is_null($job->candidate_id),
        ];
    }

    private function formatJobDetails(ShortTermJob $job, Candidate $candidate): array
    {
        return [
            'job' => $this->formatJobCard($job, $candidate),
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
            'raw' => $job,
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

    private function formatServices(ShortTermJob $job): array
    {
        return collect(['Nanny'])
            ->when($job->has_housekeeper ?? false, fn (Collection $services) => $services->push('House Manager'))
            ->when($job->children?->isNotEmpty(), fn (Collection $services) => $services->push('Baby/Night Nurse'))
            ->values()
            ->all();
    }

    private function formatLocation(ShortTermJob $job): array
    {
        return [
            'label' => collect([$job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
            'city' => $job->home_city,
            'province' => $job->home_province,
            'country' => $job->country,
        ];
    }

    private function formatAddress(ShortTermJob $job): array
    {
        return [
            'street_address' => $job->job_address,
            'city' => $job->home_city,
            'province' => $job->home_province,
            'postal_code' => $job->home_postal_code,
            'country' => $job->country,
        ];
    }

    private function formatCompensation(ShortTermJob $job): array
    {
        return [
            'amount' => $job->compensation_amount,
            'currency' => $job->compensation_currency,
            'type' => $job->compensation_type,
            'label' => '$'.rtrim(rtrim((string) $job->compensation_amount, '0'), '.').'/hr',
        ];
    }

    private function formatClientName($client): ?string
    {
        return $client ? trim($client->first_name.' '.$client->last_name) : null;
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : null;
    }
}
