<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreJobReviewRequest;
use App\Models\LongTermJob;
use App\Models\LongTermJobReview;
use App\Traits\ResolvesCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobReviewController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/jobs/long-term/{longTermJob}/reviews
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $longTermJob->candidate_id !== $candidate->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $reviews = LongTermJobReview::with('client')
            ->where('long_term_job_id', $longTermJob->id)
            ->latest()
            ->get();

        return $this->sendResponse([
            'my_review' => $reviews->firstWhere('candidate_id', $candidate->id),
            'reviews' => $reviews,
        ], 'Reviews retrieved.', 200);
    }

    // POST /candidate/jobs/long-term/{longTermJob}/reviews
    public function store(StoreJobReviewRequest $request, LongTermJob $longTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $longTermJob->candidate_id !== $candidate->id) {
            return $this->sendError('Not found.', [], 404);
        }

        if ($longTermJob->status !== 'completed') {
            return $this->sendError('Reviews can only be left for completed jobs.', [], 422);
        }

        $validated = $request->validated();

        $review = LongTermJobReview::updateOrCreate(
            ['long_term_job_id' => $longTermJob->id, 'candidate_id' => $candidate->id],
            [
                'client_id' => $longTermJob->client_id,
                'agency_id' => $longTermJob->agency_id,
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        return $this->sendResponse($review, 'Review submitted.', 201);
    }
}
