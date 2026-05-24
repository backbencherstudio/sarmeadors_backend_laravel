<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use App\Models\LongTermJobReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LongTermJobReviewController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/long-term/{longTermJob}/reviews
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $longTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $reviews = LongTermJobReview::with('client')
                ->where('long_term_job_id', $longTermJob->id)
                ->latest()
                ->get();

            $myReview = $reviews->firstWhere('candidate_id', $candidate->id);

            return $this->sendResponse([
                'my_review' => $myReview,
                'reviews' => $reviews,
            ], 'Reviews retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/long-term/{longTermJob}/reviews
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $longTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($longTermJob->status !== 'completed') {
                return $this->sendError('Reviews can only be left for completed jobs.', [], 422);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|between:1,5',
                'review' => 'nullable|string|max:2000',
            ]);

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
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
