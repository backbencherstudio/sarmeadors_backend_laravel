<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyCandidateGlobalSetting;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CandidateGlobalSettingsController extends Controller
{
    /**
     * Columns the candidate data table is able to render. Keep this list in sync with
     * the resolution logic in CandidateController::index().
     *
     * @return array<string,string>
     */
    public static function availableColumns(): array
    {
        return [
            'name' => 'Name',
            'email_address' => 'Email Address',
            'phone_number' => 'Phone Number',
            'registration_date' => 'Registration Date',
            'position_applying_for' => 'Position(s) Applying For',
            'last_login' => 'Last Login',
            'locations' => 'Locations',
            'status' => 'Status',
            'hear_about_us' => 'Hear About Us',
        ];
    }

    /**
     * Ensure every field in `table_fields` has a `display_labels` entry, defaulting
     * to its canonical label from availableColumns() when the caller didn't provide one.
     *
     * @param  array<int,string>  $tableFields
     * @param  array<string,string>  $displayLabels
     * @return array<string,string>
     */
    private function fillMissingLabels(array $tableFields, array $displayLabels): array
    {
        foreach ($tableFields as $field) {
            if (! array_key_exists($field, $displayLabels)) {
                $displayLabels[$field] = self::availableColumns()[$field] ?? $field;
            }
        }

        return $displayLabels;
    }

    // public function getAvailableCandidateColumns()
    // {
    //     $columns = collect(self::availableColumns())
    //         ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
    //         ->values();

    //     return response()->json([
    //         'status' => true,
    //         'data' => $columns,
    //     ]);
    // }

    public function getAvailableCandidateColumns()
    {
        $agency_id = auth('api')->user()->agency_id;

        $savedSettings = AgencyCandidateGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];

        $defaults = $this->defaultSettings();

        $selectedFields = $savedSettings['dashboard']['table_fields'] ?? $defaults['dashboard']['table_fields'] ?? [];

        $customLabels = $savedSettings['dashboard']['display_labels'] ?? [];

        $columns = collect(self::availableColumns())->map(function ($label, $key) use ($selectedFields, $customLabels) {
            return [
                'key' => $key,
                'label' => $customLabels[$key] ?? $label,
                'is_selected' => in_array($key, (array) $selectedFields),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => $columns,
        ]);
    }

    public function getCandidateTableSettings()
    {
        $agency_id = auth('api')->user()->agency_id;

        $saved = AgencyCandidateGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];
        $dashboard = array_replace_recursive($this->defaultSettings()['dashboard'], $saved['dashboard'] ?? []);

        $statuses = Status::where('agency_id', $agency_id)
            ->where('type', 'candidate')
            ->orderBy('serial')
            ->get(['id', 'name', 'color', 'serial', 'any_reason', 'reason']);

        return response()->json([
            'status' => true,
            'data' => [
                'dashboard' => $dashboard,
                'statuses' => $statuses,
            ],
        ]);
    }

    public function updateCandidateTableSettings(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'table_fields' => 'nullable|array',
            'table_fields.*' => [Rule::in(array_keys(self::availableColumns()))],
            'display_labels' => 'nullable|array',
            'use_admin_level_setting' => 'nullable|boolean',
            'quick_search_field' => ['nullable', 'string', Rule::in(array_keys(self::availableColumns()))],
            'default_sort_field' => ['nullable', 'string', Rule::in(array_keys(self::availableColumns()))],
            'last_login_retrieval_days' => 'nullable|integer',
            'show_status_statistical_breakdowns' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $existing = AgencyCandidateGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];

        $tableFields = $request->input('table_fields', []);

        $dashboard = [
            'table_fields' => $tableFields,
            'display_labels' => $this->fillMissingLabels($tableFields, $request->input('display_labels', [])),
            'use_admin_level_setting' => (bool) $request->input('use_admin_level_setting'),
            'quick_search_field' => $request->input('quick_search_field'),
            'default_sort_field' => $request->input('default_sort_field'),
            'last_login_retrieval_days' => $request->input('last_login_retrieval_days'),
            'show_status_statistical_breakdowns' => (bool) $request->input('show_status_statistical_breakdowns'),
        ];

        $merged = array_replace_recursive($this->defaultSettings(), $existing);
        $merged['dashboard'] = $dashboard;

        AgencyCandidateGlobalSetting::updateOrCreate(
            ['agency_id' => $agency_id],
            ['settings' => $merged]
        );

        return response()->json([
            'status' => true,
            'message' => 'Candidate table settings updated successfully',
            'data' => $dashboard,
        ]);
    }

    public function getCandidateGlobalSettings()
    {
        $agency_id = auth('api')->user()->agency_id;

        $saved = AgencyCandidateGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];

        return response()->json([
            'status' => true,
            'data' => array_replace_recursive($this->defaultSettings(), $saved),
        ]);
    }


    public function updateCandidateGlobalSettings(Request $request)
    {
        $agency_id = auth('api')->user()->agency_id;

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.dashboard.table_fields' => 'nullable|array',
            'settings.dashboard.table_fields.*' => [Rule::in(array_keys(self::availableColumns()))],
            'settings.dashboard.display_labels' => 'nullable|array',
            'settings.dashboard.use_admin_level_setting' => 'nullable|boolean',
            'settings.dashboard.quick_search_field' => 'nullable|string',
            'settings.dashboard.default_sort_field' => 'nullable|string',
            'settings.dashboard.last_login_retrieval_days' => 'nullable|integer',
            'settings.dashboard.show_status_statistical_breakdowns' => 'nullable|boolean',
            'settings.profile.profile_picture_scope' => 'nullable|string',
            'settings.profile.interview_boxes_count' => 'nullable|integer',
            'settings.profile.availability_scheduling_interval' => 'nullable|integer',
            'settings.profile.time_off_request_categories' => 'nullable|array',
            'settings.profile.min_time_block_duration' => 'nullable|integer',
            'settings.profile.max_hours_per_week' => 'nullable|integer',
            'settings.profile.time_off_request_days' => 'nullable|integer',
            'settings.profile.buffer_time_hours' => 'nullable|integer',
            'settings.access_control.no_dashboard_access_statuses' => 'nullable|array',
            'settings.access_control.no_dashboard_access_message' => 'nullable|string',
            'settings.access_control.resubmit_application_statuses' => 'nullable|array',
            'settings.registration_fee.registration_fee' => 'nullable|numeric',
            'settings.documents.show_document_in_profile' => 'nullable|boolean',
            'settings.documents.hide_documents_in_profile' => 'nullable|boolean',
            'settings.documents.notify_agency_on_expiration_edit' => 'nullable|boolean',
            'settings.documents.update_status_when_all_signed' => 'nullable|boolean',
            'settings.documents.notify_agency_on_upload' => 'nullable|boolean',
            'settings.documents.hide_document_name' => 'nullable|boolean',
            'settings.documents.admin_can_sign_document' => 'nullable|boolean',
            'settings.documents.include_audit_trail' => 'nullable|boolean',
            'settings.documents.prevent_candidate_delete' => 'nullable|boolean',
            'settings.documents.remove_upload_option_candidate' => 'nullable|boolean',
            'settings.documents.remove_upload_option_client' => 'nullable|boolean',
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

        $existingRecord = AgencyCandidateGlobalSetting::where('agency_id', $agency_id)->first();

        $existingSettings = $existingRecord ? $existingRecord->settings : $this->defaultSettings();

        // $incomingSettings = $request->input('settings', []);
        $incomingSettings = $validator->validated()['settings'] ?? [];

        $mergedSettings = array_replace_recursive($existingSettings, $incomingSettings);

        if (isset($incomingSettings['dashboard']['table_fields'])) {
            $mergedSettings['dashboard']['display_labels'] = $this->fillMissingLabels(
                $mergedSettings['dashboard']['table_fields'],
                $mergedSettings['dashboard']['display_labels'] ?? []
            );
        }

        AgencyCandidateGlobalSetting::updateOrCreate(
            ['agency_id' => $agency_id],
            ['settings' => $mergedSettings]
        );

        return response()->json([
            'status' => true,
            'message' => 'Candidate global settings updated successfully',
            'data' => $mergedSettings
        ]);
    }

    // public function updateCandidateGlobalSettings(Request $request)
    // {
    //     $agency_id = auth('api')->user()->agency_id;

    //     $validator = Validator::make($request->all(), [
    //         'settings' => 'required|array',
    //         'settings.dashboard.table_fields' => 'nullable|array',
    //         'settings.dashboard.table_fields.*' => [Rule::in(array_keys(self::availableColumns()))],
    //         'settings.dashboard.display_labels' => 'nullable|array',
    //         'settings.dashboard.use_admin_level_setting' => 'nullable|boolean',
    //         'settings.dashboard.quick_search_field' => 'nullable|string',
    //         'settings.dashboard.default_sort_field' => 'nullable|string',
    //         'settings.dashboard.last_login_retrieval_days' => 'nullable|integer',
    //         'settings.dashboard.show_status_statistical_breakdowns' => 'nullable|boolean',
    //         'settings.profile.profile_picture_scope' => 'nullable|string',
    //         'settings.profile.interview_boxes_count' => 'nullable|integer',
    //         'settings.profile.availability_scheduling_interval' => 'nullable|integer',
    //         'settings.profile.time_off_request_categories' => 'nullable|array',
    //         'settings.profile.min_time_block_duration' => 'nullable|integer',
    //         'settings.profile.max_hours_per_week' => 'nullable|integer',
    //         'settings.profile.time_off_request_days' => 'nullable|integer',
    //         'settings.profile.buffer_time_hours' => 'nullable|integer',
    //         'settings.access_control.no_dashboard_access_statuses' => 'nullable|array',
    //         'settings.access_control.no_dashboard_access_message' => 'nullable|string',
    //         'settings.access_control.resubmit_application_statuses' => 'nullable|array',
    //         'settings.registration_fee.registration_fee' => 'nullable|numeric',
    //         'settings.documents.show_document_in_profile' => 'nullable|boolean',
    //         'settings.documents.hide_documents_in_profile' => 'nullable|boolean',
    //         'settings.documents.notify_agency_on_expiration_edit' => 'nullable|boolean',
    //         'settings.documents.update_status_when_all_signed' => 'nullable|boolean',
    //         'settings.documents.notify_agency_on_upload' => 'nullable|boolean',
    //         'settings.documents.hide_document_name' => 'nullable|boolean',
    //         'settings.documents.admin_can_sign_document' => 'nullable|boolean',
    //         'settings.documents.include_audit_trail' => 'nullable|boolean',
    //         'settings.documents.prevent_candidate_delete' => 'nullable|boolean',
    //         'settings.documents.remove_upload_option_candidate' => 'nullable|boolean',
    //         'settings.documents.remove_upload_option_client' => 'nullable|boolean',
    //         'settings.schedule_availability.dont_allow_reviews' => 'nullable|boolean',
    //         'settings.schedule_availability.set_status_if_no_shift' => 'nullable|boolean',
    //         'settings.schedule_availability.show_cancelled_jobs' => 'nullable|boolean',
    //         'settings.schedule_availability.show_past_jobs' => 'nullable|boolean',
    //         'settings.schedule_availability.candidates_can_decline' => 'nullable|boolean',
    //         'settings.schedule_availability.days_past_load_job' => 'nullable|integer',
    //         'settings.schedule_availability.days_future_load_job' => 'nullable|integer',
    //         'settings.schedule_availability.minutes_before_clock_button' => 'nullable|integer',
    //         'settings.schedule_availability.clock_interval_minutes' => 'nullable|integer',
    //         'settings.schedule_availability.client_confirm_clock' => 'nullable|boolean',
    //         'settings.schedule_availability.client_confirm_clock_phone' => 'nullable|boolean',
    //         'settings.schedule_availability.notify_hours_before_shift' => 'nullable|array',
    //         'settings.schedule_availability.notify_method' => 'nullable|array',
    //         'settings.schedule_availability.reminder_minutes_after_clock' => 'nullable|array',
    //         'settings.schedule_availability.reminder_notify_method' => 'nullable|array',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $safeSettings = [
    //         'dashboard' => [
    //             'table_fields' => $request->input('settings.dashboard.table_fields', []),
    //             'display_labels' => $this->fillMissingLabels(
    //                 $request->input('settings.dashboard.table_fields', []),
    //                 $request->input('settings.dashboard.display_labels', [])
    //             ),
    //             'use_admin_level_setting' => (bool) $request->input('settings.dashboard.use_admin_level_setting'),
    //             'quick_search_field' => $request->input('settings.dashboard.quick_search_field'),
    //             'default_sort_field' => $request->input('settings.dashboard.default_sort_field'),
    //             'last_login_retrieval_days' => $request->input('settings.dashboard.last_login_retrieval_days'),
    //             'show_status_statistical_breakdowns' => (bool) $request->input('settings.dashboard.show_status_statistical_breakdowns'),
    //         ],
    //         'profile' => [
    //             'profile_picture_scope' => $request->input('settings.profile.profile_picture_scope', 'public'),
    //             'interview_boxes_count' => $request->input('settings.profile.interview_boxes_count', 2),
    //             'availability_scheduling_interval' => $request->input('settings.profile.availability_scheduling_interval', 15),
    //             'time_off_request_categories' => $request->input('settings.profile.time_off_request_categories', ['Holiday', 'Sick', 'Unpaid', 'Paid']),
    //             'min_time_block_duration' => $request->input('settings.profile.min_time_block_duration', 2),
    //             'max_hours_per_week' => $request->input('settings.profile.max_hours_per_week', 2),
    //             'time_off_request_days' => $request->input('settings.profile.time_off_request_days', 0),
    //             'buffer_time_hours' => $request->input('settings.profile.buffer_time_hours', 0),
    //         ],
    //         'access_control' => [
    //             'no_dashboard_access_statuses' => $request->input('settings.access_control.no_dashboard_access_statuses', []),
    //             'no_dashboard_access_message' => $request->input('settings.access_control.no_dashboard_access_message'),
    //             'resubmit_application_statuses' => $request->input('settings.access_control.resubmit_application_statuses', []),
    //         ],
    //         'registration_fee' => [
    //             'registration_fee' => $request->input('settings.registration_fee.registration_fee'),
    //         ],
    //         'documents' => [
    //             'show_document_in_profile' => (bool) $request->input('settings.documents.show_document_in_profile'),
    //             'hide_documents_in_profile' => (bool) $request->input('settings.documents.hide_documents_in_profile'),
    //             'notify_agency_on_expiration_edit' => (bool) $request->input('settings.documents.notify_agency_on_expiration_edit'),
    //             'update_status_when_all_signed' => (bool) $request->input('settings.documents.update_status_when_all_signed'),
    //             'notify_agency_on_upload' => (bool) $request->input('settings.documents.notify_agency_on_upload'),
    //             'hide_document_name' => (bool) $request->input('settings.documents.hide_document_name'),
    //             'admin_can_sign_document' => (bool) $request->input('settings.documents.admin_can_sign_document'),
    //             'include_audit_trail' => (bool) $request->input('settings.documents.include_audit_trail'),
    //             'prevent_candidate_delete' => (bool) $request->input('settings.documents.prevent_candidate_delete'),
    //             'remove_upload_option_candidate' => (bool) $request->input('settings.documents.remove_upload_option_candidate'),
    //             'remove_upload_option_client' => (bool) $request->input('settings.documents.remove_upload_option_client'),
    //         ],
    //         'schedule_availability' => [
    //             'dont_allow_reviews' => (bool) $request->input('settings.schedule_availability.dont_allow_reviews'),
    //             'set_status_if_no_shift' => (bool) $request->input('settings.schedule_availability.set_status_if_no_shift'),
    //             'show_cancelled_jobs' => (bool) $request->input('settings.schedule_availability.show_cancelled_jobs'),
    //             'show_past_jobs' => (bool) $request->input('settings.schedule_availability.show_past_jobs'),
    //             'candidates_can_decline' => (bool) $request->input('settings.schedule_availability.candidates_can_decline'),
    //             'days_past_load_job' => $request->input('settings.schedule_availability.days_past_load_job', 14),
    //             'days_future_load_job' => $request->input('settings.schedule_availability.days_future_load_job', 28),
    //             'minutes_before_clock_button' => $request->input('settings.schedule_availability.minutes_before_clock_button', 30),
    //             'clock_interval_minutes' => $request->input('settings.schedule_availability.clock_interval_minutes', 15),
    //             'client_confirm_clock' => (bool) $request->input('settings.schedule_availability.client_confirm_clock'),
    //             'client_confirm_clock_phone' => (bool) $request->input('settings.schedule_availability.client_confirm_clock_phone'),
    //             'notify_hours_before_shift' => $request->input('settings.schedule_availability.notify_hours_before_shift', []),
    //             'notify_method' => $request->input('settings.schedule_availability.notify_method', []),
    //             'reminder_minutes_after_clock' => $request->input('settings.schedule_availability.reminder_minutes_after_clock', []),
    //             'reminder_notify_method' => $request->input('settings.schedule_availability.reminder_notify_method', []),
    //         ],
    //     ];

    //     $existing = AgencyCandidateGlobalSetting::where('agency_id', $agency_id)->value('settings') ?? [];
    //     $merged = array_replace_recursive($this->defaultSettings(), $existing);

    //     foreach ($safeSettings as $section => $values) {
    //         $merged[$section] = $values;
    //     }

    //     AgencyCandidateGlobalSetting::updateOrCreate(
    //         ['agency_id' => $agency_id],
    //         ['settings' => $merged]
    //     );

    //     return response()->json(['status' => true, 'message' => 'Candidate global settings updated successfully']);
    // }

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
                'interview_boxes_count' => 2,
                'availability_scheduling_interval' => 15,
                'time_off_request_categories' => ['Holiday', 'Sick', 'Unpaid', 'Paid'],
                'min_time_block_duration' => 2,
                'max_hours_per_week' => 2,
                'time_off_request_days' => 0,
                'buffer_time_hours' => 0,
            ],
            'access_control' => [
                'no_dashboard_access_statuses' => [],
                'no_dashboard_access_message' => null,
                'resubmit_application_statuses' => [],
            ],
            'registration_fee' => [
                'registration_fee' => null,
            ],
            'documents' => [
                'show_document_in_profile' => false,
                'hide_documents_in_profile' => false,
                'notify_agency_on_expiration_edit' => false,
                'update_status_when_all_signed' => false,
                'notify_agency_on_upload' => false,
                'hide_document_name' => false,
                'admin_can_sign_document' => false,
                'include_audit_trail' => false,
                'prevent_candidate_delete' => false,
                'remove_upload_option_candidate' => false,
                'remove_upload_option_client' => false,
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
