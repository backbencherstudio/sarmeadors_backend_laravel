<?php

use App\Http\Controllers\Agency\AgencyCandidateDocumentController;
use App\Http\Controllers\Agency\AgencyChecklistController;
use App\Http\Controllers\Agency\AgencyController;
use App\Http\Controllers\Agency\AgencyEventController;
use App\Http\Controllers\Agency\AgencyEventTypeController;
use App\Http\Controllers\Agency\AgencyGlobalJobSettingsController;
use App\Http\Controllers\Agency\AgencyInterviewController;
use App\Http\Controllers\Agency\AgencyLocationController;
use App\Http\Controllers\Agency\AgencyNoteController;
use App\Http\Controllers\Agency\AgencyNotificationController;
use App\Http\Controllers\Agency\AgencySettingsController;
use App\Http\Controllers\Agency\AgencyTagController;
use App\Http\Controllers\Agency\AgencyTypeController;
use App\Http\Controllers\Agency\CandidateController;
use App\Http\Controllers\Agency\CandidateGlobalSettingsController;
use App\Http\Controllers\Agency\CandidateNoteController;
use App\Http\Controllers\Agency\CandidatePasswordController;
use App\Http\Controllers\Agency\CandidateProfileController;
use App\Http\Controllers\Agency\ClientController;
use App\Http\Controllers\Agency\ClientDocumentController;
use App\Http\Controllers\Agency\ClientGlobalSettingsController;
use App\Http\Controllers\Agency\ClientNoteController;
use App\Http\Controllers\Agency\ClientPasswordController;
use App\Http\Controllers\Agency\DocumentTemplateController;
use App\Http\Controllers\Agency\FormController;
use App\Http\Controllers\Agency\FormFieldController;
use App\Http\Controllers\Agency\JobMessageController as AgencyJobMessageController;
use App\Http\Controllers\Agency\LongTermJobApplicationController as AgencyLongTermJobApplicationController;
use App\Http\Controllers\Agency\LongTermJobAttendanceController as AgencyLongTermJobAttendanceController;
use App\Http\Controllers\Agency\LongTermJobController as AgencyLongTermJobController;
use App\Http\Controllers\Agency\LongTermJobRefundController as AgencyLongTermJobRefundController;
use App\Http\Controllers\Agency\MessageTemplateController;
use App\Http\Controllers\Agency\ServiceController;
use App\Http\Controllers\Agency\SettingsController;
use App\Http\Controllers\Agency\ShortTermJobApplicationController as AgencyShortTermJobApplicationController;
use App\Http\Controllers\Agency\ShortTermJobAttendanceController as AgencyShortTermJobAttendanceController;
use App\Http\Controllers\Agency\ShortTermJobController as AgencyShortTermJobController;
use App\Http\Controllers\Agency\ShortTermJobRefundController as AgencyShortTermJobRefundController;
use App\Http\Controllers\Agency\StatusController;
use App\Http\Controllers\Agency\StatusTemplateController;
use App\Http\Controllers\Agency\SubLocationController;
use App\Http\Controllers\Messages\ShortTermJobMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->prefix('agency')->group(function () {
    Route::get('agency_notes/{user}', [AgencyNoteController::class, 'index']);
    Route::post('agency_notes/{user}', [AgencyNoteController::class, 'store']);
    Route::get('agency_notes/show/{agency_note}', [AgencyNoteController::class, 'show']);
    Route::put('agency_notes/{agency_note}', [AgencyNoteController::class, 'update']);
    Route::put('agency_notes/pin_note/{agency_note}', [AgencyNoteController::class, 'pin_note']);
    Route::put('agency_notes/mark_read', [AgencyNoteController::class, 'mark_read']);
    Route::delete('agency_notes', [AgencyNoteController::class, 'destroy']);

    Route::get('message_template', [MessageTemplateController::class, 'index']);
    Route::post('message_template', [MessageTemplateController::class, 'store']);
    Route::get('message_template/{messageTemplate}', [MessageTemplateController::class, 'show']);
    Route::put('message_template/{messageTemplate}', [MessageTemplateController::class, 'update']);
    Route::delete('message_template', [MessageTemplateController::class, 'destroy']);

    // Stripe credentials
    Route::get('settings/stripe', [AgencySettingsController::class, 'getStripeStatus']);
    Route::post('settings/stripe', [AgencySettingsController::class, 'saveStripeKeys']);
    Route::delete('settings/stripe', [AgencySettingsController::class, 'removeStripeKeys']);

    // Short-term job fee settings
    Route::get('settings/short-term-fee', [AgencySettingsController::class, 'getShortTermFeeSettings']);
    Route::put('settings/short-term-fee', [AgencySettingsController::class, 'updateShortTermFeeSettings']);

    // Short-term job approval settings
    Route::get('settings/short-term-approval', [AgencySettingsController::class, 'getShortTermApprovalSettings']);
    Route::put('settings/short-term-approval', [AgencySettingsController::class, 'updateShortTermApprovalSettings']);

    // Agency short-term job management
    Route::get('jobs/short-term', [AgencyShortTermJobController::class, 'index']);
    Route::get('jobs/short-term/{shortTermJob}', [AgencyShortTermJobController::class, 'show']);
    Route::put('jobs/short-term/{shortTermJob}/approve', [AgencyShortTermJobController::class, 'approve']);
    Route::put('jobs/short-term/{shortTermJob}/reject', [AgencyShortTermJobController::class, 'reject']);
    Route::put('jobs/short-term/{shortTermJob}/cancel', [AgencyShortTermJobController::class, 'cancel']);
    Route::put('jobs/short-term/{shortTermJob}/complete', [AgencyShortTermJobController::class, 'complete']);

    // Agency short-term applicants
    Route::get('jobs/short-term/{shortTermJob}/applicants', [AgencyShortTermJobApplicationController::class, 'index']);
    Route::get('jobs/short-term/{shortTermJob}/applicants/{applicationId}', [AgencyShortTermJobApplicationController::class, 'show']);
    Route::put('jobs/short-term/{shortTermJob}/applicants/{applicationId}/confirm-hire', [AgencyShortTermJobApplicationController::class, 'confirmHire']);

    // Agency short-term attendance
    Route::get('jobs/short-term/{shortTermJob}/attendance', [AgencyShortTermJobAttendanceController::class, 'index']);

    // Agency short-term messages
    Route::get('jobs/short-term/{shortTermJob}/messages', [ShortTermJobMessageController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/messages', [ShortTermJobMessageController::class, 'store']);
    Route::get('jobs/short-term/{shortTermJob}/messages/unread-counts', [ShortTermJobMessageController::class, 'unreadCounts']);

    // Agency short-term refund
    Route::get('jobs/short-term/{shortTermJob}/refund-request', [AgencyShortTermJobRefundController::class, 'show']);
    Route::put('jobs/short-term/{shortTermJob}/refund-request/approve', [AgencyShortTermJobRefundController::class, 'approve']);
    Route::put('jobs/short-term/{shortTermJob}/refund-request/reject', [AgencyShortTermJobRefundController::class, 'reject']);

    // Agency long-term job management
    Route::get('jobs/long-term', [AgencyLongTermJobController::class, 'index']);
    Route::get('jobs/long-term/{longTermJob}', [AgencyLongTermJobController::class, 'show']);
    Route::put('jobs/long-term/{longTermJob}/approve', [AgencyLongTermJobController::class, 'approve']);
    Route::put('jobs/long-term/{longTermJob}/reject', [AgencyLongTermJobController::class, 'reject']);
    Route::put('jobs/long-term/{longTermJob}/complete', [AgencyLongTermJobController::class, 'complete']);
    Route::put('jobs/long-term/{longTermJob}/assign-nanny', [AgencyLongTermJobAttendanceController::class, 'assignNanny']);

    // Agency long-term attendance calendar & nanny payments
    Route::get('jobs/long-term/{longTermJob}/attendance', [AgencyLongTermJobAttendanceController::class, 'calendar']);
    Route::post('jobs/long-term/{longTermJob}/attendance', [AgencyLongTermJobAttendanceController::class, 'upsert']);
    Route::get('jobs/long-term/{longTermJob}/nanny-payments', [AgencyLongTermJobAttendanceController::class, 'listNannyPayments']);
    Route::post('jobs/long-term/{longTermJob}/nanny-payments', [AgencyLongTermJobAttendanceController::class, 'recordNannyPayment']);

    // Agency long-term job messages (both client & candidate threads)
    Route::get('jobs/long-term/{longTermJob}/messages', [AgencyJobMessageController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/messages', [AgencyJobMessageController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/messages/unread-counts', [AgencyJobMessageController::class, 'unreadCounts']);

    // Agency long-term job applications management
    Route::get('jobs/long-term/{longTermJob}/applicants', [AgencyLongTermJobApplicationController::class, 'index']);
    Route::get('jobs/long-term/{longTermJob}/applicants/{application}', [AgencyLongTermJobApplicationController::class, 'show']);
    Route::put('jobs/long-term/{longTermJob}/applicants/{application}/confirm-hire', [AgencyLongTermJobApplicationController::class, 'confirmHire']);

    // Agency interview management: agency sets, reschedules and cancels every
    // interview (list + calendar). Clients/candidates can only request and view.
    Route::get('interviews', [AgencyInterviewController::class, 'index']);
    Route::post('interviews', [AgencyInterviewController::class, 'store']);
    Route::get('interviews/{interview}', [AgencyInterviewController::class, 'show']);
    Route::put('interviews/{interview}/schedule', [AgencyInterviewController::class, 'schedule']);
    Route::put('interviews/{interview}/decline', [AgencyInterviewController::class, 'decline']);
    Route::put('interviews/{interview}/cancel', [AgencyInterviewController::class, 'cancel']);
    Route::put('interviews/{interview}/complete', [AgencyInterviewController::class, 'complete']);

    // Agency long-term refund request management
    Route::get('jobs/long-term/{longTermJob}/refund-request', [AgencyLongTermJobRefundController::class, 'show']);
    Route::put('jobs/long-term/{longTermJob}/refund-request/approve', [AgencyLongTermJobRefundController::class, 'approve']);
    Route::put('jobs/long-term/{longTermJob}/refund-request/reject', [AgencyLongTermJobRefundController::class, 'reject']);

    // agency sub-location management route for global settings
    Route::get('sub-locations', [SubLocationController::class, 'index']);
    Route::post('sub-location-store', [SubLocationController::class, 'store']);
    Route::get('sub-location/{id}', [SubLocationController::class, 'show']);
    Route::put('sub-location-update/{id}', [SubLocationController::class, 'update']);
    Route::delete('sub-location-destroy/{id}', [SubLocationController::class, 'destroy']);

    // agency service management route for global settings
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/service-store', [ServiceController::class, 'store']);
    Route::get('/service/{id}', [ServiceController::class, 'show']);
    Route::put('/service-update/{id}', [ServiceController::class, 'update']);
    Route::delete('/service-destroy/{id}', [ServiceController::class, 'destroy']);
    Route::patch('/service-change-status/{id}', [ServiceController::class, 'changeStatus']);

    // agency business details management route for global settings
    Route::get('/business-details', [SettingsController::class, 'getBusinessDetails']);
    Route::post('/business-holiday-store', [SettingsController::class, 'storeHoliday']);
    Route::delete('/business-holiday-delete/{id}', [SettingsController::class, 'destroyHoliday']);
    Route::post('/business-setting-update', [SettingsController::class, 'updateBusinessSettings']);
    Route::post('/business-hour-update', [SettingsController::class, 'updateBusinessHours']);
    Route::patch('/business-hours/{id}/status', [SettingsController::class, 'toggleBusinessHourStatus']);

    // agency communication settings management route for global settings
    Route::get('settings/communication', [SettingsController::class, 'getCommunicationSettings']);
    Route::post('settings/communication', [SettingsController::class, 'updateCommunicationSettings']);

    // agency global job settings management route for global settings
    Route::get('/get-global-job-settings', [AgencyGlobalJobSettingsController::class, 'getGlobalJobSettings']);
    Route::post('/update-global-job-settings', [AgencyGlobalJobSettingsController::class, 'updateGlobalJobSettings']);

    // agency global info management route
    Route::get('/agency-info', [AgencyController::class, 'info']);
    Route::post('info-update/{id}', [AgencyController::class, 'infoUpdate']);

    // client global settings
    Route::get('settings/client', [ClientGlobalSettingsController::class, 'getClientGlobalSettings']);
    Route::post('settings/client', [ClientGlobalSettingsController::class, 'updateClientGlobalSettings']);

    // client table settings (dashboard columns + client statuses) for the "Client Table Settings" panel
    Route::get('settings/client/table', [ClientGlobalSettingsController::class, 'getClientTableSettings']);
    Route::post('settings/client/table', [ClientGlobalSettingsController::class, 'updateClientTableSettings']);
    Route::get('settings/client/table/columns', [ClientGlobalSettingsController::class, 'getAvailableClientColumns']);

    // candidate global settings
    Route::get('settings/candidate', [CandidateGlobalSettingsController::class, 'getCandidateGlobalSettings']);
    Route::post('settings/candidate', [CandidateGlobalSettingsController::class, 'updateCandidateGlobalSettings']);

    // candidate table settings (dashboard columns + candidate statuses) for the "Candidate Table Settings" panel
    Route::get('settings/candidate/table', [CandidateGlobalSettingsController::class, 'getCandidateTableSettings']);
    Route::post('settings/candidate/table', [CandidateGlobalSettingsController::class, 'updateCandidateTableSettings']);
    Route::get('settings/candidate/table/columns', [CandidateGlobalSettingsController::class, 'getAvailableCandidateColumns']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->prefix('agency')->group(function () {

    // agency type management route
    Route::get('types', [AgencyTypeController::class, 'index']);
    Route::get('type/{id}', [AgencyTypeController::class, 'show']);
    Route::post('type-store', [AgencyTypeController::class, 'store']);
    Route::put('type-update/{id}', [AgencyTypeController::class, 'update']);
    Route::put('type-bulk-update', [AgencyTypeController::class, 'bulkUpdate']);
    Route::delete('type-destroy/{id}', [AgencyTypeController::class, 'destroy']);
    Route::patch('type-change-status/{id}', [AgencyTypeController::class, 'changeStatus']);

    // agency location management route
    Route::get('locations', [AgencyLocationController::class, 'index']);
    Route::get('location/{id}', [AgencyLocationController::class, 'show']);
    Route::post('location-store', [AgencyLocationController::class, 'store']);
    Route::put('location-update/{id}', [AgencyLocationController::class, 'update']);
    Route::put('location-bulk-update', [AgencyLocationController::class, 'bulkUpdate']);
    Route::delete('location-destroy/{id}', [AgencyLocationController::class, 'destroy']);
    Route::patch('location-change-status/{id}', [AgencyLocationController::class, 'changeStatus']);

    // agency checklist management route
    Route::get('checklist', [AgencyChecklistController::class, 'index']);
    Route::get('checklist/{id}', [AgencyChecklistController::class, 'show']);
    Route::post('checklist-store', [AgencyChecklistController::class, 'store']);
    Route::put('checklist-update/{id}', [AgencyChecklistController::class, 'update']);
    Route::put('checklist-bulk-update', [AgencyChecklistController::class, 'bulkUpdate']);
    Route::delete('checklist-destroy/{id}', [AgencyChecklistController::class, 'destroy']);
    Route::patch('checklist-change-status/{id}', [AgencyChecklistController::class, 'changeStatus']);

    // agency tags management route
    Route::get('tags', [AgencyTagController::class, 'index']);
    Route::get('tag/{id}', [AgencyTagController::class, 'show']);
    Route::post('tag-store', [AgencyTagController::class, 'store']);
    Route::put('tag-update/{id}', [AgencyTagController::class, 'update']);
    Route::put('tag-bulk-update', [AgencyTagController::class, 'bulkUpdate']);
    Route::delete('tag-destroy/{id}', [AgencyTagController::class, 'destroy']);
    Route::patch('tag-change-status/{id}', [AgencyTagController::class, 'changeStatus']);

    // agency event type management route
    Route::get('event-types', [AgencyEventTypeController::class, 'index']);
    Route::get('event-type/{id}', [AgencyEventTypeController::class, 'show']);
    Route::post('event-type-store', [AgencyEventTypeController::class, 'store']);
    Route::put('event-type-update/{id}', [AgencyEventTypeController::class, 'update']);
    Route::put('event-type-bulk-update', [AgencyEventTypeController::class, 'bulkUpdate']);
    Route::delete('event-type-destroy/{id}', [AgencyEventTypeController::class, 'destroy']);

    // agency event management route
    Route::get('events', [AgencyEventController::class, 'index']);
    Route::post('event-store', [AgencyEventController::class, 'store']);
    Route::get('event-show/{id}', [AgencyEventController::class, 'show']);
    Route::put('event-update/{id}', [AgencyEventController::class, 'update']);
    Route::delete('event-destroy/{id}', [AgencyEventController::class, 'destroy']);

    // agency status template (get-process-flow) management route
    Route::get('get-process-flow', [StatusTemplateController::class, 'getProcessFlow']);
    Route::post('status-template-store', [StatusTemplateController::class, 'store']);
    Route::get('status-template-show/{id}', [StatusTemplateController::class, 'show']);
    Route::put('status-template-update/{id}', [StatusTemplateController::class, 'update']);
    Route::delete('status-template-destroy/{id}', [StatusTemplateController::class, 'destroy']);
    // status template create new status after
    Route::post('statuses/store-after', [StatusTemplateController::class, 'storeAfter']);

    // Notifications
    Route::get('notifications', [AgencyNotificationController::class, 'index']);
    Route::put('notifications/mark-all-read', [AgencyNotificationController::class, 'markAllRead']);
    Route::put('notifications/{id}/read', [AgencyNotificationController::class, 'markRead']);
    Route::delete('notifications/{id}', [AgencyNotificationController::class, 'destroy']);

    // document template management route
    Route::get('document-templates', [DocumentTemplateController::class, 'index']);
    Route::post('document-templates/store', [DocumentTemplateController::class, 'store']);
    Route::get('document-templates/show/{id}', [DocumentTemplateController::class, 'show']);
    Route::post('document-templates/update/{id}', [DocumentTemplateController::class, 'update']);
    Route::delete('document-templates/delete/{id}', [DocumentTemplateController::class, 'destroy']);
    Route::post('document-templates/duplicate/{id}', [DocumentTemplateController::class, 'duplicate']);
    Route::get('document-templates/download/{id}', [DocumentTemplateController::class, 'download']);

    // Client Status
    Route::get('/statuses', [StatusController::class, 'index']);
    Route::post('/status-store', [StatusController::class, 'store']);
    Route::get('/status-edit/{id}', [StatusController::class, 'edit']);
    Route::put('/status-update/{id}', [StatusController::class, 'update']);
    Route::patch('/status-serial-update/{id}', [StatusController::class, 'serial']);
    Route::delete('/status-delete/{id}', [StatusController::class, 'destroy']);

    // Dynamic form builder
    Route::get('/forms', [FormController::class, 'index']);
    Route::post('/forms', [FormController::class, 'store']);
    Route::get('/forms/{slug}', [FormController::class, 'show']);
    Route::put('/forms/{id}', [FormController::class, 'update']);
    Route::patch('/forms/{id}/status', [FormController::class, 'toggleStatus']);
    Route::patch('/forms/{id}/fields/{key}/serial', [FormController::class, 'reorderField']);
    Route::post('/forms/{slug}/submit', [FormController::class, 'submit']);

    // Legacy flat form fields
    Route::post('/form-fields', [FormFieldController::class, 'store']);
    Route::post('/form-fields/reorder', [FormFieldController::class, 'reorder']);

    // Client Registration
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/status-statistics', [ClientController::class, 'statusStatistics']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::post('/clients/{id}', [ClientController::class, 'updateProfile']);
    Route::patch('/clients/{id}/status', [ClientController::class, 'updateStatus']);
    Route::get('/clients/{id}/lists', [ClientController::class, 'lists']);
    Route::patch('/clients/{id}/lists', [ClientController::class, 'updateLists']);

    // Client detail "Documents" tab
    Route::get('/clients/{id}/documents', [ClientDocumentController::class, 'index']);
    Route::get('/clients/{id}/documents/templates', [ClientDocumentController::class, 'availableTemplates']);
    Route::post('/clients/{id}/documents', [ClientDocumentController::class, 'store']);
    Route::get('/clients/{id}/documents/{templateId}', [ClientDocumentController::class, 'show']);
    Route::get('/clients/{id}/documents/{templateId}/download', [ClientDocumentController::class, 'downloadPdf']);
    Route::patch('/clients/{id}/documents/{templateId}/toggle', [ClientDocumentController::class, 'toggleActive']);

    // Client detail "Notes" tab
    Route::get('/clients/{id}/notes', [ClientNoteController::class, 'index']);
    Route::get('/clients/{id}/notes/admins', [ClientNoteController::class, 'availableAdmins']);
    Route::post('/clients/{id}/notes', [ClientNoteController::class, 'store']);
    Route::delete('/clients/{id}/notes', [ClientNoteController::class, 'destroy']);
    Route::get('/clients/{id}/notes/{noteId}', [ClientNoteController::class, 'show']);
    Route::patch('/clients/{id}/notes/{noteId}', [ClientNoteController::class, 'update']);
    Route::patch('/clients/{id}/notes/{noteId}/pin', [ClientNoteController::class, 'togglePin']);

    // Client detail "Password" tab
    Route::get('/clients/{id}/password', [ClientPasswordController::class, 'show']);
    Route::patch('/clients/{id}/password', [ClientPasswordController::class, 'updatePassword']);
    Route::post('/clients/{id}/secondary-logins', [ClientPasswordController::class, 'storeSecondaryLogin']);
    Route::delete('/clients/{id}/secondary-logins/{loginId}', [ClientPasswordController::class, 'destroySecondaryLogin']);

    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::get('/candidates/status-statistics', [CandidateController::class, 'statusStatistics']);
    Route::post('/candidates', [CandidateController::class, 'store']);
    Route::get('/candidates/{id}', [CandidateController::class, 'show']);
    Route::patch('/candidates/{id}/status', [CandidateController::class, 'updateStatus']);
    Route::get('/candidates/{id}/lists', [CandidateController::class, 'lists']);
    Route::patch('/candidates/{id}/lists', [CandidateController::class, 'updateLists']);

    // Candidate detail "Notes" tab
    Route::get('/candidates/{id}/notes', [CandidateNoteController::class, 'index']);
    Route::get('/candidates/{id}/notes/admins', [CandidateNoteController::class, 'availableAdmins']);
    Route::post('/candidates/{id}/notes', [CandidateNoteController::class, 'store']);
    Route::delete('/candidates/{id}/notes', [CandidateNoteController::class, 'destroy']);
    Route::get('/candidates/{id}/notes/{noteId}', [CandidateNoteController::class, 'show']);
    Route::patch('/candidates/{id}/notes/{noteId}', [CandidateNoteController::class, 'update']);
    Route::patch('/candidates/{id}/notes/{noteId}/pin', [CandidateNoteController::class, 'togglePin']);

    // Candidate detail "Documents" tab
    Route::get('/candidates/{id}/documents', [AgencyCandidateDocumentController::class, 'index']);
    Route::post('/candidates/{id}/documents/required/{key}', [AgencyCandidateDocumentController::class, 'uploadRequired']);
    Route::post('/candidates/{id}/documents/additional', [AgencyCandidateDocumentController::class, 'uploadAdditional']);
    Route::patch('/candidates/{id}/documents/additional/{documentId}', [AgencyCandidateDocumentController::class, 'updateAdditional']);
    Route::delete('/candidates/{id}/documents/{documentId}', [AgencyCandidateDocumentController::class, 'destroy']);

    // Candidate detail "Profile" tab
    Route::get('/candidates/{id}/profile', [CandidateProfileController::class, 'show']);
    Route::patch('/candidates/{id}/profile', [CandidateProfileController::class, 'update']);

    // Candidate detail "Password" tab
    Route::get('/candidates/{id}/password', [CandidatePasswordController::class, 'show']);
    Route::patch('/candidates/{id}/password', [CandidatePasswordController::class, 'updatePassword']);
    Route::post('/candidates/{id}/secondary-logins', [CandidatePasswordController::class, 'storeSecondaryLogin']);
    Route::delete('/candidates/{id}/secondary-logins/{loginId}', [CandidatePasswordController::class, 'destroySecondaryLogin']);
});
