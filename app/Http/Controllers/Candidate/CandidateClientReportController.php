<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreClientReportRequest;
use App\Http\Resources\Candidate\ClientReportResource;
use App\Models\CandidateClientReport;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use App\Traits\ResolvesCandidate;
use Illuminate\Http\JsonResponse;

class CandidateClientReportController extends Controller
{
    use ResolvesCandidate;

    public function reportShortTerm(StoreClientReportRequest $request, ShortTermJob $shortTermJob): JsonResponse
    {
        return $this->storeReport($request, 'short_term', $shortTermJob);
    }

    public function reportLongTerm(StoreClientReportRequest $request, LongTermJob $longTermJob): JsonResponse
    {
        return $this->storeReport($request, 'long_term', $longTermJob);
    }

    private function storeReport(StoreClientReportRequest $request, string $jobType, ShortTermJob|LongTermJob $job): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $job->candidate_id !== $candidate->id || $job->agency_id !== $request->current_agency->id) {
            return $this->sendError('Not found.', [], 404);
        }

        if (! in_array($job->status, ['completed', 'cancelled'], true)) {
            return $this->sendError('Clients can only be reported for completed or cancelled jobs.', [], 422);
        }

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
                'reason' => $request->validated()['reason'],
                'status' => 'pending',
                'resolved_at' => null,
            ]
        );

        return $this->sendResponse(new ClientReportResource($report), 'Client report submitted.', 201);
    }
}
