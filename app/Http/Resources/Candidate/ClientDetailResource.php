<?php

namespace App\Http\Resources\Candidate;

use App\Models\Client;
use App\Models\LongTermJobReview;
use App\Models\ShortTermJobReview;
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

        return [
            'id' => $client->id,
            'name' => trim($client->first_name.' '.$client->last_name),
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'mobile' => $client->mobile,
            'image_url' => $client->image_url,
            'rating' => $this->ratingSummary($client, $agencyId),
        ];
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
