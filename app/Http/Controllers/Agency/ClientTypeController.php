<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ClientType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $search = $request->query('search');

        $clientTypes = ClientType::where('agency_id', auth('api')->user()->agency_id)
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })->latest()->paginate($perPage);

        $clientTypes->appends(['search' => $search, 'per_page' => $perPage]);

        return response()->json([
            'status' => true,
            'message' => 'Client types retrieved successfully',
            'data' => $clientTypes->items(),
            'meta' => [
                'current_page' => $clientTypes->currentPage(),
                'last_page' => $clientTypes->lastPage(),
                'per_page' => $clientTypes->perPage(),
                'total' => $clientTypes->total(),
                'next_page_url' => $clientTypes->nextPageUrl(),
                'prev_page_url' => $clientTypes->previousPageUrl(),
            ]
        ], 200);
    }

    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $clientType = ClientType::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$clientType) {
            return response()->json([
                'status' => false,
                'message' => 'Client type not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $clientType
        ], 200);
    }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'name' => 'nullable|string|max:255|required_without:names',
            'names' => 'nullable|array|min:1|required_without:name',
            'names.*' => 'required|string|max:255|distinct',
            'status' => 'nullable|in:0,1',
        ]);

        $names = $request->filled('names') ? $request->input('names') : [$request->input('name')];
        $names = collect($names)
            ->filter(fn($value) => !is_null($value) && $value !== '')
            ->map(fn($value) => trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide at least one name using "name" or "names".'
            ], 422);
        }

        $existingNames = ClientType::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->pluck('name')
            ->toArray();

        if (!empty($existingNames)) {
            return response()->json([
                'status' => false,
                'message' => 'Some names already exist.',
                'duplicates' => $existingNames
            ], 422);
        }

        $status = $request->status ?? 1;
        $now = now();
        $rows = $names->map(fn($name) => [
            'agency_id' => $agencyId,
            'name' => $name,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        ClientType::insert($rows);

        $created = ClientType::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->orderBy('id')
            ->get()
            ->values();

        if (count($created) === 1) {
            return response()->json($created[0], 201);
        }

        return response()->json([
            'status' => true,
            'message' => 'Client types created successfully',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $clientType = ClientType::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$clientType) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_types')->ignore($clientType->id)->where(fn($query) => $query->where('agency_id', $agencyId))
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $clientType->update($request->only('name', 'status'));

        return response()->json([
            'status' => true,
            'message' => 'Client type updated successfully',
            'data' => $clientType
        ], 200);
    }

    public function bulkUpdate(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|distinct',
            'updates.*.name' => 'required|string|max:255|distinct',
            'updates.*.status' => 'nullable|in:0,1',
        ]);

        $updates = collect($request->input('updates'))
            ->map(fn($item) => [...$item, 'name' => trim($item['name'])])
            ->values();

        if ($updates->contains(fn($item) => $item['name'] === '')) {
            return response()->json([
                'status' => false,
                'message' => 'Name cannot be empty.'
            ], 422);
        }

        $ids = $updates->pluck('id')->all();
        $names = $updates->pluck('name')->all();

        $clientTypes = ClientType::where('agency_id', $agencyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($clientTypes->count() !== count($ids)) {
            $missingIds = collect($ids)->diff($clientTypes->keys())->values()->all();

            return response()->json([
                'status' => false,
                'message' => 'Some client types were not found or unauthorized.',
                'missing_ids' => $missingIds
            ], 404);
        }

        $nameConflicts = ClientType::where('agency_id', $agencyId)
            ->whereIn('name', $names)
            ->whereNotIn('id', $ids)
            ->pluck('name')
            ->toArray();

        if (!empty($nameConflicts)) {
            return response()->json([
                'status' => false,
                'message' => 'Some names already exist.',
                'duplicates' => $nameConflicts
            ], 422);
        }

        $updated = DB::transaction(function () use ($updates, $clientTypes) {
            $result = [];

            foreach ($updates as $item) {
                $clientType = $clientTypes[$item['id']];

                $payload = ['name' => $item['name']];
                if (array_key_exists('status', $item)) {
                    $payload['status'] = $item['status'];
                }

                $clientType->update($payload);
                $result[] = $clientType->fresh();
            }

            return $result;
        });

        return response()->json([
            'status' => true,
            'message' => 'Client types updated successfully',
            'data' => $updated
        ], 200);
    }

    public function destroy($id)
    {
        $clientType = ClientType::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->first();

        if (!$clientType) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $clientType->delete();

        return response()->json(['message' => 'Client type deleted successfully']);
    }
}
