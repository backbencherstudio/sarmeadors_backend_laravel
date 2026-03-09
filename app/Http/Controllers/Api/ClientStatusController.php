<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClientStatusController extends Controller
{
    public function index()
    {
        $authUser = auth('api')->user();

        $query = ClientStatus::with('agency:id,first_name')->orderBy('serial');
        $query->where('agency_id', $authUser->agency_id);

        return response()->json([
            'status' => true,
            'message' => 'Client statuses retrieved successfully.',
            'data'   => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        $request->validate([
            'name'  => 'required|string|max:100|unique:client_statuses,name,NULL,id,agency_id,' . $authUser->agency_id,
            'color' => 'nullable|string|max:50',
        ]);

        $lastSerial = ClientStatus::where('agency_id', $authUser->agency_id)
                        ->max('serial');

        $nextSerial = $lastSerial ? $lastSerial + 1 : 1;

        $status = ClientStatus::create([
            'agency_id' => $authUser->agency_id,
            'name'      => $request->name,
            'color'     => $request->color,
            'serial'    => $nextSerial,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Client status created successfully.',
            'data'    => $status,
        ]);
    }

    public function edit($id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Client status retrieved successfully.',
            'data'   => $status,
        ]);
    }

    public function update(Request $request, $id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $request->validate([
            'name'   => 'required|string|max:255',
            'color'  => 'nullable|string|max:50',
        ]);

        $status->update($request->only('name', 'color'));

        return response()->json([
            'status'  => true,
            'message' => 'Client status updated successfully.',
            'data'    => $status,
        ]);
    }

    public function serial(Request $request, $id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $request->validate([
            'serial' => [
                'required',
                'integer',
                Rule::exists('client_statuses', 'serial')->where(function ($query) use ($authUser) {
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

        $otherStatus = ClientStatus::where('agency_id', $authUser->agency_id)
                            ->where('serial', $newSerial)
                            ->first();

        if ($otherStatus) {
            $otherStatus->update(['serial' => $status->serial]);
        }

        $status->update(['serial' => $newSerial]);

        return response()->json([
            'status'  => true,
            'message' => 'Client status serial updated successfully.',
            'serial'  => $status->serial,
        ]);
    }

    public function destroy($id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if ($status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $status->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Client status deleted successfully.',
        ]);
    }
}
