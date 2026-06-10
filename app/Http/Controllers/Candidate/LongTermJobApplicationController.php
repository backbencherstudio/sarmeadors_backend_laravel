<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LongTermJobApplicationController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/long-term/marketplace
    // Browse marketplace jobs available for application
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

            $baseQuery = LongTermJob::with(['client', 'schedules', 'children'])
                ->where('agency_id', $request->current_agency->id)
                ->where('status', 'marketplace')
                ->whereDoesntHave('applications', function ($q) use ($candidate) {
                    $q->where('candidate_id', $candidate->id);
                });

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

            $jobs = $query->latest()->paginate(10);
            $jobs->getCollection()->transform(fn (LongTermJob $job): array => $this->formatJobCard($job, $candidate));

            return $this->sendResponse($jobs, 'Marketplace jobs retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/long-term-marketplace/{longTermJob}
    public function showMarketplace(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($longTermJob->agency_id !== $request->current_agency->id || $longTermJob->status !== 'marketplace') {
                return $this->sendError('Job not found.', [], 404);
            }

            $longTermJob->load(['client', 'children', 'schedules']);

            return $this->sendResponse($this->formatJobDetails($longTermJob, $candidate), 'Marketplace job retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/long-term/applications
    // My submitted applications
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $applications = LongTermJobApplication::with(['job.client', 'job.schedules'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $request->current_agency->id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($applications, 'Applications retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/long-term/{longTermJob}/apply
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($longTermJob->status !== 'marketplace' || $longTermJob->agency_id !== $request->current_agency->id) {
                return $this->sendError('This job is not available for applications.', [], 422);
            }

            $existing = LongTermJobApplication::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->first();

            if ($existing) {
                return $this->sendError('You have already applied to this job.', [], 422);
            }

            $validated = $request->validate([
                'application_message' => 'nullable|string|max:2000',
            ]);

            $application = LongTermJobApplication::create([
                'long_term_job_id' => $longTermJob->id,
                'candidate_id' => $candidate->id,
                'agency_id' => $longTermJob->agency_id,
                'application_message' => $validated['application_message'] ?? null,
            ]);

            return $this->sendResponse($application, 'Application submitted successfully.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /candidate/jobs/long-term/{longTermJob}/apply
    // Withdraw application
    public function destroy(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $application = LongTermJobApplication::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->where('status', 'pending')
                ->first();

            if (! $application) {
                return $this->sendError('No pending application found.', [], 404);
            }

            $application->delete();

            return $this->sendResponse([], 'Application withdrawn.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function formatJobCard(LongTermJob $job, Candidate $candidate): array
    {
        $hasApplied = LongTermJobApplication::where('long_term_job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->exists();

        return [
            'id' => $job->id,
            'job_type' => 'long_term',
            'title' => $job->title,
            'client_name' => $this->formatClientName($job->client),
            'cover_image_url' => $job->cover_image_url,
            'description' => $job->description,
            'description_preview' => $job->description ? Str::limit($job->description, 140) : null,
            'services' => $this->formatServices($job),
            'location' => $this->formatLocation($job),
            'compensation' => $this->formatCompensation($job),
            'status' => $job->status,
            'has_applied' => $hasApplied,
            'can_apply' => $job->status === 'marketplace' && ! $hasApplied,
        ];
    }

    private function formatJobDetails(LongTermJob $job, Candidate $candidate): array
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
            'schedule' => $job->schedules
                ->sortBy('day_of_week')
                ->values()
                ->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'day_name' => $this->formatDayName((int) $schedule->day_of_week),
                    'start_time' => $this->formatTime($schedule->start_time),
                    'end_time' => $this->formatTime($schedule->end_time),
                    'time_range' => $this->formatTime($schedule->start_time).' - '.$this->formatTime($schedule->end_time),
                ]),
            'address' => $this->formatAddress($job),
            'budget' => $this->formatCompensation($job),
            'additional_information' => [
                'family_schedule' => $job->family_schedule,
                'household_tasks' => $job->household_tasks,
                'family_philosophy' => $job->family_philosophy,
                'play_dates' => $job->play_dates,
                'addons' => $job->addons,
                'home_description' => $job->home_description,
                'neighborhood_description' => $job->neighborhood_description,
                'nanny_experience' => $job->nanny_experience,
                'payment_norms' => $job->payment_norms,
                'consent_description' => $job->consent_description,
                'child_attachment' => $job->child_attachment,
            ],
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

    private function formatServices(LongTermJob $job): array
    {
        return collect(['Nanny'])
            ->when($job->has_housekeeper, fn (Collection $services) => $services->push('House Manager'))
            ->when($job->children?->isNotEmpty(), fn (Collection $services) => $services->push('Baby/Night Nurse'))
            ->values()
            ->all();
    }

    private function formatLocation(LongTermJob $job): array
    {
        return [
            'label' => collect([$job->home_city, $job->home_province, $job->country])->filter()->implode(', '),
            'city' => $job->home_city,
            'province' => $job->home_province,
            'country' => $job->country,
        ];
    }

    private function formatAddress(LongTermJob $job): array
    {
        return [
            'street_address' => $job->job_address,
            'city' => $job->home_city,
            'province' => $job->home_province,
            'postal_code' => $job->home_postal_code,
            'country' => $job->country,
        ];
    }

    private function formatCompensation(LongTermJob $job): array
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

    private function formatDayName(int $day): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? 'Unknown';
    }
}
