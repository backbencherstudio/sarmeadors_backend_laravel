<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyClientGlobalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientGlobalSettingsController extends Controller
{
    public function getClientGlobalSettings()
    {
        $agency_id = auth('api')->user()->agency_id;

        $saved = AgencyClientGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];

        return response()->json([
            'status' => true,
            'data' => array_replace_recursive($this->defaultSettings(), $saved),
        ]);
    }

    public function updateClientGlobalSettings(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.dashboard.table_fields' => 'nullable|array',
            'settings.dashboard.display_labels' => 'nullable|array',
            'settings.dashboard.use_admin_level_setting' => 'nullable|boolean',
            'settings.dashboard.quick_search_field' => 'nullable|string',
            'settings.dashboard.default_sort_field' => 'nullable|string',
            'settings.dashboard.last_login_retrieval_days' => 'nullable|integer',
            'settings.dashboard.show_status_statistical_breakdowns' => 'nullable|boolean',
            'settings.profile.profile_picture_scope' => 'nullable|string',
            'settings.profile.default_documents_scope' => 'nullable|string',
            'settings.profile.interview_boxes_count' => 'nullable|integer',
            'settings.profile.availability_scheduling_interval' => 'nullable|integer',
            'settings.profile.time_off_request_categories' => 'nullable|array',
            'settings.profile.min_time_block_duration' => 'nullable|integer',
            'settings.profile.max_hours_per_week' => 'nullable|integer',
            'settings.profile.time_off_request_days' => 'nullable|integer',
            'settings.profile.buffer_time_hours' => 'nullable|integer',
            'settings.booking_access.show_candidate_rate_to_client' => 'nullable|boolean',
            'settings.booking_access.show_client_rate_to_candidate' => 'nullable|boolean',
            'settings.booking_access.types_cannot_edit_shift_jobs' => 'nullable|array',
            'settings.booking_access.max_hours_for_bookings' => 'nullable|array',
            'settings.booking_access.min_hours_for_bookings' => 'nullable|array',
            'settings.registration_fee.registration_fee' => 'nullable|numeric',
            'settings.invoices.show_quick_invoice_button' => 'nullable|boolean',
            'settings.invoices.show_quick_invoice_for_shift_job' => 'nullable|boolean',
            'settings.invoices.check_partial_payment_by_default' => 'nullable|boolean',
            'settings.invoices.use_advanced_invoice_manager' => 'nullable|boolean',
            'settings.invoices.use_stripe_terminal' => 'nullable|boolean',
            'settings.documents.show_document_in_profile' => 'nullable|boolean',
            'settings.documents.admin_can_sign_document' => 'nullable|boolean',
            'settings.documents.include_audit_trail' => 'nullable|boolean',
            'settings.documents.allow_download_without_signing' => 'nullable|boolean',
            'settings.documents.update_status_when_all_signed' => 'nullable|boolean',
            'settings.schedule_availability.dont_allow_reviews' => 'nullable|boolean',
            'settings.schedule_availability.set_status_if_no_shift' => 'nullable|boolean',
            'settings.schedule_availability.show_cancelled_jobs' => 'nullable|boolean',
            'settings.schedule_availability.show_past_jobs' => 'nullable|boolean',
            'settings.schedule_availability.candidates_can_decline' => 'nullable|boolean',
            'settings.schedule_availability.days_past_load_job' => 'nullable|integer',
            'settings.schedule_availability.days_future_load_job' => 'nullable|integer',
            'settings.schedule_availability.minutes_before_clock_button' => 'nullable|integer',
            'settings.schedule_availability.clock_interval_minutes' => 'nullable|integer',
            'settings.schedule_availability.client_confirm_clock' => 'nullable|boolean',
            'settings.schedule_availability.client_confirm_clock_phone' => 'nullable|boolean',
            'settings.schedule_availability.notify_hours_before_shift' => 'nullable|array',
            'settings.schedule_availability.notify_method' => 'nullable|array',
            'settings.schedule_availability.reminder_minutes_after_clock' => 'nullable|array',
            'settings.schedule_availability.reminder_notify_method' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $safeSettings = [
            'dashboard' => [
                'table_fields' => $request->input('settings.dashboard.table_fields', []),
                'display_labels' => $request->input('settings.dashboard.display_labels', []),
                'use_admin_level_setting' => (bool) $request->input('settings.dashboard.use_admin_level_setting'),
                'quick_search_field' => $request->input('settings.dashboard.quick_search_field'),
                'default_sort_field' => $request->input('settings.dashboard.default_sort_field'),
                'last_login_retrieval_days' => $request->input('settings.dashboard.last_login_retrieval_days'),
                'show_status_statistical_breakdowns' => (bool) $request->input('settings.dashboard.show_status_statistical_breakdowns'),
            ],
            'profile' => [
                'profile_picture_scope' => $request->input('settings.profile.profile_picture_scope', 'public'),
                'default_documents_scope' => $request->input('settings.profile.default_documents_scope', 'public'),
                'interview_boxes_count' => $request->input('settings.profile.interview_boxes_count', 2),
                'availability_scheduling_interval' => $request->input('settings.profile.availability_scheduling_interval', 15),
                'time_off_request_categories' => $request->input('settings.profile.time_off_request_categories', ['Holiday', 'Sick', 'Unpaid', 'Paid']),
                'min_time_block_duration' => $request->input('settings.profile.min_time_block_duration', 2),
                'max_hours_per_week' => $request->input('settings.profile.max_hours_per_week', 2),
                'time_off_request_days' => $request->input('settings.profile.time_off_request_days', 0),
                'buffer_time_hours' => $request->input('settings.profile.buffer_time_hours', 0),
            ],
            'booking_access' => [
                'show_candidate_rate_to_client' => (bool) $request->input('settings.booking_access.show_candidate_rate_to_client'),
                'show_client_rate_to_candidate' => (bool) $request->input('settings.booking_access.show_client_rate_to_candidate'),
                'types_cannot_edit_shift_jobs' => $request->input('settings.booking_access.types_cannot_edit_shift_jobs', []),
                'max_hours_for_bookings' => $request->input('settings.booking_access.max_hours_for_bookings', []),
                'min_hours_for_bookings' => $request->input('settings.booking_access.min_hours_for_bookings', []),
            ],
            'registration_fee' => [
                'registration_fee' => $request->input('settings.registration_fee.registration_fee'),
            ],
            'invoices' => [
                'show_quick_invoice_button' => (bool) $request->input('settings.invoices.show_quick_invoice_button'),
                'show_quick_invoice_for_shift_job' => (bool) $request->input('settings.invoices.show_quick_invoice_for_shift_job'),
                'check_partial_payment_by_default' => (bool) $request->input('settings.invoices.check_partial_payment_by_default'),
                'use_advanced_invoice_manager' => (bool) $request->input('settings.invoices.use_advanced_invoice_manager'),
                'use_stripe_terminal' => (bool) $request->input('settings.invoices.use_stripe_terminal'),
            ],
            'documents' => [
                'show_document_in_profile' => (bool) $request->input('settings.documents.show_document_in_profile'),
                'admin_can_sign_document' => (bool) $request->input('settings.documents.admin_can_sign_document'),
                'include_audit_trail' => (bool) $request->input('settings.documents.include_audit_trail'),
                'allow_download_without_signing' => (bool) $request->input('settings.documents.allow_download_without_signing'),
                'update_status_when_all_signed' => (bool) $request->input('settings.documents.update_status_when_all_signed'),
            ],
            'schedule_availability' => [
                'dont_allow_reviews' => (bool) $request->input('settings.schedule_availability.dont_allow_reviews'),
                'set_status_if_no_shift' => (bool) $request->input('settings.schedule_availability.set_status_if_no_shift'),
                'show_cancelled_jobs' => (bool) $request->input('settings.schedule_availability.show_cancelled_jobs'),
                'show_past_jobs' => (bool) $request->input('settings.schedule_availability.show_past_jobs'),
                'candidates_can_decline' => (bool) $request->input('settings.schedule_availability.candidates_can_decline'),
                'days_past_load_job' => $request->input('settings.schedule_availability.days_past_load_job', 14),
                'days_future_load_job' => $request->input('settings.schedule_availability.days_future_load_job', 28),
                'minutes_before_clock_button' => $request->input('settings.schedule_availability.minutes_before_clock_button', 30),
                'clock_interval_minutes' => $request->input('settings.schedule_availability.clock_interval_minutes', 15),
                'client_confirm_clock' => (bool) $request->input('settings.schedule_availability.client_confirm_clock'),
                'client_confirm_clock_phone' => (bool) $request->input('settings.schedule_availability.client_confirm_clock_phone'),
                'notify_hours_before_shift' => $request->input('settings.schedule_availability.notify_hours_before_shift', []),
                'notify_method' => $request->input('settings.schedule_availability.notify_method', []),
                'reminder_minutes_after_clock' => $request->input('settings.schedule_availability.reminder_minutes_after_clock', []),
                'reminder_notify_method' => $request->input('settings.schedule_availability.reminder_notify_method', []),
            ],
        ];

        $existing = AgencyClientGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];
        $merged = array_replace_recursive($this->defaultSettings(), $existing, $safeSettings);

        AgencyClientGlobalSetting::updateOrCreate(
            ['agency_id' => $agency_id],
            ['settings' => $merged]
        );

        return response()->json(['status' => true, 'message' => 'Client global settings updated successfully']);
    }

    private function defaultSettings(): array
    {
        return [
            'dashboard' => [
                'table_fields' => ['name', 'email_address', 'phone_number', 'registration_date', 'status'],
                'display_labels' => [],
                'use_admin_level_setting' => false,
                'quick_search_field' => null,
                'default_sort_field' => null,
                'last_login_retrieval_days' => null,
                'show_status_statistical_breakdowns' => false,
            ],
            'profile' => [
                'profile_picture_scope' => 'public',
                'default_documents_scope' => 'public',
                'interview_boxes_count' => 2,
                'availability_scheduling_interval' => 15,
                'time_off_request_categories' => ['Holiday', 'Sick', 'Unpaid', 'Paid'],
                'min_time_block_duration' => 2,
                'max_hours_per_week' => 2,
                'time_off_request_days' => 0,
                'buffer_time_hours' => 0,
            ],
            'booking_access' => [
                'show_candidate_rate_to_client' => false,
                'show_client_rate_to_candidate' => false,
                'types_cannot_edit_shift_jobs' => [],
                'max_hours_for_bookings' => [],
                'min_hours_for_bookings' => [],
            ],
            'registration_fee' => [
                'registration_fee' => null,
            ],
            'invoices' => [
                'show_quick_invoice_button' => false,
                'show_quick_invoice_for_shift_job' => false,
                'check_partial_payment_by_default' => false,
                'use_advanced_invoice_manager' => false,
                'use_stripe_terminal' => false,
            ],
            'documents' => [
                'show_document_in_profile' => false,
                'admin_can_sign_document' => false,
                'include_audit_trail' => false,
                'allow_download_without_signing' => false,
                'update_status_when_all_signed' => false,
            ],
            'schedule_availability' => [
                'dont_allow_reviews' => false,
                'set_status_if_no_shift' => false,
                'show_cancelled_jobs' => false,
                'show_past_jobs' => false,
                'candidates_can_decline' => false,
                'days_past_load_job' => 14,
                'days_future_load_job' => 28,
                'minutes_before_clock_button' => 30,
                'clock_interval_minutes' => 15,
                'client_confirm_clock' => false,
                'client_confirm_clock_phone' => false,
                'notify_hours_before_shift' => [],
                'notify_method' => [],
                'reminder_minutes_after_clock' => [],
                'reminder_notify_method' => [],
            ],
        ];
    }
}
