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

        $settings = AgencyClientGlobalSetting::where('agency_id', $agency_id)->first();

        return response()->json([
            'status' => true,
            'data' => $settings ? $settings->settings : $this->defaultSettings(),
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
        ];
    }
}
