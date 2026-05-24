<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobApplicationController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/short-term-marketplace
    public function marketplace(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $query = ShortTermJob::with(['dates', 'children', 'location', 'client'])
                ->where('agency_id', $request->current_agency->id)
                ->where('status', 'marketplace')
                ->whereNull('candidate_id');

            if ($request->has('location_id')) {
                $query->where('location_id', $request->query('location_id'));
            }

            $jobs = $query->latest()->paginate(20);

            return $this->sendResponse($jobs, 'Marketplace jobs retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/short-term-applications
    // Returns marketplace jobs the candidate has expressed interest in
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            return $this->sendResponse([], 'Short-term jobs do not use a separate applications table. Use the assigned jobs endpoint instead.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/short-term/{shortTermJob}/apply
    public function store(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($shortTermJob->agency_id !== $request->current_agency->id || $shortTermJob->status !== 'marketplace') {
                return $this->sendError('This job is not available.', [], 422);
            }

            if ($shortTermJob->candidate_id) {
                return $this->sendError('This job already has a hired candidate.', [], 422);
            }

            // For short-term jobs, expressing interest sets the candidate as the pending hire
            $shortTermJob->update(['candidate_id' => $candidate->id]);

            return $this->sendResponse($shortTermJob->fresh()->load(['dates', 'client']), 'Interest submitted. Awaiting client/agency confirmation.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /candidate/jobs/short-term/{shortTermJob}/apply
    public function destroy(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('You are not the selected candidate for this job.', [], 404);
            }

            if (! in_array($shortTermJob->status, ['marketplace', 'pending_approval'])) {
                return $this->sendError('Cannot withdraw from a job that is already running or completed.', [], 422);
            }

            $shortTermJob->update(['candidate_id' => null]);

            return $this->sendResponse([], 'Withdrawn successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
