<?php

namespace App\Http\Resources\Candidate;

use App\Models\CandidateClientReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateClientReport
 */
class ClientReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agency_id' => $this->agency_id,
            'candidate_id' => $this->candidate_id,
            'client_id' => $this->client_id,
            'short_term_job_id' => $this->short_term_job_id,
            'long_term_job_id' => $this->long_term_job_id,
            'job_type' => $this->job_type,
            'reason' => $this->reason,
            'status' => $this->status,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
