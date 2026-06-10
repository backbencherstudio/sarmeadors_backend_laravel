<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientLocationController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /client/locations?q=
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if (! $client) {
            return $this->sendError('Client profile not found.', [], 404);
        }

        $q = $request->query('q');

        $query = Location::query()->where('agency_id', $request->current_agency->id);

        if ($q) {
            $query->where('location', 'like', "%{$q}%");
        }

        $locations = $query->orderBy('location')->limit(50)->get();

        return $this->sendResponse($locations, 'Locations retrieved successfully.', 200);
    }
}
