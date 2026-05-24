<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/long-term?status=running
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $status = $request->query('status');

            $query = LongTermJob::with(['client', 'schedules', 'children', 'latestAttendance'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $request->current_agency->id);

            if ($status) {
                $query->where('status', $status);
            }

            $jobs = $query->latest()->get();

            $counts = LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $request->current_agency->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->sendResponse([
                'counts' => $counts,
                'jobs' => $jobs,
            ], 'Jobs retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/long-term/{longTermJob}
    public function show(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $longTermJob->candidate_id !== $candidate->id) {
                return $this->sendError('Not found', [], 404);
            }

            $longTermJob->load(['client', 'schedules', 'children']);

            return $this->sendResponse($longTermJob, 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
