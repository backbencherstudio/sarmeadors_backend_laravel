<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth('api')->user();

        $query = Status::with('agency:id,name')->orderBy('serial');
        $query->where('agency_id', $authUser->agency_id);

        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        return response()->json([
            'status' => true,
            'message' => 'Client statuses retrieved successfully.',
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        $request->validate([
            'type' => 'required|in:client,candidate|max:50',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('statuses')->where(function ($query) use ($authUser, $request) {
                    return $query->where('agency_id', $authUser->agency_id)
                        ->where('type', $request->type);
                }),
            ],
            'color' => 'required|string|max:50',
        ]);

        $lastSerial = Status::where('agency_id', $authUser->agency_id)
            ->max('serial');

        $nextSerial = $lastSerial ? $lastSerial + 1 : 1;

        $status = Status::create([
            'agency_id' => $authUser->agency_id,
            'name' => $request->name,
            'color' => $request->color,
            'serial' => $nextSerial,
            'type' => $request->type,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Client status created successfully.',
            'data' => $status,
        ]);
    }

    public function edit($id)
    {
        $authUser = auth('api')->user();

        $status = Status::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Client status retrieved successfully.',
            'data' => $status,
        ]);
    }

    public function update(Request $request, $id)
    {
        $authUser = auth('api')->user();

        $status = Status::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('statuses')->ignore($status->id)->where(function ($query) use ($authUser, $status) {
                    return $query->where('agency_id', $authUser->agency_id)
                        ->where('type', $status->type);
                }),
            ],
            'color' => 'required|string|max:50',
        ]);

        $status->update($request->only('name', 'color'));

        return response()->json([
            'status' => true,
            'message' => 'Client status updated successfully.',
            'data' => $status,
        ]);
    }

    public function serial(Request $request, $id)
    {
        $authUser = auth('api')->user();

        $status = Status::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $request->validate([
            'serial' => [
                'required',
                'integer',
                Rule::exists('statuses', 'serial')->where(function ($query) use ($authUser) {
                    $query->where('agency_id', $authUser->agency_id);
                }),
            ],
        ]);

        $newSerial = $request->serial;

        if ($status->serial == $newSerial) {
            return response()->json([
                'status' => true,
                'message' => 'Serial already in this position.',
            ]);
        }

        $otherStatus = Status::where('agency_id', $authUser->agency_id)
            ->where('serial', $newSerial)
            ->first();

        if ($otherStatus) {
            $otherStatus->update(['serial' => $status->serial]);
        }

        $status->update(['serial' => $newSerial]);

        return response()->json([
            'status' => true,
            'message' => 'Client status serial updated successfully.',
            'serial' => $status->serial,
        ]);
    }

    public function destroy($id)
    {
        $authUser = auth('api')->user();

        $status = Status::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $status->delete();

        return response()->json([
            'status' => true,
            'message' => 'Client status deleted successfully.',
        ]);
    }

    public function storeReasons(Request $request)
    {
        $request->validate([
            'statuses' => 'required|array',
            'statuses.*.id' => 'required|exists:statuses,id',
            'statuses.*.reason' => 'required|max:1000',
        ]);

        $agencyId = auth('api')->user()->agency_id;

        DB::beginTransaction();

        try {

            foreach ($request->statuses as $statusData) {

                Status::where('id', $statusData['id'])
                    ->where('agency_id', $agencyId)
                    ->update([
                        'any_reason' => 1,
                        'reason' => $statusData['reason'],
                    ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Reasons stored successfully',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
