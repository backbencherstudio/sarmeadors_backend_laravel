<?php

use App\Http\Controllers\Agency\AgencySettingsController;
use App\Http\Controllers\Client\CandidateMessageController as ClientCandidateMessageController;
use App\Http\Controllers\Client\ClientCandidateController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientDocumentController;
use App\Http\Controllers\Client\ClientFormController;
use App\Http\Controllers\Client\ClientInterviewController;
use App\Http\Controllers\Client\ClientLocationController;
use App\Http\Controllers\Client\ClientMessageInboxController;
use App\Http\Controllers\Client\ClientNotificationController;
use App\Http\Controllers\Client\ClientPaymentController;
use App\Http\Controllers\Client\ClientPaymentMethodController;
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

Route::middleware(['subdomain', 'auth:api', 'role:client'])->prefix('client')->group(function () {
    // Dashboard
    Route::get('dashboard', [ClientDashboardController::class, 'index']);

    // Profile management
    Route::get('profile', [ClientProfileController::class, 'show']);
    Route::post('profile', [ClientProfileController::class, 'update']);
    Route::put('profile/password', [ClientProfileController::class, 'updatePassword']);
    Route::delete('profile', [ClientProfileController::class, 'destroy']);

    // Locations (dropdown source for profile / job address)
    Route::get('locations', [ClientLocationController::class, 'index']);

    // Messages inbox (dashboard chat: admin & candidate tabs)
    Route::get('messages', [ClientMessageInboxController::class, 'index']);

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

    // Saved payment methods (cards stored for future bookings)
    Route::get('payment-methods', [ClientPaymentMethodController::class, 'index']);
    Route::delete('payment-methods/{paymentMethod}', [ClientPaymentMethodController::class, 'destroy']);

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

    // My Candidates link (status / notes)
    Route::put('candidates/{candidate}/link', [ClientCandidateController::class, 'updateLink']);

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

    // Agency-built long-term job application forms
    Route::get('forms', [ClientFormController::class, 'index']);
    Route::get('forms/{slug}', [ClientFormController::class, 'show']);
    Route::post('forms/{slug}/submit', [ClientFormController::class, 'submit']);

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

    // Client <-> assigned candidate direct messages
    Route::get('jobs/long-term/{longTermJob}/candidate-messages', [ClientCandidateMessageController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/candidate-messages', [ClientCandidateMessageController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/candidate-messages/unread-counts', [ClientCandidateMessageController::class, 'unreadCounts']);
});
