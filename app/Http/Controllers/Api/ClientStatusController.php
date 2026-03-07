<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientStatus;
use Illuminate\Http\Request;

class ClientStatusController extends Controller
{
    public function index()
    {
        $authUser = auth('api')->user();

        $query = ClientStatus::query()->orderBy('serial');

        if (!$authUser->hasRole('super_admin')) {
            $query->where('agency_id', $authUser->agency_id);
        }

        return response()->json([
            'status' => true,
            'data'   => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        $request->validate([
            'name'   => 'required|string|max:100',
            'color'  => 'nullable|string|max:50',
            'serial' => 'nullable|integer',
        ]);

        $status = ClientStatus::create([
            'agency_id' => $authUser->hasRole('super_admin')
                            ? $request->agency_id
                            : $authUser->agency_id,
            'name'   => $request->name,
            'color'  => $request->color,
            'serial' => $request->serial ?? 0,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Client status created successfully.',
            'data'    => $status,
        ]);
    }

    public function show($id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if (!$authUser->hasRole('super_admin') &&
            $status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        return response()->json([
            'status' => true,
            'data'   => $status,
        ]);
    }

    public function update(Request $request, $id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if (!$authUser->hasRole('super_admin') &&
            $status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $request->validate([
            'name'   => 'required|string|max:255',
            'color'  => 'nullable|string|max:50',
            'serial' => 'nullable|integer',
        ]);

        $status->update($request->only('name', 'color', 'serial'));

        return response()->json([
            'status'  => true,
            'message' => 'Client status updated successfully.',
            'data'    => $status,
        ]);
    }

    public function destroy($id)
    {
        $authUser = auth('api')->user();

        $status = ClientStatus::findOrFail($id);

        if (!$authUser->hasRole('super_admin') &&
            $status->agency_id !== $authUser->agency_id) {
            abort(403);
        }

        $status->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Client status deleted successfully.',
        ]);
    }
}
