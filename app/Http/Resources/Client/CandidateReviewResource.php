<?php

namespace App\Http\Resources\Client;

use App\Models\LongTermJobReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LongTermJobReview
 */
class CandidateReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $client = $review->client;

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'review' => $review->review,
            'created_at' => $review->created_at?->toIso8601String(),
            'reviewer' => $client ? [
                'id' => $client->id,
                'name' => trim($client->first_name.' '.$client->last_name),
                'image_url' => $client->image_url,
            ] : null,
        ];
    }
}
