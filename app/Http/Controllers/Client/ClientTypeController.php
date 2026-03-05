<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientTypeController extends Controller
{
    public function index()
    {
        return ClientType::where('agency_id', auth('api')->user()->agency_id)->get();
    }

    public function show(ClientType $clientType)
    {
        if ($clientType->agency_id !== auth('api')->user()->agency_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($clientType);
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
            'status' => 'required|in:0,1',
        ]);

        $clientType = ClientType::create([
            'agency_id' => $agencyId,
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json($clientType, 201);
    }

    public function update(Request $request, ClientType $clientType)
    {
        if ($clientType->agency_id !== auth('api')->user()->agency_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_types')->ignore($clientType->id)->where(fn($query) => $query->where('agency_id', $clientType->agency_id))
            ],
            'status' => 'required|in:0,1',
        ]);

        $clientType->update($request->only('name', 'status'));

        return response()->json($clientType);
    }

    public function destroy(ClientType $clientType)
    {
        if ($clientType->agency_id !== auth('api')->user()->agency_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $clientType->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
