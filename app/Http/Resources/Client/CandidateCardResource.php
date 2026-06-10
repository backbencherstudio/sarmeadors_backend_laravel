<?php

namespace App\Http\Resources\Client;

use App\Models\Candidate;
use App\Traits\PresentsCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Candidate
 */
class CandidateCardResource extends JsonResource
{
    use PresentsCandidate;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $candidate = $this->resource;

        return [
            'id' => $candidate->id,
            'name' => $this->candidateFullName($candidate),
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'image_url' => $candidate->image_url,
            'roles' => $this->candidateRoleNames($candidate),
            'location' => $this->candidateLocationLabel($candidate),
            'experience_years' => $candidate->years_of_experience,
            'available_for' => $candidate->available_for ?? [],
            'rating' => [
                'average' => $candidate->average_rating,
                'count' => $candidate->reviews_count,
            ],
            'actions' => [
                'can_view_profile' => true,
            ],
        ];
    }
}
