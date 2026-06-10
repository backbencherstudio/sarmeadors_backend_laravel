<?php

use App\Http\Controllers\Agency\AgencyNoteController;
use App\Http\Controllers\Agency\AgencySettingsController;
use App\Http\Controllers\Agency\JobMessageController as AgencyJobMessageController;
use App\Http\Controllers\Agency\LongTermJobApplicationController as AgencyLongTermJobApplicationController;
use App\Http\Controllers\Agency\LongTermJobAttendanceController as AgencyLongTermJobAttendanceController;
use App\Http\Controllers\Agency\LongTermJobController as AgencyLongTermJobController;
use App\Http\Controllers\Agency\LongTermJobRefundController as AgencyLongTermJobRefundController;
use App\Http\Controllers\Agency\MessageTemplateController;
use App\Http\Controllers\Agency\ShortTermJobApplicationController as AgencyShortTermJobApplicationController;
use App\Http\Controllers\Agency\ShortTermJobAttendanceController as AgencyShortTermJobAttendanceController;
use App\Http\Controllers\Agency\ShortTermJobController as AgencyShortTermJobController;
use App\Http\Controllers\Agency\ShortTermJobRefundController as AgencyShortTermJobRefundController;
use App\Http\Controllers\Candidate\CandidateAvailabilityController;
use App\Http\Controllers\Candidate\CandidateClientsController;
use App\Http\Controllers\Candidate\CandidateDashboardController;
use App\Http\Controllers\Candidate\CandidateDocumentController;
use App\Http\Controllers\Candidate\CandidateInterviewController;
use App\Http\Controllers\Candidate\CandidateNotificationController;
use App\Http\Controllers\Candidate\CandidateProfileController;
use App\Http\Controllers\Candidate\JobMessageController as CandidateJobMessageController;
use App\Http\Controllers\Candidate\LongTermJobApplicationController as CandidateLongTermJobApplicationController;
use App\Http\Controllers\Candidate\LongTermJobAttendanceController as CandidateLongTermJobAttendanceController;
use App\Http\Controllers\Candidate\LongTermJobController as CandidateLongTermJobController;
use App\Http\Controllers\Candidate\LongTermJobReviewController as CandidateLongTermJobReviewController;
use App\Http\Controllers\Candidate\ShortTermJobApplicationController as CandidateShortTermJobApplicationController;
use App\Http\Controllers\Candidate\ShortTermJobAttendanceController as CandidateShortTermJobAttendanceController;
use App\Http\Controllers\Candidate\ShortTermJobController as CandidateShortTermJobController;
use App\Http\Controllers\Candidate\ShortTermJobReviewController as CandidateShortTermJobReviewController;
use App\Http\Controllers\Client\ClientCandidateController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientDocumentController;
use App\Http\Controllers\Client\ClientInterviewController;
use App\Http\Controllers\Client\ClientLocationController;
use App\Http\Controllers\Client\ClientNotificationController;
use App\Http\Controllers\Client\ClientPaymentController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\JobMessageController as ClientJobMessageController;
use App\Http\Controllers\Client\LongTermJobApplicationController as ClientLongTermJobApplicationController;
use App\Http\Controllers\Client\LongTermJobAttendanceController as ClientLongTermJobAttendanceController;
use App\Http\Controllers\Client\LongTermJobController;
use App\Http\Controllers\Client\LongTermJobPaymentController as ClientLongTermJobPaymentController;
use App\Http\Controllers\Client\LongTermJobRefundController as ClientLongTermJobRefundController;
use App\Http\Controllers\Client\LongTermJobReviewController as ClientLongTermJobReviewController;
use App\Http\Controllers\Client\ShortTermJobApplicationController as ClientShortTermJobApplicationController;
use App\Http\Controllers\Client\ShortTermJobController;
use App\Http\Controllers\Client\ShortTermJobPaymentController as ClientShortTermJobPaymentController;
use App\Http\Controllers\Client\ShortTermJobRefundController as ClientShortTermJobRefundController;
use App\Http\Controllers\Client\ShortTermJobReviewController as ClientShortTermJobReviewController;
use App\Http\Controllers\Messages\ShortTermJobMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->prefix('admin')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->prefix('admin')->group(function () {});

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

    // Agency long-term refund request management
    Route::get('jobs/long-term/{longTermJob}/refund-request', [AgencyLongTermJobRefundController::class, 'show']);
    Route::put('jobs/long-term/{longTermJob}/refund-request/approve', [AgencyLongTermJobRefundController::class, 'approve']);
    Route::put('jobs/long-term/{longTermJob}/refund-request/reject', [AgencyLongTermJobRefundController::class, 'reject']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->prefix('agency')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->prefix('client')->group(function () {
    // Dashboard
    Route::get('dashboard', [ClientDashboardController::class, 'index']);

    // Profile management
    Route::get('profile', [ClientProfileController::class, 'show']);
    Route::put('profile', [ClientProfileController::class, 'update']);
    Route::put('profile/password', [ClientProfileController::class, 'updatePassword']);
    Route::delete('profile', [ClientProfileController::class, 'destroy']);

    // Locations (dropdown source for profile / job address)
    Route::get('locations', [ClientLocationController::class, 'index']);

    // Notifications
    Route::get('notifications', [ClientNotificationController::class, 'index']);
    Route::put('notifications/mark-all-read', [ClientNotificationController::class, 'markAllRead']);
    Route::put('notifications/{id}/read', [ClientNotificationController::class, 'markRead']);
    Route::delete('notifications/{id}', [ClientNotificationController::class, 'destroy']);

    // Documents / Agreements
    Route::get('documents', [ClientDocumentController::class, 'index']);
    Route::get('documents/{document}', [ClientDocumentController::class, 'show']);
    Route::post('documents/{document}/sign', [ClientDocumentController::class, 'sign']);
    Route::get('documents/{document}/download', [ClientDocumentController::class, 'download']);

    // Aggregated payments hub
    Route::get('payments', [ClientPaymentController::class, 'index']);
    Route::get('payments/invoices', [ClientPaymentController::class, 'invoices']);
    Route::get('payments/invoices/{invoice}', [ClientPaymentController::class, 'showInvoice']);
    Route::post('payments/invoices/{invoice}/pay', [ClientPaymentController::class, 'payInvoice']);
    Route::get('payments/{paymentId}/download', [ClientPaymentController::class, 'download']);

    // Candidates browsing
    Route::get('candidates/discover', [ClientCandidateController::class, 'discover']);
    Route::get('candidates', [ClientCandidateController::class, 'index']);
    Route::get('candidates/{candidate}', [ClientCandidateController::class, 'show']);

    // Candidate reviews (not tied to a single job)
    Route::get('candidates/{candidate}/reviews', [ClientCandidateController::class, 'reviews']);
    Route::post('candidates/{candidate}/reviews', [ClientCandidateController::class, 'storeReview']);

    // Hire / Interview requests from candidate detail page
    Route::post('candidates/{candidate}/hire-request', [ClientCandidateController::class, 'hireRequest']);
    Route::post('candidates/{candidate}/interview-request', [ClientCandidateController::class, 'interviewRequest']);

    // Interviews management
    Route::get('interviews', [ClientInterviewController::class, 'index']);
    Route::get('interviews/{interview}', [ClientInterviewController::class, 'show']);
    Route::put('interviews/{interview}/reschedule', [ClientInterviewController::class, 'reschedule']);
    Route::delete('interviews/{interview}', [ClientInterviewController::class, 'cancel']);

    // Check agency payment settings before showing the job form
    Route::get('jobs/short-term/payment-check', [AgencySettingsController::class, 'clientShortTermPaymentCheck']);

    // Short-term jobs
    Route::get('jobs/short-term', [ShortTermJobController::class, 'index']);
    Route::post('jobs/short-term', [ShortTermJobController::class, 'store']);
    Route::get('jobs/short-term/{shortTermJob}', [ShortTermJobController::class, 'show']);
    Route::put('jobs/short-term/{shortTermJob}', [ShortTermJobController::class, 'update']);
    Route::delete('jobs/short-term/{shortTermJob}', [ShortTermJobController::class, 'destroy']);
    Route::put('jobs/short-term/{shortTermJob}/cancel', [ShortTermJobController::class, 'cancel']);
    Route::put('jobs/short-term/{shortTermJob}/resubmit', [ShortTermJobController::class, 'resubmit']);
    Route::post('jobs/short-term/{shortTermJob}/broadcast', [ShortTermJobController::class, 'broadcastRequest']);

    // Client short-term applicants
    Route::get('jobs/short-term/{shortTermJob}/applicants', [ClientShortTermJobApplicationController::class, 'index']);
    Route::get('jobs/short-term/{shortTermJob}/applicants/{applicationId}', [ClientShortTermJobApplicationController::class, 'show']);
    Route::get('jobs/short-term/{shortTermJob}/applicants/{applicationId}/candidate-reviews', [ClientShortTermJobApplicationController::class, 'candidateReviews']);
    Route::post('jobs/short-term/{shortTermJob}/applicants/{applicationId}/hire', [ClientShortTermJobApplicationController::class, 'hire']);

    // Client short-term invoice & payments
    Route::get('jobs/short-term/{shortTermJob}/invoice', [ClientShortTermJobPaymentController::class, 'invoice']);
    Route::get('jobs/short-term/{shortTermJob}/payments', [ClientShortTermJobPaymentController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/payments', [ClientShortTermJobPaymentController::class, 'store']);
    Route::get('jobs/short-term/{shortTermJob}/payments/{paymentId}/download', [ClientShortTermJobPaymentController::class, 'download']);

    // Client short-term reviews
    Route::get('jobs/short-term/{shortTermJob}/reviews', [ClientShortTermJobReviewController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/reviews', [ClientShortTermJobReviewController::class, 'store']);

    // Client short-term refund
    Route::get('jobs/short-term/{shortTermJob}/refund-request', [ClientShortTermJobRefundController::class, 'show']);
    Route::post('jobs/short-term/{shortTermJob}/refund-request', [ClientShortTermJobRefundController::class, 'store']);

    // Client short-term messages
    Route::get('jobs/short-term/{shortTermJob}/messages', [ShortTermJobMessageController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/messages', [ShortTermJobMessageController::class, 'store']);
    Route::get('jobs/short-term/{shortTermJob}/messages/unread-counts', [ShortTermJobMessageController::class, 'unreadCounts']);

    // Long-term jobs
    Route::get('jobs/long-term', [LongTermJobController::class, 'index']);
    Route::post('jobs/long-term', [LongTermJobController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}', [LongTermJobController::class, 'show']);
    Route::put('jobs/long-term/{longTermJob}', [LongTermJobController::class, 'update']);
    Route::delete('jobs/long-term/{longTermJob}', [LongTermJobController::class, 'destroy']);
    Route::put('jobs/long-term/{longTermJob}/cancel', [LongTermJobController::class, 'cancel']);
    Route::put('jobs/long-term/{longTermJob}/resubmit', [LongTermJobController::class, 'resubmit']);
    Route::post('jobs/long-term/{longTermJob}/broadcast', [LongTermJobController::class, 'broadcastRequest']);

    // Client long-term attendance calendar (read-only)
    Route::get('jobs/long-term/{longTermJob}/attendance', [ClientLongTermJobAttendanceController::class, 'calendar']);

    // Client long-term applicants (marketplace jobs)
    Route::get('jobs/long-term/{longTermJob}/applicants', [ClientLongTermJobApplicationController::class, 'index']);
    Route::get('jobs/long-term/{longTermJob}/applicants/{application}', [ClientLongTermJobApplicationController::class, 'show']);
    Route::get('jobs/long-term/{longTermJob}/applicants/{application}/candidate-reviews', [ClientLongTermJobApplicationController::class, 'candidateReviews']);
    Route::post('jobs/long-term/{longTermJob}/applicants/{application}/interview', [ClientLongTermJobApplicationController::class, 'interview']);
    Route::post('jobs/long-term/{longTermJob}/applicants/{application}/hire', [ClientLongTermJobApplicationController::class, 'hire']);

    // Client long-term invoice & payments
    Route::get('jobs/long-term/{longTermJob}/invoice', [ClientLongTermJobPaymentController::class, 'invoice']);
    Route::get('jobs/long-term/{longTermJob}/payments', [ClientLongTermJobPaymentController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/payments', [ClientLongTermJobPaymentController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/payments/{payment}/download', [ClientLongTermJobPaymentController::class, 'download']);

    // Client long-term reviews
    Route::get('jobs/long-term/{longTermJob}/reviews', [ClientLongTermJobReviewController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/reviews', [ClientLongTermJobReviewController::class, 'store']);

    // Client long-term refund
    Route::get('jobs/long-term/{longTermJob}/refund-request', [ClientLongTermJobRefundController::class, 'show']);
    Route::post('jobs/long-term/{longTermJob}/refund-request', [ClientLongTermJobRefundController::class, 'store']);

    // Client long-term job messages (client thread only)
    Route::get('jobs/long-term/{longTermJob}/messages', [ClientJobMessageController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/messages', [ClientJobMessageController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/messages/unread-counts', [ClientJobMessageController::class, 'unreadCounts']);
});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->prefix('candidate')->group(function () {
    // Dashboard
    Route::get('dashboard', [CandidateDashboardController::class, 'index']);

    // Profile management
    Route::get('profile', [CandidateProfileController::class, 'show']);
    Route::put('profile', [CandidateProfileController::class, 'update']);
    Route::put('profile/password', [CandidateProfileController::class, 'updatePassword']);
    Route::delete('profile', [CandidateProfileController::class, 'destroy']);

    // Notifications
    Route::get('notifications', [CandidateNotificationController::class, 'index']);
    Route::put('notifications/mark-all-read', [CandidateNotificationController::class, 'markAllRead']);
    Route::put('notifications/{id}/read', [CandidateNotificationController::class, 'markRead']);
    Route::delete('notifications/{id}', [CandidateNotificationController::class, 'destroy']);

    // Availability
    Route::get('availability', [CandidateAvailabilityController::class, 'show']);
    Route::put('availability', [CandidateAvailabilityController::class, 'update']);
    Route::get('availability/unavailabilities', [CandidateAvailabilityController::class, 'indexUnavailabilities']);
    Route::post('availability/unavailabilities', [CandidateAvailabilityController::class, 'storeUnavailability']);
    Route::delete('availability/unavailabilities/{unavailability}', [CandidateAvailabilityController::class, 'destroyUnavailability']);

    // My Clients
    Route::get('clients', [CandidateClientsController::class, 'index']);
    Route::get('clients/{client}', [CandidateClientsController::class, 'show']);

    // Documents
    Route::get('documents', [CandidateDocumentController::class, 'index']);
    Route::get('documents/agreements/{documentTemplate}', [CandidateDocumentController::class, 'showAgreement']);
    Route::post('documents/agreements/{documentTemplate}/sign', [CandidateDocumentController::class, 'signAgreement']);
    Route::post('documents/required/{documentKey}/upload', [CandidateDocumentController::class, 'uploadRequired']);
    Route::delete('documents/required/{documentKey}', [CandidateDocumentController::class, 'deleteRequired']);
    Route::post('documents/additional', [CandidateDocumentController::class, 'storeAdditional']);
    Route::delete('documents/additional/{candidateDocument}', [CandidateDocumentController::class, 'destroyAdditional']);
    Route::get('documents/files/{candidateDocument}/download', [CandidateDocumentController::class, 'downloadUploaded']);

    // Interviews
    Route::get('interviews', [CandidateInterviewController::class, 'index']);
    Route::get('interviews/{interview}', [CandidateInterviewController::class, 'show']);

    // Candidate short-term jobs (assigned jobs)
    Route::get('jobs/short-term', [CandidateShortTermJobController::class, 'index']);
    Route::get('jobs/short-term/{shortTermJob}', [CandidateShortTermJobController::class, 'show']);

    // Candidate short-term marketplace & applications
    Route::get('jobs/short-term-marketplace', [CandidateShortTermJobApplicationController::class, 'marketplace']);
    Route::get('jobs/short-term-marketplace/{shortTermJob}', [CandidateShortTermJobApplicationController::class, 'showMarketplace']);
    Route::get('jobs/short-term-applications', [CandidateShortTermJobApplicationController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/apply', [CandidateShortTermJobApplicationController::class, 'store']);
    Route::delete('jobs/short-term/{shortTermJob}/apply', [CandidateShortTermJobApplicationController::class, 'destroy']);

    // Candidate short-term attendance (check-in / check-out)
    Route::get('jobs/short-term/{shortTermJob}/attendance', [CandidateShortTermJobAttendanceController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/check-in', [CandidateShortTermJobAttendanceController::class, 'checkIn']);
    Route::post('jobs/short-term/{shortTermJob}/check-out', [CandidateShortTermJobAttendanceController::class, 'checkOut']);

    // Candidate short-term reviews
    Route::get('jobs/short-term/{shortTermJob}/reviews', [CandidateShortTermJobReviewController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/reviews', [CandidateShortTermJobReviewController::class, 'store']);

    // Candidate short-term messages
    Route::get('jobs/short-term/{shortTermJob}/messages', [ShortTermJobMessageController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/messages', [ShortTermJobMessageController::class, 'store']);
    Route::get('jobs/short-term/{shortTermJob}/messages/unread-counts', [ShortTermJobMessageController::class, 'unreadCounts']);

    // Candidate long-term jobs (assigned jobs: list & detail)
    Route::get('jobs/long-term', [CandidateLongTermJobController::class, 'index']);
    Route::get('jobs/long-term/{longTermJob}', [CandidateLongTermJobController::class, 'show']);

    // Candidate marketplace browsing & applications
    Route::get('jobs/long-term-marketplace', [CandidateLongTermJobApplicationController::class, 'marketplace']);
    Route::get('jobs/long-term-marketplace/{longTermJob}', [CandidateLongTermJobApplicationController::class, 'showMarketplace']);
    Route::get('jobs/long-term-applications', [CandidateLongTermJobApplicationController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/apply', [CandidateLongTermJobApplicationController::class, 'store']);
    Route::delete('jobs/long-term/{longTermJob}/apply', [CandidateLongTermJobApplicationController::class, 'destroy']);

    // Candidate long-term attendance (check-in / check-out)
    Route::get('jobs/long-term/{longTermJob}/attendance', [CandidateLongTermJobAttendanceController::class, 'calendar']);
    Route::post('jobs/long-term/{longTermJob}/check-in', [CandidateLongTermJobAttendanceController::class, 'checkIn']);
    Route::post('jobs/long-term/{longTermJob}/check-out', [CandidateLongTermJobAttendanceController::class, 'checkOut']);

    // Candidate long-term reviews
    Route::get('jobs/long-term/{longTermJob}/reviews', [CandidateLongTermJobReviewController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/reviews', [CandidateLongTermJobReviewController::class, 'store']);

    // Candidate long-term job messages (candidate thread only)
    Route::get('jobs/long-term/{longTermJob}/messages', [CandidateJobMessageController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/messages', [CandidateJobMessageController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/messages/unread-counts', [CandidateJobMessageController::class, 'unreadCounts']);
});
