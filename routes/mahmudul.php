<?php

use App\Http\Controllers\Agency\AgencyNoteController;
use App\Http\Controllers\Agency\MessageTemplateController;
use Illuminate\Http\Request;
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
    Route::get('message_template/show/{agency_note}', [MessageTemplateController::class, 'show']);
    Route::put('message_template/{agency_note}', [AgencyNoteController::class, 'update']);
    Route::put('message_template/pin_note/{agency_note}', [MessageTemplateController::class, 'pin_note']);
    Route::put('message_template/mark_read/{agency_note}', [MessageTemplateController::class, 'mark_read']);
    Route::delete('message_template', [MessageTemplateController::class, 'destroy']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->prefix('agency')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->prefix('client')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->prefix('candidate')->group(function () {});
