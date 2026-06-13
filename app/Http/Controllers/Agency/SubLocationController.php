<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\SubLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubLocationController extends Controller
{
    public function index(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $subLocations = SubLocation::with(['location:id,location'])
            ->where('agency_id', $agency_id)
            ->when($request->location_id, fn ($q) => $q->where('location_id', $request->location_id))
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sub-Locations retrieved successfully',
            'data' => $subLocations,
        ]);
    }

    public function store(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(function ($query) use ($agency_id) {
                    $query->where('agency_id', $agency_id);
                }),
            ],
            'sub_location' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $subLocation = SubLocation::create([
            'agency_id' => $agency_id,
            'location_id' => $request->location_id,
            'sub_location' => $request->sub_location,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub Location created successfully',
            'data' => $subLocation,
        ], 201);
    }

    public function show($id)
    {
        $agency_id = auth('api')->user()->agency_id;

        $subLocation = SubLocation::with(['location:id,location'])
            ->where('agency_id', $agency_id)
            ->find($id);

        if (! $subLocation) {
            return response()->json(['message' => 'Sub Location not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $subLocation]);
    }

    public function update(Request $request, $id)
    {
        $agency_id = auth('api')->user()->agency_id;

        $subLocation = SubLocation::where('agency_id', $agency_id)->find($id);

        if (! $subLocation) {
            return response()->json([
                'success' => false,
                'message' => 'Sub Location not found or unauthorized.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'location_id' => [
                'sometimes',
                'required',
                Rule::exists('locations', 'id')->where(function ($query) use ($agency_id) {
                    $query->where('agency_id', $agency_id);
                }),
            ],
            'sub_location' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $subLocation->update($request->only(['location_id', 'sub_location']));

        return response()->json([
            'success' => true,
            'message' => 'Sub Location updated successfully',
            'data' => $subLocation,
        ]);
    }

    public function destroy($id)
    {
        $agency_id = auth('api')->user()->agency_id;

        $subLocation = SubLocation::where('agency_id', $agency_id)->find($id);

        if (! $subLocation) {
            return response()->json(['message' => 'Sub Location not found or unauthorized'], 404);
        }

        $subLocation->delete();

        return response()->json(['success' => true, 'message' => 'Sub Location deleted successfully']);
    }
}
