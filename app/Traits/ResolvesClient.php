<?php

namespace App\Traits;

use App\Models\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Resolves the Client record tied to the authenticated user within the
 * agency resolved by the subdomain middleware (`$request->current_agency`).
 */
trait ResolvesClient
{
    protected function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    /**
     * Resolve the current client or abort with a 404 handled globally.
     *
     * @throws ModelNotFoundException
     */
    protected function currentClientOrFail(Request $request): Client
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            throw new ModelNotFoundException('Client profile not found.');
        }

        return $client;
    }
}
