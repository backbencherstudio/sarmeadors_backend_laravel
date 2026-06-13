<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgencyTagController extends Controller
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

        $agencyTag = Tag::where('agency_id', auth('api')->user()->agency_id)
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->latest()
            ->paginate($perPage);

        $agencyTag->appends(['search' => $search, 'per_page' => $perPage, 'type' => $type]);

        return response()->json([
            'status' => true,
            'message' => 'Agency tags retrieved successfully',
            'data' => $agencyTag->items(),
            'meta' => [
                'current_page' => $agencyTag->currentPage(),
                'last_page' => $agencyTag->lastPage(),
                'per_page' => $agencyTag->perPage(),
                'total' => $agencyTag->total(),
                'next_page_url' => $agencyTag->nextPageUrl(),
                'prev_page_url' => $agencyTag->previousPageUrl(),
            ],
        ], 200);
    }

    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $agencyTag = Tag::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $agencyTag) {
            return response()->json([
                'status' => false,
                'message' => 'Agency tag not found or unauthorized',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $agencyTag,
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
                'message' => 'Please provide at least one tag using "name" or "names".',
            ], 422);
        }

        $existingNames = Tag::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->pluck('name')
            ->toArray();

        if (! empty($existingNames)) {
            return response()->json([
                'status' => false,
                'message' => 'Some tag already exist.',
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

        Tag::insert($rows);

        $created = Tag::where('agency_id', $agencyId)
            ->whereIn('name', $names->all())
            ->orderBy('id')
            ->get()
            ->values();

        if (count($created) === 1) {
            return response()->json($created[0], 201);
        }

        return response()->json([
            'status' => true,
            'message' => 'Agency tag created successfully',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $agencyTag = Tag::where('id', $id)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $agencyTag) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('check_lists')->ignore($agencyTag->id)->where(fn ($query) => $query->where('agency_id', $agencyId)),
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $agencyTag->update($request->only('name', 'status'));

        return response()->json([
            'status' => true,
            'message' => 'Agency tag updated successfully',
            'data' => $agencyTag,
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

        $agencyTags = Tag::where('agency_id', $agencyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($agencyTags->count() !== count($ids)) {
            $missingIds = collect($ids)->diff($agencyTags->keys())->values()->all();

            return response()->json([
                'status' => false,
                'message' => 'Some agency tag were not found or unauthorized.',
                'missing_ids' => $missingIds,
            ], 404);
        }

        $nameConflicts = Tag::where('agency_id', $agencyId)
            ->whereIn('name', $names)
            ->whereNotIn('id', $ids)
            ->pluck('name')
            ->toArray();

        if (! empty($nameConflicts)) {
            return response()->json([
                'status' => false,
                'message' => 'Some checklist already exist.',
                'duplicates' => $nameConflicts,
            ], 422);
        }

        $updated = DB::transaction(function () use ($updates, $agencyTags) {
            $result = [];

            foreach ($updates as $item) {
                $agencyTag = $agencyTags[$item['id']];

                $payload = ['name' => $item['name']];
                if (array_key_exists('status', $item)) {
                    $payload['status'] = $item['status'];
                }

                $agencyTag->update($payload);
                $result[] = $agencyTag->fresh();
            }

            return $result;
        });

        return response()->json([
            'status' => true,
            'message' => 'Agency tag updated successfully',
            'data' => $updated,
        ], 200);
    }

    public function destroy($id)
    {
        $agencyTag = Tag::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->first();

        if (! $agencyTag) {
            return response()->json(['message' => 'Unauthorized or Not Found'], 403);
        }

        $agencyTag->delete();

        return response()->json(['message' => 'Agency tag deleted successfully']);
    }
}
