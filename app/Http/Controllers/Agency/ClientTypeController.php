<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\ClientType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $clientTypes = ClientType::where('agency_id', auth('api')->user()->agency_id)->latest()->paginate($perPage);

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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_types')->where(fn($query) => $query->where('agency_id', $agencyId))
            ],
            'status' => 'nullable|in:0,1',
        ]);

        $clientType = ClientType::create([
            'agency_id' => $agencyId,
            'name' => $request->name,
            'status' => $request->status ?? 1,
        ]);

        return response()->json($clientType, 201);
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
