<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientType;
use Illuminate\Http\Request;

class ClientTypeController extends Controller
{
    public function index()
    {
        return ClientType::where('agency_id', auth('api')->user()->agency_id)->get();
    }

    public function show($id)
    {
        $clientType = ClientType::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->first();

        if (!$clientType) {
            return response()->json([
                'message' => 'Client Type not found'
            ], 404);
        }

        return response()->json($clientType);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $clientType = ClientType::create([
            'agency_id' => $user->agency_id,
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json($clientType, 201);
    }

    public function update(Request $request, ClientType $clientType)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $clientType->update($request->only('name', 'status'));

        return response()->json($clientType);
    }

    public function destroy(ClientType $clientType)
    {
        if (
            auth('api')->user()->user_type !== 'agency_admin' ||
            $clientType->agency_id !== auth('api')->user()->agency_id
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $clientType->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
