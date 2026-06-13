<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    public function index()
    {
        $agency_id = auth('api')->user()->agency_id;
        $services = Service::where('agency_id', $agency_id)->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved successfully',
            'data' => $services,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_name' => 'required|string|max:255',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::create([
            'agency_id' => auth('api')->user()->agency_id,
            'service_name' => $request->service_name,
            'status' => $request->has('status') ? $request->status : true,
        ]);

        return response()->json(['message' => 'Service Created', 'data' => $service], 201);
    }

    public function show($id)
    {
        $agency_id = auth('api')->user()->agency_id;
        $service = Service::where('id', $id)->where('agency_id', $agency_id)->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        return response()->json(['data' => $service]);
    }

    public function update(Request $request, $id)
    {
        $agency_id = auth('api')->user()->agency_id;
        $service = Service::where('id', $id)->where('agency_id', $agency_id)->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        $request->validate([
            'service_name' => 'required|string',
            'status' => 'nullable|boolean',
        ]);

        $service->update([
            'service_name' => $request->service_name,
            'status' => $request->has('status') ? $request->status : $service->status,
        ]);

        return response()->json(['message' => 'Service updated', 'data' => $service]);
    }

    public function changeStatus(Request $request, $id)
    {
        $agency_id = auth('api')->user()->agency_id;
        $service = Service::where('id', $id)->where('agency_id', $agency_id)->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        $request->validate([
            'status' => 'required|boolean',
        ]);

        $service->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'current_status' => $service->status,
        ]);
    }

    public function destroy($id)
    {
        $agency_id = auth('api')->user()->agency_id;
        $service = Service::where('id', $id)->where('agency_id', $agency_id)->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        $service->delete();

        return response()->json(['message' => 'Service deleted successfully']);
    }
}
