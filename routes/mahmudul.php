<?php

use App\Http\Controllers\Agency\AgencyNoteController;
use App\Http\Controllers\Agency\AgencySettingsController;
use App\Http\Controllers\Agency\LongTermJobAttendanceController as AgencyLongTermJobAttendanceController;
use App\Http\Controllers\Agency\LongTermJobController as AgencyLongTermJobController;
use App\Http\Controllers\Agency\MessageTemplateController;
use App\Http\Controllers\Agency\ShortTermJobController as AgencyShortTermJobController;
use App\Http\Controllers\Candidate\LongTermJobAttendanceController as CandidateLongTermJobAttendanceController;
use App\Http\Controllers\Client\LongTermJobAttendanceController as ClientLongTermJobAttendanceController;
use App\Http\Controllers\Client\LongTermJobController;
use App\Http\Controllers\Client\ShortTermJobController;
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
    Route::put('agency_notes/mark_read/{agency_note}', [AgencyNoteController::class, 'mark_read']);
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

    // Agency long-term job management
    Route::get('jobs/long-term', [AgencyLongTermJobController::class, 'index']);
    Route::get('jobs/long-term/{longTermJob}', [AgencyLongTermJobController::class, 'show']);
    Route::put('jobs/long-term/{longTermJob}/approve', [AgencyLongTermJobController::class, 'approve']);
    Route::put('jobs/long-term/{longTermJob}/reject', [AgencyLongTermJobController::class, 'reject']);
    Route::put('jobs/long-term/{longTermJob}/assign-nanny', [AgencyLongTermJobAttendanceController::class, 'assignNanny']);

    // Agency long-term attendance calendar & nanny payments
    Route::get('jobs/long-term/{longTermJob}/attendance', [AgencyLongTermJobAttendanceController::class, 'calendar']);
    Route::post('jobs/long-term/{longTermJob}/attendance', [AgencyLongTermJobAttendanceController::class, 'upsert']);
    Route::get('jobs/long-term/{longTermJob}/nanny-payments', [AgencyLongTermJobAttendanceController::class, 'listNannyPayments']);
    Route::post('jobs/long-term/{longTermJob}/nanny-payments', [AgencyLongTermJobAttendanceController::class, 'recordNannyPayment']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->prefix('agency')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->prefix('client')->group(function () {
    // Check agency payment settings before showing the job form
    Route::get('jobs/short-term/payment-check', [AgencySettingsController::class, 'clientShortTermPaymentCheck']);

    // Short-term jobs
    Route::get('jobs/short-term', [ShortTermJobController::class, 'index']);
    Route::post('jobs/short-term', [ShortTermJobController::class, 'store']);
    Route::get('jobs/short-term/{shortTermJob}', [ShortTermJobController::class, 'show']);
    Route::delete('jobs/short-term/{shortTermJob}', [ShortTermJobController::class, 'destroy']);

    // Long-term jobs
    Route::get('jobs/long-term', [LongTermJobController::class, 'index']);
    Route::post('jobs/long-term', [LongTermJobController::class, 'store']);
    Route::get('jobs/long-term/{longTermJob}', [LongTermJobController::class, 'show']);
    Route::delete('jobs/long-term/{longTermJob}', [LongTermJobController::class, 'destroy']);

    // Client long-term attendance calendar (read-only)
    Route::get('jobs/long-term/{longTermJob}/attendance', [ClientLongTermJobAttendanceController::class, 'calendar']);
});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->prefix('candidate')->group(function () {
    // Candidate long-term attendance (check-in / check-out)
    Route::get('jobs/long-term/{longTermJob}/attendance', [CandidateLongTermJobAttendanceController::class, 'calendar']);
    Route::post('jobs/long-term/{longTermJob}/check-in', [CandidateLongTermJobAttendanceController::class, 'checkIn']);
    Route::post('jobs/long-term/{longTermJob}/check-out', [CandidateLongTermJobAttendanceController::class, 'checkOut']);
});
