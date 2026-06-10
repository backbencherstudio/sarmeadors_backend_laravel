<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobReview;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CandidateClientsController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/clients
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $shortTermClientIds = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->pluck('client_id');

            $longTermClientIds = LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->pluck('client_id');

            $allClientIds = $shortTermClientIds->merge($longTermClientIds)->unique()->values();

            if ($request->filled('search') && ! $request->has('filter.search')) {
                $request->merge([
                    'filter' => array_merge((array) $request->query('filter', []), [
                        'search' => $request->query('search'),
                    ]),
                ]);
            }

            $query = QueryBuilder::for(Client::query()->whereIn('id', $allClientIds), $request)
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
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                    })
                );

            $clients = $query->paginate($request->integer('per_page', 10));
            $pageClientIds = $clients->getCollection()->pluck('id');

            $shortTermJobs = ShortTermJob::where('candidate_id', $candidate->id)
                ->whereIn('client_id', $pageClientIds)
                ->where('agency_id', $agencyId)
                ->latest()
                ->get()
                ->groupBy('client_id');

            $longTermJobs = LongTermJob::where('candidate_id', $candidate->id)
                ->whereIn('client_id', $pageClientIds)
                ->where('agency_id', $agencyId)
                ->latest()
                ->get()
                ->groupBy('client_id');

            $clients->getCollection()->transform(function (Client $client) use ($shortTermJobs, $longTermJobs) {
                $latestJob = $shortTermJobs->get($client->id, collect())
                    ->concat($longTermJobs->get($client->id, collect()))
                    ->sortByDesc('created_at')
                    ->first();

                return $this->formatClientListItem($client, $latestJob);
            });

            return $this->sendResponse($clients, 'Clients retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/clients/{client}
    public function show(Request $request, Client $client): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $hasWorkedWith = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->exists()
                || LongTermJob::where('candidate_id', $candidate->id)
                    ->where('client_id', $client->id)
                    ->where('agency_id', $agencyId)
                    ->exists();

            if (! $hasWorkedWith || $client->agency_id !== $agencyId) {
                return $this->sendError('Client not found.', [], 404);
            }

            $shortTermJobs = ShortTermJob::with(['dates', 'review' => fn ($q) => $q->where('candidate_id', $candidate->id)])
                ->where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->latest()
                ->get();

            $longTermJobs = LongTermJob::with(['schedules', 'review' => fn ($q) => $q->where('candidate_id', $candidate->id)])
                ->where('candidate_id', $candidate->id)
                ->where('client_id', $client->id)
                ->where('agency_id', $agencyId)
                ->latest()
                ->get();

            $jobHistory = $this->formatJobHistory(
                $shortTermJobs
                    ->concat($longTermJobs)
                    ->sortByDesc('created_at')
                    ->values()
            );

            return $this->sendResponse([
                'client' => $this->formatClientDetails($client, $agencyId),
                'job_history' => $jobHistory,
            ], 'Client details retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function formatClientListItem(Client $client, ShortTermJob|LongTermJob|null $latestJob): array
    {
        return [
            'id' => $client->id,
            'name' => $this->formatClientName($client),
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'mobile' => $client->mobile,
            'image_url' => $client->image_url,
            'job_type' => $latestJob ? $this->formatJobTypeLabel($latestJob) : null,
            'job_status' => $latestJob?->status,
        ];
    }

    private function formatClientDetails(Client $client, int $agencyId): array
    {
        return [
            'id' => $client->id,
            'name' => $this->formatClientName($client),
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'mobile' => $client->mobile,
            'image_url' => $client->image_url,
            'rating' => $this->getClientRatingSummary($client, $agencyId),
        ];
    }

    private function formatJobHistory(Collection $jobs): Collection
    {
        return $jobs->map(function (ShortTermJob|LongTermJob $job): array {
            $isShortTerm = $job instanceof ShortTermJob;
            $schedule = $isShortTerm ? $job->dates : $job->schedules;

            return [
                'id' => $job->id,
                'job_type' => $isShortTerm ? 'short_term' : 'long_term',
                'job_type_label' => $this->formatJobTypeLabel($job),
                'title' => $job->title,
                'description' => $job->description,
                'cover_image_url' => $job->cover_image_url,
                'address' => [
                    'line' => $job->job_address,
                    'city' => $job->home_city,
                    'province' => $job->home_province,
                    'postal_code' => $job->home_postal_code,
                    'country' => $job->country,
                ],
                'compensation' => [
                    'amount' => $job->compensation_amount,
                    'currency' => $job->compensation_currency,
                    'type' => $job->compensation_type,
                ],
                'status' => $job->status,
                'schedule' => $schedule,
                'my_review' => $job->review,
                'can_leave_review' => $job->status === 'completed' && ! $job->review,
                'can_view_review' => (bool) $job->review,
                'can_report_client' => false,
            ];
        });
    }

    private function getClientRatingSummary(Client $client, int $agencyId): array
    {
        $shortTermReviews = ShortTermJobReview::where('client_id', $client->id)
            ->where('agency_id', $agencyId);

        $longTermReviews = LongTermJobReview::where('client_id', $client->id)
            ->where('agency_id', $agencyId);

        $ratings = $shortTermReviews->pluck('rating')
            ->merge($longTermReviews->pluck('rating'));

        return [
            'average' => $ratings->isEmpty() ? null : round($ratings->avg(), 1),
            'count' => $ratings->count(),
        ];
    }

    private function formatClientName(Client $client): string
    {
        return trim($client->first_name.' '.$client->last_name);
    }

    private function formatJobTypeLabel(ShortTermJob|LongTermJob $job): string
    {
        if ($job instanceof ShortTermJob) {
            return 'Short-Term Jobs';
        }

        return 'Long-Term Jobs';
    }
}
