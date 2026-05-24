<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/short-term
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $status = $request->query('status');

            $query = ShortTermJob::with(['dates', 'children', 'location', 'client'])
                ->where('candidate_id', $candidate->id);

            if ($status) {
                $query->where('status', $status);
            }

            $jobs = $query->latest()->get();

            $counts = ShortTermJob::where('candidate_id', $candidate->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->sendResponse(['counts' => $counts, 'jobs' => $jobs], 'Jobs retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/short-term/{shortTermJob}
    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $shortTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $shortTermJob->load(['dates', 'children', 'location', 'client']);

            return $this->sendResponse($shortTermJob, 'Job retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
