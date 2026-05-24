<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateDashboardController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/dashboard
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $agencyId = $request->current_agency->id;

            // Status statistics
            $shortTermJobCount = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->count();

            $longTermJobCount = LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->count();

            $runningJobCount = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->where('status', 'running')
                ->count()
                + LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->where('status', 'running')
                ->count();

            // Unique families (clients) the candidate has worked with
            $shortTermClientIds = ShortTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->pluck('client_id');

            $longTermClientIds = LongTermJob::where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->pluck('client_id');

            $familyCount = $shortTermClientIds->merge($longTermClientIds)->unique()->count();

            // Running short-term job
            $runningShortTermJob = ShortTermJob::with(['client', 'dates', 'latestAttendance'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->where('status', 'running')
                ->latest()
                ->first();

            // Running long-term job
            $runningLongTermJob = LongTermJob::with(['client', 'schedules', 'latestAttendance'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $agencyId)
                ->where('status', 'running')
                ->latest()
                ->first();

            // Available marketplace jobs (short-term)
            $availableShortTermJobs = ShortTermJob::with(['dates', 'location', 'client'])
                ->where('agency_id', $agencyId)
                ->where('status', 'marketplace')
                ->whereNull('candidate_id')
                ->latest()
                ->limit(6)
                ->get();

            // Available marketplace jobs (long-term)
            $availableLongTermJobs = LongTermJob::with(['schedules', 'location', 'client'])
                ->where('agency_id', $agencyId)
                ->where('status', 'marketplace')
                ->whereDoesntHave('applications', fn ($q) => $q->where('candidate_id', $candidate->id))
                ->latest()
                ->limit(6)
                ->get();

            return $this->sendResponse([
                'stats' => [
                    'short_term_jobs' => $shortTermJobCount,
                    'long_term_jobs' => $longTermJobCount,
                    'my_jobs' => $runningJobCount,
                    'my_families' => $familyCount,
                ],
                'running_short_term_job' => $runningShortTermJob,
                'running_long_term_job' => $runningLongTermJob,
                'available_short_term_jobs' => $availableShortTermJobs,
                'available_long_term_jobs' => $availableLongTermJobs,
            ], 'Dashboard retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
