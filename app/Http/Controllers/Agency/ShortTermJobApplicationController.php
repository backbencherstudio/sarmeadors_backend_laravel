<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobApplicationController extends Controller
{
    private function resolveJob(Request $request, ShortTermJob $job): bool
    {
        return $job->agency_id === $request->current_agency->id;
    }

    // GET /agency/jobs/short-term/{shortTermJob}/applicants
    public function index(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $shortTermJob)) {
                return $this->sendError('Not found.', [], 404);
            }

            $candidate = $shortTermJob->candidate_id
                ? Candidate::with('reviews')->find($shortTermJob->candidate_id)
                : null;

            if ($candidate) {
                $candidate->append(['image_url', 'average_rating', 'reviews_count']);
            }

            return $this->sendResponse([
                'hired_candidate' => $candidate,
                'status' => $shortTermJob->status,
            ], 'Applicants retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /agency/jobs/short-term/{shortTermJob}/applicants/{applicationId}
    public function show(Request $request, ShortTermJob $shortTermJob, int $applicationId): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $shortTermJob)) {
                return $this->sendError('Not found.', [], 404);
            }

            $candidate = Candidate::with('reviews')
                ->where('agency_id', $request->current_agency->id)
                ->find($applicationId);

            if (! $candidate) {
                return $this->sendError('Candidate not found.', [], 404);
            }

            $candidate->append(['image_url', 'average_rating', 'reviews_count']);

            return $this->sendResponse($candidate, 'Candidate retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/short-term/{shortTermJob}/applicants/{applicationId}/confirm-hire
    public function confirmHire(Request $request, ShortTermJob $shortTermJob, int $applicationId): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $shortTermJob)) {
                return $this->sendError('Not found.', [], 404);
            }

            if ($shortTermJob->candidate_id !== $applicationId) {
                return $this->sendError('This candidate has not been selected for hire by the client.', [], 422);
            }

            if (! in_array($shortTermJob->status, ['marketplace', 'pending_approval'])) {
                return $this->sendError('Job is not in a hireable state.', [], 422);
            }

            $shortTermJob->update(['status' => 'running']);

            return $this->sendResponse(
                $shortTermJob->fresh()->load(['candidate', 'dates']),
                'Hire confirmed. Job is now running.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
