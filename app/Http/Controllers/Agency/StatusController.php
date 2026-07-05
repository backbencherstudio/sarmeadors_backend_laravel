<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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
            'any_reason' => 'nullable|boolean',
            'reason' => 'required_if:any_reason,1|nullable|string|max:1000',
        ]);

        $lastSerial = Status::where('agency_id', $authUser->agency_id)
            ->max('serial');

        $nextSerial = $lastSerial ? $lastSerial + 1 : 1;

        $anyReason = $request->boolean('any_reason');

        $status = Status::create([
            'agency_id' => $authUser->agency_id,
            'name' => $request->name,
            'color' => $request->color,
            'serial' => $nextSerial,
            'type' => $request->type,
            'any_reason' => $anyReason,
            'reason' => $anyReason ? $request->reason : null,
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
            'any_reason' => 'nullable|boolean',
            'reason' => 'required_if:any_reason,1|nullable|string|max:1000',
        ]);

        $anyReason = $request->has('any_reason') ? $request->boolean('any_reason') : $status->any_reason;
        $reason = $anyReason ? ($request->input('reason', $status->reason)) : null;

        $status->update([
            'name' => $request->name,
            'color' => $request->color,
            'any_reason' => $anyReason,
            'reason' => $reason,
        ]);

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

        $oldSerial = $status->serial;
        $newSerial = $request->serial;

        if ($oldSerial == $newSerial) {
            return response()->json([
                'status' => true,
                'message' => 'Serial already in this position.',
            ]);
        }

        DB::transaction(function () use ($authUser, $oldSerial, $newSerial, $status) {
            if ($oldSerial < $newSerial) {
                Status::where('agency_id', $authUser->agency_id)
                    ->whereBetween('serial', [$oldSerial + 1, $newSerial])
                    ->decrement('serial');
            } else {
                Status::where('agency_id', $authUser->agency_id)
                    ->whereBetween('serial', [$newSerial, $oldSerial - 1])
                    ->increment('serial');
            }

            $status->update(['serial' => $newSerial]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Client status serial reordered successfully.',
            'serial' => $status->serial,
        ]);
    }

    // public function serial(Request $request, $id)
    // {
    //     $authUser = auth('api')->user();

    //     $status = Status::findOrFail($id);

    //     if ($status->agency_id !== $authUser->agency_id) {
    //         abort(403);
    //     }

    //     $request->validate([
    //         'serial' => [
    //             'required',
    //             'integer',
    //             Rule::exists('statuses', 'serial')->where(function ($query) use ($authUser) {
    //                 $query->where('agency_id', $authUser->agency_id);
    //             }),
    //         ],
    //     ]);

    //     $newSerial = $request->serial;

    //     if ($status->serial == $newSerial) {
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Serial already in this position.',
    //         ]);
    //     }

    //     $otherStatus = Status::where('agency_id', $authUser->agency_id)
    //         ->where('serial', $newSerial)
    //         ->first();

    //     if ($otherStatus) {
    //         $otherStatus->update(['serial' => $status->serial]);
    //     }

    //     $status->update(['serial' => $newSerial]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Client status serial updated successfully.',
    //         'serial' => $status->serial,
    //     ]);
    // }

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
}
