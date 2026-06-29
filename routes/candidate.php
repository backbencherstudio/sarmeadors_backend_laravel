<?php

use App\Http\Controllers\Candidate\CandidateAvailabilityController;
use App\Http\Controllers\Candidate\CandidateClientReportController;
use App\Http\Controllers\Candidate\CandidateClientsController;
use App\Http\Controllers\Candidate\CandidateDashboardController;
use App\Http\Controllers\Candidate\CandidateDocumentController;
use App\Http\Controllers\Candidate\CandidateInterviewController;
use App\Http\Controllers\Candidate\CandidateMessageInboxController;
use App\Http\Controllers\Candidate\CandidateMyJobController;
use App\Http\Controllers\Candidate\CandidateNotificationController;
use App\Http\Controllers\Candidate\CandidateProfileController;
use App\Http\Controllers\Candidate\CandidateRequestedJobController;
use App\Http\Controllers\Candidate\ClientMessageController as CandidateClientMessageController;
use App\Http\Controllers\Candidate\JobMessageController as CandidateJobMessageController;
use App\Http\Controllers\Candidate\LongTermJobApplicationController as CandidateLongTermJobApplicationController;
use App\Http\Controllers\Candidate\LongTermJobAttendanceController as CandidateLongTermJobAttendanceController;
use App\Http\Controllers\Candidate\LongTermJobController as CandidateLongTermJobController;
use App\Http\Controllers\Candidate\LongTermJobReviewController as CandidateLongTermJobReviewController;
use App\Http\Controllers\Candidate\ShortTermJobApplicationController as CandidateShortTermJobApplicationController;
use App\Http\Controllers\Candidate\ShortTermJobAttendanceController as CandidateShortTermJobAttendanceController;
use App\Http\Controllers\Candidate\ShortTermJobController as CandidateShortTermJobController;
use App\Http\Controllers\Candidate\ShortTermJobReviewController as CandidateShortTermJobReviewController;
use App\Http\Controllers\Messages\ShortTermJobMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->prefix('candidate')->group(function () {
    // Dashboard
    Route::get('dashboard', [CandidateDashboardController::class, 'index']);

    // Profile management
    Route::get('profile', [CandidateProfileController::class, 'show']);
    Route::post('profile', [CandidateProfileController::class, 'update']);
    Route::put('profile/password', [CandidateProfileController::class, 'updatePassword']);
    Route::delete('profile', [CandidateProfileController::class, 'destroy']);

    // Messages inbox (dashboard chat: admin & client tabs)
    Route::get('messages', [CandidateMessageInboxController::class, 'index']);

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

    // Requested jobs from clients
    Route::get('jobs/requested', [CandidateRequestedJobController::class, 'index']);
    Route::get('jobs/requested/{candidateJobRequest}', [CandidateRequestedJobController::class, 'show']);
    Route::put('jobs/requested/{candidateJobRequest}/approve', [CandidateRequestedJobController::class, 'approve']);
    Route::put('jobs/requested/{candidateJobRequest}/reject', [CandidateRequestedJobController::class, 'reject']);

    // My jobs unified calendar/list view
    Route::get('jobs', [CandidateMyJobController::class, 'index']);

    // Candidate short-term jobs (assigned jobs)
    Route::get('jobs/short-term', [CandidateShortTermJobController::class, 'index']);
    Route::get('jobs/short-term/{shortTermJob}', [CandidateShortTermJobController::class, 'show']);

    // Candidate short-term marketplace & applications
    Route::get('jobs/short-term-marketplace', [CandidateShortTermJobApplicationController::class, 'marketplace']);
    Route::get('jobs/short-term-marketplace/{shortTermJob}', [CandidateShortTermJobApplicationController::class, 'showMarketplace']);
    Route::get('jobs/short-term-applications', [CandidateShortTermJobApplicationController::class, 'index']);
    Route::get('jobs/short-term-applications/{shortTermJob}', [CandidateShortTermJobApplicationController::class, 'show']);
    Route::post('jobs/short-term/{shortTermJob}/apply', [CandidateShortTermJobApplicationController::class, 'store']);
    Route::delete('jobs/short-term/{shortTermJob}/apply', [CandidateShortTermJobApplicationController::class, 'destroy']);

    // Candidate short-term attendance (check-in / check-out)
    Route::get('jobs/short-term/{shortTermJob}/attendance', [CandidateShortTermJobAttendanceController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/check-in', [CandidateShortTermJobAttendanceController::class, 'checkIn']);
    Route::post('jobs/short-term/{shortTermJob}/check-out', [CandidateShortTermJobAttendanceController::class, 'checkOut']);

    // Candidate short-term invoice (earnings breakdown)
    Route::get('jobs/short-term/{shortTermJob}/invoice', [CandidateShortTermJobController::class, 'invoice']);

    // Candidate short-term reviews
    Route::get('jobs/short-term/{shortTermJob}/reviews', [CandidateShortTermJobReviewController::class, 'index']);
    Route::post('jobs/short-term/{shortTermJob}/reviews', [CandidateShortTermJobReviewController::class, 'store']);
    Route::post('jobs/short-term/{shortTermJob}/client-report', [CandidateClientReportController::class, 'reportShortTerm']);

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
    Route::get('jobs/long-term-applications/{application}', [CandidateLongTermJobApplicationController::class, 'show']);
    Route::post('jobs/long-term/{longTermJob}/apply', [CandidateLongTermJobApplicationController::class, 'store']);
    Route::delete('jobs/long-term/{longTermJob}/apply', [CandidateLongTermJobApplicationController::class, 'destroy']);

    // Candidate long-term attendance (check-in / check-out)
    Route::get('jobs/long-term/{longTermJob}/attendance', [CandidateLongTermJobAttendanceController::class, 'calendar']);
    Route::post('jobs/long-term/{longTermJob}/check-in', [CandidateLongTermJobAttendanceController::class, 'checkIn']);
    Route::post('jobs/long-term/{longTermJob}/check-out', [CandidateLongTermJobAttendanceController::class, 'checkOut']);

    // Candidate long-term invoice (earnings breakdown)
    Route::get('jobs/long-term/{longTermJob}/invoice', [CandidateLongTermJobController::class, 'invoice']);

    // Candidate long-term reviews
    Route::get('jobs/long-term/{longTermJob}/reviews', [CandidateLongTermJobReviewController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/reviews', [CandidateLongTermJobReviewController::class, 'store']);
    Route::post('jobs/long-term/{longTermJob}/client-report', [CandidateClientReportController::class, 'reportLongTerm']);

    // Candidate long-term job messages (candidate thread only)
    Route::get('jobs/long-term/{longTermJob}/messages', [CandidateJobMessageController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/messages', [CandidateJobMessageController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/messages/unread-counts', [CandidateJobMessageController::class, 'unreadCounts']);

    // Candidate <-> client direct messages (assigned jobs)
    Route::get('jobs/long-term/{longTermJob}/client-messages', [CandidateClientMessageController::class, 'index']);
    Route::post('jobs/long-term/{longTermJob}/client-messages', [CandidateClientMessageController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}/client-messages/unread-counts', [CandidateClientMessageController::class, 'unreadCounts']);
});
