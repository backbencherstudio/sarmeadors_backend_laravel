<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyBusinessHour;
use App\Models\AgencyHoliday;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function getBusinessDetails()
    {
        $agency_id = auth('api')->user()->agency_id;

        $masterCommonHolidays = [
            "New Years Day",
            "Martin Luther King JL Day",
            "Presidents' Day",
            "Memorial Day",
            "Juneteenth National Independence Day",
            "Labor Day",
            "Columbus Day",
            "Veterans Day",
            "Thanksgiving Day",
            "Independence Day",
            "Christmas Day"
        ];

        $locations = Location::where('agency_id', $agency_id)
            ->pluck('location', 'id');

        $savedHolidays = AgencyHoliday::where('agency_id', $agency_id)->get();

        $savedHolidays->transform(function ($holiday) use ($locations) {
            $details = [];
            if (!empty($holiday->location_ids)) {
                foreach ($holiday->location_ids as $id) {
                    $details[] = [
                        'id' => $id,
                        'location' => $locations[$id] ?? 'Unknown Location'
                    ];
                }
            }
            $holiday->location_details = $details;
            return $holiday;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'agency' => Agency::find($agency_id, ['id', 'currency', 'countries']),
                'common_holidays_master' => $masterCommonHolidays,
                'saved_holidays' => $savedHolidays,
                'all_agency_locations' => $locations->map(fn($location, $id) => ['id' => $id, 'location' => $location])->values(),
                'business_hours' => AgencyBusinessHour::where('agency_id', $agency_id)->get(),
            ]
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'holiday_name' => 'required|string|max:255',
            'date'         => 'required_if:is_common,false|nullable|date',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer',
            'is_common'    => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('location_ids') && !empty($request->location_ids)) {

            $validLocationsCount = Location::where('agency_id', $agency_id)
                ->whereIn('id', $request->location_ids)
                ->count();

            if ($validLocationsCount !== count($request->location_ids)) {
                return response()->json([
                    'status' => false,
                    'message' => 'One or more Location IDs are invalid or do not belong to your agency.'
                ], 403);
            }
        }

        $holiday = AgencyHoliday::create([
            'agency_id'    => $agency_id,
            'holiday_name' => $request->holiday_name,
            'date'         => $request->is_common ? null : $request->date,
            'location_ids' => $request->location_ids,
            'is_common'    => $request->is_common,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Holiday added successfully',
            'data' => $holiday
        ], 201);
    }

    public function destroyHoliday($id)
    {
        $agency_id = auth('api')->user()->agency_id;

        $holiday = AgencyHoliday::where('id', $id)
            ->where('agency_id', $agency_id)
            ->first();

        if (!$holiday) {
            return response()->json([
                'status' => false,
                'message' => 'Holiday not found or you do not have permission to delete it.'
            ], 404);
        }

        $holiday->delete();

        return response()->json([
            'status' => true,
            'message' => 'Holiday deleted successfully'
        ]);
    }

    public function updateBusinessSettings(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'currency'  => 'required|string|max:10',
            'countries' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        Agency::where('id', $agency_id)->update([
            'currency'  => $request->currency,
            'countries' => $request->countries,
        ]);

        return response()->json(['status' => true, 'message' => 'Settings updated successfully']);
    }

    public function updateBusinessHours(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'business_hours'            => 'required|array',
            'business_hours.*.day'      => 'required|string',
            'business_hours.*.start_time' => 'nullable',
            'business_hours.*.end_time'  => 'nullable',
            'business_hours.*.is_open'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        foreach ($request->business_hours as $hour) {
            AgencyBusinessHour::where('agency_id', $agency_id)
                ->where('day', $hour['day'])
                ->update([
                    'start_time' => $hour['start_time'],
                    'end_time'   => $hour['end_time'],
                    'is_open'    => $hour['is_open'],
                ]);
        }

        return response()->json(['status' => true, 'message' => 'Business hours updated successfully']);
    }

    public function toggleBusinessHourStatus(Request $request, $id)
    {
        $agency_id = auth('api')->user()->agency_id;

        $businessHour = AgencyBusinessHour::where('id', $id)
            ->where('agency_id', $agency_id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'is_open' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $businessHour->update([
            'is_open' => $request->is_open
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status updated for ' . $businessHour->day
        ]);
    }
}
