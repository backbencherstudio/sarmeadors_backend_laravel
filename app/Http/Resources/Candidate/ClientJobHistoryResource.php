<?php

namespace App\Http\Resources\Candidate;

use App\Models\LongTermJob;
use App\Models\ShortTermJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShortTermJob|LongTermJob
 */
class ClientJobHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->resource;
        $isShortTerm = $job instanceof ShortTermJob;

        return [
            'id' => $job->id,
            'job_type' => $isShortTerm ? 'short_term' : 'long_term',
            'job_type_label' => $isShortTerm ? 'Short-Term Jobs' : 'Long-Term Jobs',
            'title' => $job->title,
            'description' => $job->description,
            'cover_image_url' => $job->cover_image_url,
            'address' => [
                'line' => $job->job_address,
                'city' => $job->home_city,
                'province' => $job->home_province,
                'postal_code' => $job->home_postal_code,
                'country' => $job->country,
            ],
            'compensation' => [
                'amount' => $job->compensation_amount,
                'currency' => $job->compensation_currency,
                'type' => $job->compensation_type,
            ],
            'status' => $job->status,
            'schedule' => $isShortTerm ? $job->dates : $job->schedules,
            'my_review' => $job->review,
            'can_leave_review' => $job->status === 'completed' && ! $job->review,
            'can_view_review' => (bool) $job->review,
            'can_report_client' => false,
        ];
    }
}
