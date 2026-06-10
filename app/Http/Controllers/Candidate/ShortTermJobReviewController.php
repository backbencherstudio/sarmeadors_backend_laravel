<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreJobReviewRequest;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobReview;
use App\Traits\ResolvesCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobReviewController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/jobs/short-term/{shortTermJob}/reviews
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $shortTermJob->candidate_id !== $candidate->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $reviews = ShortTermJobReview::with('client')
            ->where('short_term_job_id', $shortTermJob->id)
            ->latest()
            ->get();

        return $this->sendResponse([
            'my_review' => $reviews->firstWhere('candidate_id', $candidate->id),
            'reviews' => $reviews,
        ], 'Reviews retrieved.', 200);
    }

    // POST /candidate/jobs/short-term/{shortTermJob}/reviews
    public function store(StoreJobReviewRequest $request, ShortTermJob $shortTermJob): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $shortTermJob->candidate_id !== $candidate->id) {
            return $this->sendError('Not found.', [], 404);
        }

        if ($shortTermJob->status !== 'completed') {
            return $this->sendError('Reviews can only be left for completed jobs.', [], 422);
        }

        $validated = $request->validated();

        $review = ShortTermJobReview::updateOrCreate(
            ['short_term_job_id' => $shortTermJob->id, 'candidate_id' => $candidate->id],
            [
                'client_id' => $shortTermJob->client_id,
                'agency_id' => $shortTermJob->agency_id,
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        return $this->sendResponse($review, 'Review submitted.', 201);
    }
}
