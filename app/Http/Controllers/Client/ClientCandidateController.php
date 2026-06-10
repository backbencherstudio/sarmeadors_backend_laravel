<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\FormSubmission;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientCandidateController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/candidates
    // tab=new (applied to client's active jobs) | tab=previous (hired + completed)
    public function index(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;
            $tab = $request->query('tab', 'new');

            if ($tab === 'previous') {
                $candidateIds = LongTermJob::where('client_id', $client->id)
                    ->where('status', 'completed')
                    ->whereNotNull('candidate_id')
                    ->pluck('candidate_id')
                    ->unique();

                $candidates = Candidate::with('reviews')
                    ->where('agency_id', $agencyId)
                    ->whereIn('id', $candidateIds)
                    ->paginate(12);
            } else {
                $jobIds = LongTermJob::where('client_id', $client->id)->pluck('id');

                $candidateIds = LongTermJobApplication::whereIn('long_term_job_id', $jobIds)
                    ->pluck('candidate_id')
                    ->unique();

                $candidates = Candidate::with('reviews')
                    ->where('agency_id', $agencyId)
                    ->whereIn('id', $candidateIds)
                    ->paginate(12);
            }

            $candidates->each(fn ($c) => $c->append(['image_url', 'average_rating', 'reviews_count']));

            return $this->sendResponse($candidates, 'Candidates retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/candidates/discover
    // All agency candidates for browsing (marketplace discovery)
    public function discover(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            $query = Candidate::with('reviews')
                ->where('agency_id', $agencyId);

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $candidates = $query->paginate(12);
            $candidates->each(fn ($c) => $c->append(['image_url', 'average_rating', 'reviews_count']));

            return $this->sendResponse($candidates, 'Candidates retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/candidates/{candidate}
    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            if ($candidate->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $candidate->append(['image_url', 'average_rating', 'reviews_count']);

            $reviews = LongTermJobReview::with('client')
                ->where('candidate_id', $candidate->id)
                ->latest()
                ->get();

            $myReview = $reviews->firstWhere('client_id', $client->id);

            $formSubmissions = FormSubmission::with('form', 'fieldValues.field')
                ->where('entity_id', $candidate->id)
                ->get();

            $application = LongTermJobApplication::whereHas('job', fn ($q) => $q->where('client_id', $client->id))
                ->where('candidate_id', $candidate->id)
                ->with(['job', 'interview'])
                ->latest()
                ->first();

            return $this->sendResponse([
                'candidate' => $candidate,
                'reviews' => [
                    'my_review' => $myReview,
                    'all' => $reviews,
                ],
                'form_submissions' => $formSubmissions,
                'application' => $application,
            ], 'Candidate retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /client/candidates/{candidate}/reviews
    public function reviews(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $reviews = LongTermJobReview::with('client')
            ->where('candidate_id', $candidate->id)
            ->latest()
            ->get();

        return $this->sendResponse([
            'average_rating' => round($reviews->avg('rating') ?? 0, 1),
            'reviews_count' => $reviews->count(),
            'my_review' => $reviews->firstWhere('client_id', $client->id),
            'reviews' => $reviews,
        ], 'Reviews retrieved successfully.', 200);
    }

    // POST /client/candidates/{candidate}/reviews
    public function storeReview(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $validated = $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'review' => 'nullable|string|max:5000',
        ]);

        $existingJob = LongTermJob::where('client_id', $client->id)
            ->where('candidate_id', $candidate->id)
            ->latest()
            ->first();

        $review = LongTermJobReview::updateOrCreate(
            [
                'client_id' => $client->id,
                'candidate_id' => $candidate->id,
                'long_term_job_id' => $existingJob?->id,
            ],
            [
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        return $this->sendResponse($review->load('client'), 'Review submitted.', 201);
    }

    // POST /client/candidates/{candidate}/hire-request
    // Triggers the job-creation flow for a specific candidate (short-term or long-term)
    public function hireRequest(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $validated = $request->validate([
            'job_type' => 'required|in:short-term,long-term',
            'note' => 'nullable|string|max:1000',
        ]);

        return $this->sendResponse([
            'candidate_id' => $candidate->id,
            'job_type' => $validated['job_type'],
            'status' => 'broadcast_requested',
            'message' => 'The job has been forwarded to the admin for broadcasting. Once the admin starts the broadcast, we will keep you updated.',
            'requested_at' => now()->toDateTimeString(),
        ], 'Hire request sent for broadcasting.', 201);
    }

    // POST /client/candidates/{candidate}/interview-request
    // Request an interview directly from the candidate detail page
    public function interviewRequest(Request $request, Candidate $candidate): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        if ($candidate->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $validated = $request->validate([
            'job_type' => 'required|in:short-term,long-term',
            'interview_type' => 'required|in:in_person,zoom,google_meet',
            'description' => 'nullable|string|max:2000',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'available_from' => 'required|date_format:H:i',
            'available_to' => 'required|date_format:H:i|after:available_from',
            'special_note' => 'nullable|string|max:1000',
        ]);

        return $this->sendResponse([
            'candidate_id' => $candidate->id,
            'job_type' => $validated['job_type'],
            'interview_type' => $validated['interview_type'],
            'scheduled_date' => $validated['scheduled_date'],
            'available_from' => $validated['available_from'],
            'available_to' => $validated['available_to'],
            'status' => 'scheduled',
            'requested_at' => now()->toDateTimeString(),
        ], 'Interview request created.', 201);
    }
}
