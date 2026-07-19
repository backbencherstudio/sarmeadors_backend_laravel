<?php

namespace App\Http\Resources\Candidate;

use App\Models\Client;
use App\Models\Location;
use App\Models\LongTermJobReview;
use App\Models\ShortTermJobReview;
use App\Services\FormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $client = $this->resource;
        $agencyId = $request->current_agency->id;
        $builder = app(FormBuilderService::class);
        $submission = $builder->registrationSubmissionFor('client', $client->id, $agencyId);

        return [
            'id' => $client->id,
            'name' => trim($client->first_name.' '.$client->last_name),
            'image_url' => $client->image_url,
            'area' => $this->resolveArea($client),
            'rating' => $this->ratingSummary($client, $agencyId),
            'form_id' => $submission?->form_id,
            'form_name' => $submission?->form?->name,
            'blocks' => $builder->profileBlocks('client', $client, $submission),
        ];
    }

    /**
     * Resolve the client's configured location names into a single area label
     * (e.g. "DC Metro Area"). Returns null when no locations are set.
     */
    private function resolveArea(Client $client): ?string
    {
        $locationIds = collect($client->location_id ?? [])->filter()->values();

        if ($locationIds->isEmpty()) {
            return null;
        }

        $names = Location::whereIn('id', $locationIds)
            ->pluck('location')
            ->filter()
            ->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * @return array{average: float|null, count: int}
     */
    private function ratingSummary(Client $client, int $agencyId): array
    {
        $ratings = ShortTermJobReview::where('client_id', $client->id)
            ->where('agency_id', $agencyId)
            ->pluck('rating')
            ->merge(
                LongTermJobReview::where('client_id', $client->id)
                    ->where('agency_id', $agencyId)
                    ->pluck('rating')
            );

        return [
            'average' => $ratings->isEmpty() ? null : round($ratings->avg(), 1),
            'count' => $ratings->count(),
        ];
    }
}
