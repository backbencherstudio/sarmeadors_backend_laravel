<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ClientLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientLocationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $clientLocations = ClientLocation::where('agency_id', auth('api')->user()->agency_id)->latest()->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Client locations retrieved successfully',
            'data' => $clientLocations->items(),
            'meta' => [
                'current_page' => $clientLocations->currentPage(),
                'last_page' => $clientLocations->lastPage(),
                'per_page' => $clientLocations->perPage(),
                'total' => $clientLocations->total(),
                'next_page_url' => $clientLocations->nextPageUrl(),
                'prev_page_url' => $clientLocations->previousPageUrl(),
            ]
        ], 200);
    }

    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $clientLocation = ClientLocation::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$clientLocation) {
            return response()->json([
                'status' => false,
                'message' => 'Client location not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $clientLocation
        ], 200);
    }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'location' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_locations')->where(fn($query) => $query->where('agency_id', $agencyId))
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $clientLocation = ClientLocation::create([
            'agency_id' => $agencyId,
            'location' => $request->location,
            'status' => $request->status ?? 1,
        ]);

        return response()->json($clientLocation, 201);
    }

    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $clientLocation = ClientLocation::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$clientLocation) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $request->validate([
            'location' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_locations')->ignore($clientLocation->id)->where(fn($query) => $query->where('agency_id', $agencyId))
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $clientLocation->update($request->only('location', 'status'));

        return response()->json([
            'status' => true,
            'message' => 'Client location updated successfully',
            'data' => $clientLocation
        ], 200);
    }

    public function destroy($id)
    {
        $clientLocation = ClientLocation::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->first();

        if (!$clientLocation) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $clientLocation->delete();

        return response()->json(['message' => 'Client location deleted successfully']);
    }
}
