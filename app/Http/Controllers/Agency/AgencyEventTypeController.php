<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgencyEventTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $search = $request->query('search');
        $type = $request->query('type');

        if (! is_null($type)) {
            $type = is_string($type) ? strtolower(trim($type)) : $type;
            if (! in_array($type, ['candidate', 'client'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid type. Allowed: candidate, client.',
                ], 422);
            }
        }

        $agencyEventTypes = EventType::where('agency_id', auth('api')->user()->agency_id)
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->latest()
            ->paginate($perPage);

        $agencyEventTypes->appends(['search' => $search, 'per_page' => $perPage, 'type' => $type]);

        return response()->json([
            'status' => true,
            'message' => 'Agency event types retrieved successfully',
            'data' => $agencyEventTypes->items(),
            'meta' => [
                'current_page' => $agencyEventTypes->currentPage(),
                'last_page' => $agencyEventTypes->lastPage(),
                'per_page' => $agencyEventTypes->perPage(),
                'total' => $agencyEventTypes->total(),
                'next_page_url' => $agencyEventTypes->nextPageUrl(),
                'prev_page_url' => $agencyEventTypes->previousPageUrl(),
            ],
        ], 200);
    }

    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $agencyEventType = EventType::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $agencyEventType) {
            return response()->json([
                'status' => false,
                'message' => 'Agency event type not found or unauthorized',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $agencyEventType,
        ], 200);
    }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        // If frontend doesn't send it, default to candidate.
        $requestType = $request->input('type', 'candidate');
        $requestType = is_string($requestType) ? strtolower(trim($requestType)) : $requestType;
        $request->merge(['type' => $requestType]);

        $request->validate([
            'name' => 'nullable|string|max:255|required_without:names',
            'names' => 'nullable|array|min:1|required_without:name',
            'names.*' => 'required|string|max:255|distinct',
            'type' => ['required', 'string', Rule::in(['candidate', 'client'])],
            'status' => 'nullable|in:0,1',
        ]);

        $names = $request->filled('names') ? $request->input('names') : [$request->input('name')];
        $names = collect($names)
            ->filter(fn ($value) => ! is_null($value) && $value !== '')
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide at least one name using "name" or "names".',
            ], 422);
        }

        $existingNames = EventType::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->pluck('name')
            ->toArray();

        if (! empty($existingNames)) {
            return response()->json([
                'status' => false,
                'message' => 'Some names already exist.',
                'duplicates' => $existingNames,
            ], 422);
        }

        $type = $request->input('type');
        $status = $request->status ?? 1;
        $now = now();
        $rows = $names->map(fn ($name) => [
            'agency_id' => $agencyId,
            'name' => $name,
            'type' => $type,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        EventType::insert($rows);

        $created = EventType::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->orderBy('id')
            ->get()
            ->values();

        if (count($created) === 1) {
            return response()->json($created[0], 201);
        }

        return response()->json([
            'status' => true,
            'message' => 'Agency event types created successfully',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $agencyEventType = EventType::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $agencyEventType) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('event_types')->ignore($agencyEventType->id)->where(fn ($query) => $query->where('agency_id', $agencyId)),
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $agencyEventType->update($request->only('name', 'status'));

        return response()->json([
            'status' => true,
            'message' => 'Agency event type updated successfully',
            'data' => $agencyEventType,
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
            ->map(fn ($item) => [...$item, 'name' => trim($item['name'])])
            ->values();

        if ($updates->contains(fn ($item) => $item['name'] === '')) {
            return response()->json([
                'status' => false,
                'message' => 'Name cannot be empty.',
            ], 422);
        }

        $ids = $updates->pluck('id')->all();
        $names = $updates->pluck('name')->all();

        $agencyEventTypes = EventType::where('agency_id', $agencyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($agencyEventTypes->count() !== count($ids)) {
            $missingIds = collect($ids)->diff($agencyEventTypes->keys())->values()->all();

            return response()->json([
                'status' => false,
                'message' => 'Some agency event types were not found or unauthorized.',
                'missing_ids' => $missingIds,
            ], 404);
        }

        $nameConflicts = EventType::where('agency_id', $agencyId)
            ->whereIn('name', $names)
            ->whereNotIn('id', $ids)
            ->pluck('name')
            ->toArray();

        if (! empty($nameConflicts)) {
            return response()->json([
                'status' => false,
                'message' => 'Some names already exist.',
                'duplicates' => $nameConflicts,
            ], 422);
        }

        $updated = DB::transaction(function () use ($updates, $agencyEventTypes) {
            $result = [];

            foreach ($updates as $item) {
                $agencyEventType = $agencyEventTypes[$item['id']];

                $payload = ['name' => $item['name']];
                if (array_key_exists('status', $item)) {
                    $payload['status'] = $item['status'];
                }

                $agencyEventType->update($payload);
                $result[] = $agencyEventType->fresh();
            }

            return $result;
        });

        return response()->json([
            'status' => true,
            'message' => 'Agency event types updated successfully',
            'data' => $updated,
        ], 200);
    }

    public function destroy($id)
    {
        $agencyEventType = EventType::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->first();

        if (! $agencyEventType) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $agencyEventType->delete();

        return response()->json(['message' => 'Agency event type deleted successfully']);
    }
}
