<?php

namespace App\Http\Resources\Client;

use App\Models\CandidateJobRequest;
use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateJobRequest
 */
class HireRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $jobRequest = $this->resource;
        $job = $jobRequest->job_type === 'short_term'
            ? $jobRequest->shortTermJob
            : $jobRequest->longTermJob;

        return [
            'id' => $jobRequest->id,
            'job_type' => $jobRequest->job_type,
            'status' => $jobRequest->status,
            'message' => $jobRequest->message,
            'requested_at' => $jobRequest->created_at?->toIso8601String(),
            'responded_at' => $jobRequest->responded_at?->toIso8601String(),
            'candidate' => $jobRequest->candidate ? [
                'id' => $jobRequest->candidate->id,
                'name' => trim($jobRequest->candidate->first_name.' '.$jobRequest->candidate->last_name),
                'image_url' => $jobRequest->candidate->image_url,
            ] : null,
            'job' => $job ? $this->formatJob($job) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatJob(ShortTermJob|LongTermJob $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'status' => $job->status,
            'compensation_amount' => $job->compensation_amount,
            'compensation_currency' => $job->compensation_currency,
            'compensation_type' => $job->compensation_type,
            'location' => collect([$job->home_city, $job->home_province, $job->country])
                ->filter()
                ->implode(', ') ?: null,
        ];
    }
}
