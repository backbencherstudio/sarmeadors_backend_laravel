<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreCandidateReviewRequest;
use App\Http\Requests\Client\StoreHireRequestRequest;
use App\Http\Requests\Client\StoreInterviewRequestRequest;
use App\Http\Resources\Client\CandidateCardResource;
use App\Http\Resources\Client\CandidateDetailResource;
use App\Http\Resources\Client\CandidateReviewResource;
use App\Http\Resources\Client\HireRequestResource;
use App\Models\Candidate;
use App\Models\CandidateJobRequest;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobReview;
use App\Models\ShortTermJob;
use App\Traits\ResolvesClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientCandidateController extends Controller
{
    use ResolvesClient;

    // GET /client/candidates?tab=new|previous
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $candidateIds = $request->query('tab') === 'previous'
            ? $this->previousCandidateIds($client->id)
            : $this->newCandidateIds($client->id);

        $candidates = Candidate::with('reviews')
            ->where('agency_id', $request->current_agency->id)
            ->whereIn('id', $candidateIds)
            ->paginate(12);

        return $this->sendResponse(
            CandidateCardResource::collection($candidates),
            'Candidates retrieved successfully.',
            200
        );
    }

    // GET /client/candidates/discover
    public function discover(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $candidates = Candidate::with('reviews')
            ->where('agency_id', $request->current_agency->id)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('search'));

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->paginate(12);

        return $this->sendResponse(
            CandidateCardResource::collection($candidates),
            'Candidates retrieved successfully.',
            200
        );
    }

    // GET /client/candidates/{candidate}
    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $candidate->load('reviews');

        $reviews = LongTermJobReview::with('client')
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id)
            ->latest()
            ->get();

        $myReview = $reviews->firstWhere('client_id', $client->id);

        return $this->sendResponse([
            'candidate' => new CandidateDetailResource($candidate),
            'reviews' => [
                'average' => $candidate->average_rating,
                'count' => $reviews->count(),
                'my_review' => $myReview ? new CandidateReviewResource($myReview) : null,
                'items' => CandidateReviewResource::collection($reviews),
            ],
            'application' => $this->latestApplicationContext($client->id, $candidate->id),
            'actions' => [
                'can_review' => true,
                'can_hire_request' => true,
            ],
        ], 'Candidate retrieved successfully.', 200);
    }

    // GET /client/candidates/{candidate}/reviews
    public function reviews(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $reviews = LongTermJobReview::with('client')
            ->where('candidate_id', $candidate->id)
            ->where('agency_id', $request->current_agency->id)
            ->latest()
            ->get();

        return $this->sendResponse([
            'average' => round((float) $reviews->avg('rating'), 1) ?: null,
            'count' => $reviews->count(),
            'my_review' => ($mine = $reviews->firstWhere('client_id', $client->id))
                ? new CandidateReviewResource($mine)
                : null,
            'items' => CandidateReviewResource::collection($reviews),
        ], 'Reviews retrieved successfully.', 200);
    }

    // POST /client/candidates/{candidate}/reviews
    public function storeReview(StoreCandidateReviewRequest $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validated();

        $existingJob = LongTermJob::where('client_id', $client->id)
            ->where('candidate_id', $candidate->id)
            ->latest()
            ->first();

        if (! $existingJob) {
            return $this->sendError('You can only review a candidate you have worked with on a long-term job.', [], 422);
        }

        $review = LongTermJobReview::updateOrCreate(
            [
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'long_term_job_id' => $existingJob->id,
            ],
            [
                'agency_id' => $request->current_agency->id,
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        return $this->sendResponse(
            new CandidateReviewResource($review->load('client')),
            'Review submitted.',
            201
        );
    }

    // POST /client/candidates/{candidate}/hire-request
    // Posts a private short-term job for a specific candidate and sends them a
    // pending request they can approve/reject from their Requested Jobs page.
    // Long-term hires go through the interview flow first.
    public function hireRequest(StoreHireRequestRequest $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validated();

        if ($validated['job_type'] === 'long-term') {
            return $this->sendError('Long-term hires require scheduling an interview first.', [], 422);
        }

        $jobRequest = DB::transaction(function () use ($request, $client, $candidate, $validated): CandidateJobRequest {
            $job = ShortTermJob::create([
                'agency_id' => $request->current_agency->id,
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'location_id' => $validated['location_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'job_address' => $validated['job_address'],
                'home_city' => $validated['home_city'],
                'home_province' => $validated['home_province'],
                'home_postal_code' => $validated['home_postal_code'],
                'country' => $validated['country'],
                'compensation_amount' => $validated['compensation_amount'],
                'compensation_currency' => $validated['compensation_currency'] ?? 'usd',
                'compensation_type' => $validated['compensation_type'] ?? 'per_hour',
                'status' => 'pending_approval',
            ]);

            foreach ($validated['dates'] as $date) {
                $job->dates()->create($date);
            }

            return CandidateJobRequest::create([
                'agency_id' => $request->current_agency->id,
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'short_term_job_id' => $job->id,
                'job_type' => 'short_term',
                'status' => 'pending',
                'message' => $validated['note'] ?? null,
            ]);
        });

        return $this->sendResponse(
            new HireRequestResource($jobRequest->load(['candidate', 'shortTermJob'])),
            'Hire request sent to candidate.',
            201
        );
    }

    // POST /client/candidates/{candidate}/interview-request
    public function interviewRequest(StoreInterviewRequestRequest $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Candidate not found.', [], 404);
        }

        $validated = $request->validated();

        return $this->sendResponse([
            'candidate_id' => $candidate->id,
            'job_type' => $validated['job_type'],
            'interview_type' => $validated['interview_type'],
            'scheduled_date' => $validated['scheduled_date'],
            'available_from' => $validated['available_from'],
            'available_to' => $validated['available_to'],
            'status' => 'scheduled',
            'requested_at' => now()->toIso8601String(),
        ], 'Interview request created.', 201);
    }

    /**
     * Candidate ids who applied to the client's active long-term jobs.
     *
     * @return Collection<int, int>
     */
    private function newCandidateIds(int $clientId): Collection
    {
        $jobIds = LongTermJob::where('client_id', $clientId)->pluck('id');

        return LongTermJobApplication::whereIn('long_term_job_id', $jobIds)
            ->pluck('candidate_id')
            ->unique()
            ->values();
    }

    /**
     * Candidate ids the client has previously completed jobs with.
     *
     * @return Collection<int, int>
     */
    private function previousCandidateIds(int $clientId): Collection
    {
        return LongTermJob::where('client_id', $clientId)
            ->where('status', 'completed')
            ->whereNotNull('candidate_id')
            ->pluck('candidate_id')
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestApplicationContext(int $clientId, int $candidateId): ?array
    {
        $application = LongTermJobApplication::whereHas('job', fn (Builder $query) => $query->where('client_id', $clientId))
            ->where('candidate_id', $candidateId)
            ->with(['job', 'interview'])
            ->latest()
            ->first();

        if (! $application) {
            return null;
        }

        $interview = $application->interview;

        return [
            'id' => $application->id,
            'status' => $application->status,
            'job' => [
                'id' => $application->job?->id,
                'title' => $application->job?->title,
            ],
            'interview' => $interview ? [
                'id' => $interview->id,
                'status' => $interview->status,
                'scheduled_date' => $interview->scheduled_date?->toDateString(),
                'type' => $interview->interview_type,
                'link' => $interview->interview_link,
            ] : null,
        ];
    }
}
