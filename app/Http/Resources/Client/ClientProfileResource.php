<?php

namespace App\Http\Resources\Client;

use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientProfileResource extends JsonResource
{
    public function __construct(Client $client, private readonly User $user)
    {
        parent::__construct($client);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user->id,
            'client_id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'image_url' => $this->image_url,
            'locations' => $this->resolveLocations(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function resolveLocations(): array
    {
        $ids = collect($this->location_id ?? [])->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Location::whereIn('id', $ids)
            ->get(['id', 'location'])
            ->map(fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->location,
            ])
            ->all();
    }
}
