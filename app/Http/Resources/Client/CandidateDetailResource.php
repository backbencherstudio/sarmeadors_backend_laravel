<?php

namespace App\Http\Resources\Client;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Traits\PresentsCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Candidate
 */
class CandidateDetailResource extends JsonResource
{
    use PresentsCandidate;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $candidate = $this->resource;

        return [
            'header' => [
                'id' => $candidate->id,
                'name' => $this->candidateFullName($candidate),
                'image_url' => $candidate->image_url,
                'roles' => $this->candidateRoleNames($candidate),
                'rating' => [
                    'average' => $candidate->average_rating,
                    'count' => $candidate->reviews_count,
                ],
            ],
            'personal_information' => [
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'date_of_birth' => $candidate->date_of_birth?->toDateString(),
                'nationality' => $candidate->nationality,
                'mobile' => $candidate->mobile,
                'address' => [
                    'street_address' => $candidate->street_address,
                    'city' => $candidate->city,
                    'province' => $candidate->province,
                    'postal_code' => $candidate->postal_code,
                    'country' => $candidate->country,
                ],
            ],
            'professional_information' => [
                'hours_per_week' => $candidate->hours_per_week,
                'bilingual' => $candidate->bilingual,
                'pay_range_per_hour' => $candidate->pay_range_per_hour,
                'start_date' => $candidate->start_date?->toDateString(),
                'last_position_end_reason' => $candidate->last_position_end_reason,
            ],
            'reference' => [
                'first_name' => $candidate->reference_first_name,
                'last_name' => $candidate->reference_last_name,
                'phone' => $candidate->reference_phone,
                'email' => $candidate->reference_email,
                'relation' => $candidate->reference_relation,
                'description' => $candidate->reference_description,
            ],
            'additional_information' => [
                'interested_in_iowa' => $candidate->interested_in_iowa,
                'years_of_experience' => $candidate->years_of_experience,
                'commitment' => $candidate->commitment,
                'available_for' => $candidate->available_for ?? [],
                'drivers_license' => $candidate->drivers_license,
                'cpr_first_aid' => $candidate->cpr_first_aid,
                'vaccinations' => $candidate->vaccinations,
                'ok_with_pets' => $candidate->ok_with_pets,
                'ok_with_travel' => $candidate->ok_with_travel,
                'work_legally_in_us' => $candidate->work_legally_in_us,
                'comfortable_paid_legally' => $candidate->comfortable_paid_legally,
                'has_ssn' => $candidate->has_ssn,
                'hear_about_us' => $candidate->hear_about_us,
            ],
            'documents' => $this->formatDocuments($candidate),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function formatDocuments(Candidate $candidate): Collection
    {
        return CandidateDocument::where('candidate_id', $candidate->id)
            ->where('agency_id', $candidate->agency_id)
            ->whereIn('status', ['uploaded', 'signed'])
            ->latest()
            ->get()
            ->map(fn (CandidateDocument $document): array => [
                'id' => $document->id,
                'category' => $document->category,
                'title' => $document->title,
                'file_name' => $document->original_file_name,
                'file_url' => $document->file_url,
                'status' => $document->status,
            ]);
    }
}
