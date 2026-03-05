<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ClientLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'location' => 'nullable|string|max:255|required_without:locations',
            'locations' => 'nullable|array|min:1|required_without:location',
            'locations.*' => 'required|string|max:255|distinct',
            'status' => 'nullable|in:0,1',
        ]);

        $locations = $request->filled('locations') ? $request->input('locations') : [$request->input('location')];
        $locations = collect($locations)
            ->filter(fn($value) => !is_null($value) && $value !== '')
            ->map(fn($value) => trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($locations->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide at least one location using "location" or "locations".'
            ], 422);
        }

        $existingLocations = ClientLocation::where('agency_id', $agencyId)
            ->whereIn('location', $locations->all())
            ->pluck('location')
            ->toArray();

        if (!empty($existingLocations)) {
            return response()->json([
                'status' => false,
                'message' => 'Some locations already exist.',
                'duplicates' => $existingLocations
            ], 422);
        }

        $status = $request->status ?? 1;
        $now = now();
        $rows = $locations->map(fn($location) => [
            'agency_id' => $agencyId,
            'location' => $location,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        ClientLocation::insert($rows);

        $created = ClientLocation::where('agency_id', $agencyId)
            ->whereIn('location', $locations->all())
            ->orderBy('id')
            ->get()
            ->values();

        if (count($created) === 1) {
            return response()->json($created[0], 201);
        }

        return response()->json([
            'status' => true,
            'message' => 'Client locations created successfully',
            'data' => $created,
        ], 201);
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

    public function bulkUpdate(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|distinct',
            'updates.*.location' => 'required|string|max:255|distinct',
            'updates.*.status' => 'nullable|in:0,1',
        ]);

        $updates = collect($request->input('updates'))
            ->map(fn($item) => [...$item, 'location' => trim($item['location'])])
            ->values();

        if ($updates->contains(fn($item) => $item['location'] === '')) {
            return response()->json([
                'status' => false,
                'message' => 'Location cannot be empty.'
            ], 422);
        }

        $ids = $updates->pluck('id')->all();
        $locations = $updates->pluck('location')->all();

        $clientLocations = ClientLocation::where('agency_id', $agencyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($clientLocations->count() !== count($ids)) {
            $missingIds = collect($ids)->diff($clientLocations->keys())->values()->all();

            return response()->json([
                'status' => false,
                'message' => 'Some client locations were not found or unauthorized.',
                'missing_ids' => $missingIds
            ], 404);
        }

        $locationConflicts = ClientLocation::where('agency_id', $agencyId)
            ->whereIn('location', $locations)
            ->whereNotIn('id', $ids)
            ->pluck('location')
            ->toArray();

        if (!empty($locationConflicts)) {
            return response()->json([
                'status' => false,
                'message' => 'Some locations already exist.',
                'duplicates' => $locationConflicts
            ], 422);
        }

        $updated = DB::transaction(function () use ($updates, $clientLocations) {
            $result = [];

            foreach ($updates as $item) {
                $clientLocation = $clientLocations[$item['id']];

                $payload = ['location' => $item['location']];
                if (array_key_exists('status', $item)) {
                    $payload['status'] = $item['status'];
                }

                $clientLocation->update($payload);
                $result[] = $clientLocation->fresh();
            }

            return $result;
        });

        return response()->json([
            'status' => true,
            'message' => 'Client locations updated successfully',
            'data' => $updated
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
