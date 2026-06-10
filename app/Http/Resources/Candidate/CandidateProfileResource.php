<?php

namespace App\Http\Resources\Candidate;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Candidate
 */
class CandidateProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $candidate = $this->resource;
        $user = $request->user();

        return [
            'profile' => [
                'header' => $this->formatHeader($candidate),
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
                'documents_summary' => $this->formatDocumentsSummary($candidate),
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
            ],
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'image' => $candidate->image_url,
            ],
            'candidate' => $candidate->append('image_url'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHeader(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => trim($candidate->first_name.' '.$candidate->last_name),
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'email' => $candidate->email,
            'mobile' => $candidate->mobile,
            'image_url' => $candidate->image_url,
            'completion_percentage' => $this->calculateCompletionPercentage($candidate),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function formatDocumentsSummary(Candidate $candidate): array
    {
        $documents = CandidateDocument::where('candidate_id', $candidate->id)
            ->where('agency_id', $candidate->agency_id)
            ->get();

        return [
            'total' => $documents->count(),
            'agreements_signed' => $documents->where('category', 'agreement')->where('status', 'signed')->count(),
            'required_uploaded' => $documents->where('category', 'required')->where('status', 'uploaded')->count(),
            'additional_uploaded' => $documents->where('category', 'additional')->where('status', 'uploaded')->count(),
        ];
    }

    private function calculateCompletionPercentage(Candidate $candidate): int
    {
        $fields = [
            'first_name',
            'last_name',
            'email',
            'mobile',
            'date_of_birth',
            'nationality',
            'street_address',
            'city',
            'province',
            'postal_code',
            'country',
            'hours_per_week',
            'bilingual',
            'pay_range_per_hour',
            'start_date',
            'reference_first_name',
            'reference_last_name',
            'reference_phone',
            'reference_email',
            'years_of_experience',
            'commitment',
            'available_for',
            'drivers_license',
            'cpr_first_aid',
            'vaccinations',
            'work_legally_in_us',
        ];

        $completed = collect($fields)
            ->filter(function (string $field) use ($candidate): bool {
                $value = $candidate->{$field};

                if (is_array($value)) {
                    return count($value) > 0;
                }

                return filled($value);
            })
            ->count();

        return (int) round(($completed / count($fields)) * 100);
    }
}
