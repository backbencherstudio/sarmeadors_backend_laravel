<?php

namespace App\Http\Resources\Client;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Services\FormBuilderService;
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
        $builder = app(FormBuilderService::class);
        $submission = $builder->registrationSubmissionFor('candidate', $candidate->id, $request->current_agency->id);

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
            'form_id' => $submission?->form_id,
            'form_name' => $submission?->form?->name,
            'blocks' => $builder->profileBlocks('candidate', $candidate, $submission),
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
