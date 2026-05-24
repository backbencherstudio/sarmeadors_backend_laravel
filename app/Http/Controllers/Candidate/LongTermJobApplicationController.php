<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LongTermJobApplicationController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/jobs/long-term/marketplace
    // Browse marketplace jobs available for application
    public function marketplace(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $jobs = LongTermJob::with(['client', 'schedules', 'children'])
                ->where('agency_id', $request->current_agency->id)
                ->where('status', 'marketplace')
                ->whereDoesntHave('applications', function ($q) use ($candidate) {
                    $q->where('candidate_id', $candidate->id);
                })
                ->latest()
                ->paginate(10);

            return $this->sendResponse($jobs, 'Marketplace jobs retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/jobs/long-term/applications
    // My submitted applications
    public function index(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $applications = LongTermJobApplication::with(['job.client', 'job.schedules'])
                ->where('candidate_id', $candidate->id)
                ->where('agency_id', $request->current_agency->id)
                ->latest()
                ->paginate(10);

            return $this->sendResponse($applications, 'Applications retrieved.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/jobs/long-term/{longTermJob}/apply
    public function store(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            if ($longTermJob->status !== 'marketplace' || $longTermJob->agency_id !== $request->current_agency->id) {
                return $this->sendError('This job is not available for applications.', [], 422);
            }

            $existing = LongTermJobApplication::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->first();

            if ($existing) {
                return $this->sendError('You have already applied to this job.', [], 422);
            }

            $validated = $request->validate([
                'application_message' => 'nullable|string|max:2000',
            ]);

            $application = LongTermJobApplication::create([
                'long_term_job_id' => $longTermJob->id,
                'candidate_id' => $candidate->id,
                'agency_id' => $longTermJob->agency_id,
                'application_message' => $validated['application_message'] ?? null,
            ]);

            return $this->sendResponse($application, 'Application submitted successfully.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /candidate/jobs/long-term/{longTermJob}/apply
    // Withdraw application
    public function destroy(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $application = LongTermJobApplication::where('long_term_job_id', $longTermJob->id)
                ->where('candidate_id', $candidate->id)
                ->where('status', 'pending')
                ->first();

            if (! $application) {
                return $this->sendError('No pending application found.', [], 404);
            }

            $application->delete();

            return $this->sendResponse([], 'Application withdrawn.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
