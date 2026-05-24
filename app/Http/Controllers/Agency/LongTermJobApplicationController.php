<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobApplicationController extends Controller
{
    private function resolveJob(Request $request, LongTermJob $job): bool
    {
        return $job->agency_id === $request->current_agency->id;
    }

    // GET /agency/jobs/long-term/{longTermJob}/applicants
    public function index(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob)) {
                return $this->sendError('Not found', [], 404);
            }

            $applications = LongTermJobApplication::with(['candidate.reviews', 'interview'])
                ->where('long_term_job_id', $longTermJob->id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($applications, 'Applicants retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /agency/jobs/long-term/{longTermJob}/applicants/{application}
    public function show(Request $request, LongTermJob $longTermJob, LongTermJobApplication $application): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob) || $application->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            $application->load(['candidate.reviews', 'interview']);

            return $this->sendResponse($application, 'Applicant retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /agency/jobs/long-term/{longTermJob}/applicants/{application}/confirm-hire
    // Agency confirms the client's hire request and triggers nanny assignment
    public function confirmHire(Request $request, LongTermJob $longTermJob, LongTermJobApplication $application): JsonResponse
    {
        try {
            if (! $this->resolveJob($request, $longTermJob) || $application->long_term_job_id !== $longTermJob->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($application->status !== 'hired') {
                return $this->sendError('This application has not been marked for hire by the client.', [], 422);
            }

            $longTermJob->update([
                'candidate_id' => $application->candidate_id,
                'status' => 'running',
            ]);

            // Reject remaining pending applications
            LongTermJobApplication::where('long_term_job_id', $longTermJob->id)
                ->where('id', '!=', $application->id)
                ->whereIn('status', ['pending', 'interviewed'])
                ->update(['status' => 'rejected']);

            return $this->sendResponse(
                $longTermJob->fresh()->load(['candidate']),
                'Hire confirmed. Job is now running.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
