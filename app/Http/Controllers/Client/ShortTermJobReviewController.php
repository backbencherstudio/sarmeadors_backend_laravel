<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShortTermJobReviewController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/jobs/short-term/{shortTermJob}/reviews
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if (! $shortTermJob->candidate_id) {
                return $this->sendResponse(['my_review' => null, 'reviews' => []], 'No candidate assigned.', 200);
            }

            $reviews = ShortTermJobReview::with('client')
                ->where('candidate_id', $shortTermJob->candidate_id)
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

    // POST /client/jobs/short-term/{shortTermJob}/reviews
    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($shortTermJob->status !== 'completed') {
                return $this->sendError('Reviews can only be left for completed jobs.', [], 422);
            }

            if (! $shortTermJob->candidate_id) {
                return $this->sendError('No candidate assigned to this job.', [], 422);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|between:1,5',
                'review' => 'nullable|string|max:2000',
            ]);

            $review = ShortTermJobReview::updateOrCreate(
                ['short_term_job_id' => $shortTermJob->id, 'client_id' => $client->id],
                [
                    'candidate_id' => $shortTermJob->candidate_id,
                    'agency_id' => $shortTermJob->agency_id,
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
