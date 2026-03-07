<?php

use App\Http\Controllers\Agency\ClientChecklistController;
use App\Http\Controllers\Agency\ClientLocationController;
use App\Http\Controllers\Agency\ClientTagController;
use App\Http\Controllers\Agency\ClientTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:api')->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->group(function () {

    //client type management route
    Route::get('client-types', [ClientTypeController::class, 'index']);
    Route::get('client-type/{id}', [ClientTypeController::class, 'show']);
    Route::post('client-type-store', [ClientTypeController::class, 'store']);
    Route::put('client-type-update/{id}', [ClientTypeController::class, 'update']);
    Route::put('client-type-bulk-update', [ClientTypeController::class, 'bulkUpdate']);
    Route::delete('client-type-destroy/{id}', [ClientTypeController::class, 'destroy']);

    //client location management route
    Route::get('client-locations', [ClientLocationController::class, 'index']);
    Route::get('client-location/{id}', [ClientLocationController::class, 'show']);
    Route::post('client-location-store', [ClientLocationController::class, 'store']);
    Route::put('client-location-update/{id}', [ClientLocationController::class, 'update']);
    Route::put('client-location-bulk-update', [ClientLocationController::class, 'bulkUpdate']);
    Route::delete('client-location-destroy/{id}', [ClientLocationController::class, 'destroy']);

    //client checklist management route
    Route::get('client-checklist', [ClientChecklistController::class, 'index']);
    Route::get('client-checklist/{id}', [ClientChecklistController::class, 'show']);
    Route::post('client-checklist-store', [ClientChecklistController::class, 'store']);
    Route::put('client-checklist-update/{id}', [ClientChecklistController::class, 'update']);
    Route::put('client-checklist-bulk-update', [ClientChecklistController::class, 'bulkUpdate']);
    Route::delete('client-checklist-destroy/{id}', [ClientChecklistController::class, 'destroy']);

    //client tags management route
    Route::get('client-tags', [ClientTagController::class, 'index']);
    Route::get('client-tag/{id}', [ClientTagController::class, 'show']);
    Route::post('client-tag-store', [ClientTagController::class, 'store']);
    Route::put('client-tag-update/{id}', [ClientTagController::class, 'update']);
    Route::put('client-tag-bulk-update', [ClientTagController::class, 'bulkUpdate']);
    Route::delete('client-tag-destroy/{id}', [ClientTagController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

    //client type management route
    Route::get('client-types', [ClientTypeController::class, 'index']);
    Route::get('client-type/{id}', [ClientTypeController::class, 'show']);
    Route::post('client-type-store', [ClientTypeController::class, 'store']);
    Route::put('client-type-update/{id}', [ClientTypeController::class, 'update']);
    Route::put('client-type-bulk-update', [ClientTypeController::class, 'bulkUpdate']);
    Route::delete('client-type-destroy/{id}', [ClientTypeController::class, 'destroy']);

    //client location management route
    Route::get('client-locations', [ClientLocationController::class, 'index']);
    Route::get('client-location/{id}', [ClientLocationController::class, 'show']);
    Route::post('client-location-store', [ClientLocationController::class, 'store']);
    Route::put('client-location-update/{id}', [ClientLocationController::class, 'update']);
    Route::put('client-location-bulk-update', [ClientLocationController::class, 'bulkUpdate']);
    Route::delete('client-location-destroy/{id}', [ClientLocationController::class, 'destroy']);

    //client checklist management route
    Route::get('client-checklist', [ClientChecklistController::class, 'index']);
    Route::get('client-checklist/{id}', [ClientChecklistController::class, 'show']);
    Route::post('client-checklist-store', [ClientChecklistController::class, 'store']);
    Route::put('client-checklist-update/{id}', [ClientChecklistController::class, 'update']);
    Route::put('client-checklist-bulk-update', [ClientChecklistController::class, 'bulkUpdate']);
    Route::delete('client-checklist-destroy/{id}', [ClientChecklistController::class, 'destroy']);

    //client tags management route
    Route::get('client-tags', [ClientTagController::class, 'index']);
    Route::get('client-tag/{id}', [ClientTagController::class, 'show']);
    Route::post('client-tag-store', [ClientTagController::class, 'store']);
    Route::put('client-tag-update/{id}', [ClientTagController::class, 'update']);
    Route::put('client-tag-bulk-update', [ClientTagController::class, 'bulkUpdate']);
    Route::delete('client-tag-destroy/{id}', [ClientTagController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {

});
