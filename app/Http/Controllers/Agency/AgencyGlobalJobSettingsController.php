<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyGlobalJobSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AgencyGlobalJobSettingsController extends Controller
{
    public function getGlobalJobSettings()
    {
        $agency_id = auth('api')->user()->agency_id;

        $settings = AgencyGlobalJobSetting::where('agency_id', $agency_id)->first();

        return response()->json([
            'status' => true,
            'data' => $settings ? $settings->settings : $this->defaultSettingsStructure(),
        ]);
    }

    public function updateGlobalJobSettings(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',

            // General & Fees
            'settings.job_types' => 'nullable|array',
            'settings.short_term_booking_fee' => 'nullable|numeric',
            'settings.long_term_booking_fee' => 'nullable|numeric',

            // Short Term Job Rules (Booleans)
            'settings.short_term.need_admin_approval' => 'boolean',
            'settings.short_term.send_email_to_candidate' => 'boolean',
            'settings.short_term.send_email_to_client' => 'boolean',
            'settings.short_term.make_reason_mandatory' => 'boolean',
            'settings.short_term.no_reason_if_client_cancels' => 'boolean',
            'settings.short_term.unassign_on_client_cancel' => 'boolean',
            'settings.short_term.send_email_on_unassign' => 'boolean',
            'settings.short_term.include_cancel_reason_in_email' => 'boolean',
            'settings.short_term.display_canceled_on_calendar' => 'boolean',
            'settings.short_term.show_assigned_at_top' => 'boolean',
            'settings.short_term.check_already_booked' => 'boolean',
            'settings.short_term.check_time_off' => 'boolean',
            'settings.short_term.check_not_available' => 'boolean',
            'settings.short_term.check_tags_dont_match' => 'boolean',
            'settings.short_term.check_do_not_match_list' => 'boolean',
            'settings.short_term.disable_distance_search' => 'boolean',

            // Long Term Job Rules (Booleans)
            'settings.long_term.manage_payment_records' => 'boolean',
            'settings.long_term.hide_from_job_board' => 'boolean',
            'settings.long_term.set_table_view_default' => 'boolean',
            'settings.long_term.hide_closed_jobs_admin' => 'boolean',
            'settings.long_term.show_analytics_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $safeSettings = [
            'job_types' => $request->input('settings.job_types', []),
            'short_term_booking_fee' => $request->input('settings.short_term_booking_fee'),
            'long_term_booking_fee' => $request->input('settings.long_term_booking_fee'),

            'short_term' => [
                'need_admin_approval' => (bool) $request->input('settings.short_term.need_admin_approval'),
                'send_email_to_candidate' => (bool) $request->input('settings.short_term.send_email_to_candidate'),
                'send_email_to_client' => (bool) $request->input('settings.short_term.send_email_to_client'),
                'make_reason_mandatory' => (bool) $request->input('settings.short_term.make_reason_mandatory'),
                'no_reason_if_client_cancels' => (bool) $request->input('settings.short_term.no_reason_if_client_cancels'),
                'unassign_on_client_cancel' => (bool) $request->input('settings.short_term.unassign_on_client_cancel'),
                'send_email_on_unassign' => (bool) $request->input('settings.short_term.send_email_on_unassign'),
                'include_cancel_reason_in_email' => (bool) $request->input('settings.short_term.include_cancel_reason_in_email'),
                'display_canceled_on_calendar' => (bool) $request->input('settings.short_term.display_canceled_on_calendar'),
                'show_assigned_at_top' => (bool) $request->input('settings.short_term.show_assigned_at_top'),
                'check_already_booked' => (bool) $request->input('settings.short_term.check_already_booked'),
                'check_time_off' => (bool) $request->input('settings.short_term.check_time_off'),
                'check_not_available' => (bool) $request->input('settings.short_term.check_not_available'),
                'check_tags_dont_match' => (bool) $request->input('settings.short_term.check_tags_dont_match'),
                'check_do_not_match_list' => (bool) $request->input('settings.short_term.check_do_not_match_list'),
                'disable_distance_search' => (bool) $request->input('settings.short_term.disable_distance_search'),
            ],

            'long_term' => [
                'manage_payment_records' => (bool) $request->input('settings.long_term.manage_payment_records'),
                'hide_from_job_board' => (bool) $request->input('settings.long_term.hide_from_job_board'),
                'set_table_view_default' => (bool) $request->input('settings.long_term.set_table_view_default'),
                'hide_closed_jobs_admin' => (bool) $request->input('settings.long_term.hide_closed_jobs_admin'),
                'show_analytics_default' => (bool) $request->input('settings.long_term.show_analytics_default'),
            ],
        ];

        AgencyGlobalJobSetting::updateOrCreate(
            ['agency_id' => $agency_id],
            ['settings' => $safeSettings]
        );

        return response()->json([
            'status' => true,
            'message' => 'Global job settings updated successfully',
        ]);
    }

    private function defaultSettingsStructure()
    {
        return [
            'job_types' => [],
            'short_term_booking_fee' => 0,
            'long_term_booking_fee' => 0,
            'short_term' => [],
            'long_term' => [],
        ];
    }
}
