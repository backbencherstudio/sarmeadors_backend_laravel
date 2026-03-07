<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ClientTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientTagController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');

        $clientTag = ClientTag::where('agency_id', auth('api')->user()->agency_id)
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })
            ->latest()
            ->paginate($perPage);

        $clientTag->appends(['search' => $search, 'per_page' => $perPage]);

        return response()->json([
            'status' => true,
            'message' => 'Client tags retrieved successfully',
            'data' => $clientTag->items(),
            'meta' => [
                'current_page' => $clientTag->currentPage(),
                'last_page' => $clientTag->lastPage(),
                'per_page' => $clientTag->perPage(),
                'total' => $clientTag->total(),
                'next_page_url' => $clientTag->nextPageUrl(),
                'prev_page_url' => $clientTag->previousPageUrl(),
            ]
        ], 200);
    }

    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $clientTag = ClientTag::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$clientTag) {
            return response()->json([
                'status' => false,
                'message' => 'Client tag not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $clientTag
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
                'message' => 'Please provide at least one tag using "name" or "names".'
            ], 422);
        }

        $existingNames = ClientTag::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->pluck('name')
            ->toArray();

        if (!empty($existingNames)) {
            return response()->json([
                'status' => false,
                'message' => 'Some tag already exist.',
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

        ClientTag::insert($rows);

        $created = ClientTag::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->orderBy('id')
            ->get()
            ->values();

        if (count($created) === 1) {
            return response()->json($created[0], 201);
        }

        return response()->json([
            'status' => true,
            'message' => 'Client tag created successfully',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $clientTag = ClientTag::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (!$clientTag) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_check_lists')->ignore($clientTag->id)->where(fn($query) => $query->where('agency_id', $agencyId))
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $clientTag->update($request->only('name', 'status'));

        return response()->json([
            'status' => true,
            'message' => 'Client tag updated successfully',
            'data' => $clientTag
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

        $clientTags = ClientTag::where('agency_id', $agencyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($clientTags->count() !== count($ids)) {
            $missingIds = collect($ids)->diff($clientTags->keys())->values()->all();

            return response()->json([
                'status' => false,
                'message' => 'Some client tag were not found or unauthorized.',
                'missing_ids' => $missingIds
            ], 404);
        }

        $nameConflicts = ClientTag::where('agency_id', $agencyId)
            ->whereIn('name', $names)
            ->whereNotIn('id', $ids)
            ->pluck('name')
            ->toArray();

        if (!empty($nameConflicts)) {
            return response()->json([
                'status' => false,
                'message' => 'Some checklist already exist.',
                'duplicates' => $nameConflicts
            ], 422);
        }

        $updated = DB::transaction(function () use ($updates, $clientTags) {
            $result = [];

            foreach ($updates as $item) {
                $clientTag = $clientTags[$item['id']];

                $payload = ['name' => $item['name']];
                if (array_key_exists('status', $item)) {
                    $payload['status'] = $item['status'];
                }

                $clientTag->update($payload);
                $result[] = $clientTag->fresh();
            }

            return $result;
        });

        return response()->json([
            'status' => true,
            'message' => 'Client tag updated successfully',
            'data' => $updated
        ], 200);
    }

    public function destroy($id)
    {
        $clientTag = ClientTag::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->first();

        if (!$clientTag) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $clientTag->delete();

        return response()->json(['message' => 'Client tag deleted successfully']);
    }
}
