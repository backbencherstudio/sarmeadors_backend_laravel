<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LongTermJobReviewController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/long-term/{longTermJob}/reviews
    // Returns all reviews for the candidate assigned to this job
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if (! $longTermJob->candidate_id) {
                return $this->sendResponse([], 'No candidate assigned.', 200);
            }

            $reviews = LongTermJobReview::with('client')
                ->where('candidate_id', $longTermJob->candidate_id)
                ->latest()
                ->get();

            $myReview = $reviews->firstWhere('client_id', $client->id);

            return $this->sendResponse([
                'my_review' => $myReview,
                'reviews' => $reviews,
            ], 'Reviews retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/long-term/{longTermJob}/reviews
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'completed') {
                return $this->sendError('Reviews can only be left for completed jobs.', [], 422);
            }

            if (! $longTermJob->candidate_id) {
                return $this->sendError('No candidate assigned to this job.', [], 422);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|between:1,5',
                'review' => 'nullable|string|max:2000',
            ]);

            $review = LongTermJobReview::updateOrCreate(
                ['long_term_job_id' => $longTermJob->id, 'client_id' => $client->id],
                [
                    'candidate_id' => $longTermJob->candidate_id,
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
