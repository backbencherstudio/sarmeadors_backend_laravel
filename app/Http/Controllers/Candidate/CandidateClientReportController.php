<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateClientReport;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CandidateClientReportController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    public function reportShortTerm(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        return $this->storeReport($request, 'short_term', $shortTermJob);
    }

    public function reportLongTerm(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        return $this->storeReport($request, 'long_term', $longTermJob);
    }

    private function storeReport(Request $request, string $jobType, ShortTermJob|LongTermJob $job): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $job->candidate_id !== $candidate->id || $job->agency_id !== $request->current_agency->id) {
                return $this->sendError('Not found.', [], 404);
            }

            if (! in_array($job->status, ['completed', 'cancelled'], true)) {
                return $this->sendError('Clients can only be reported for completed or cancelled jobs.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'required|string|max:2000',
            ]);

            $report = CandidateClientReport::updateOrCreate(
                [
                    'candidate_id' => $candidate->id,
                    'job_type' => $jobType,
                    'short_term_job_id' => $jobType === 'short_term' ? $job->id : null,
                    'long_term_job_id' => $jobType === 'long_term' ? $job->id : null,
                ],
                [
                    'agency_id' => $job->agency_id,
                    'client_id' => $job->client_id,
                    'reason' => $validated['reason'],
                    'status' => 'pending',
                    'resolved_at' => null,
                ]
            );

            return $this->sendResponse($report, 'Client report submitted.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
