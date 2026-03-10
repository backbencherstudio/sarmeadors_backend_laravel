<?php

use App\Http\Controllers\Agency\AgencyChecklistController;
use App\Http\Controllers\Agency\AgencyEventController;
use App\Http\Controllers\Agency\AgencyEventTypeController;
use App\Http\Controllers\Agency\AgencyLocationController;
use App\Http\Controllers\Agency\AgencyTagController;
use App\Http\Controllers\Agency\AgencyTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:api')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->group(function () {

    //agency type management route
    Route::get('types', [AgencyTypeController::class, 'index']);
    Route::get('type/{id}', [AgencyTypeController::class, 'show']);
    Route::post('type-store', [AgencyTypeController::class, 'store']);
    Route::put('type-update/{id}', [AgencyTypeController::class, 'update']);
    Route::put('type-bulk-update', [AgencyTypeController::class, 'bulkUpdate']);
    Route::delete('type-destroy/{id}', [AgencyTypeController::class, 'destroy']);

    //agency location management route
    Route::get('locations', [AgencyLocationController::class, 'index']);
    Route::get('location/{id}', [AgencyLocationController::class, 'show']);
    Route::post('location-store', [AgencyLocationController::class, 'store']);
    Route::put('location-update/{id}', [AgencyLocationController::class, 'update']);
    Route::put('location-bulk-update', [AgencyLocationController::class, 'bulkUpdate']);
    Route::delete('location-destroy/{id}', [AgencyLocationController::class, 'destroy']);

    //agency checklist management route
    Route::get('checklist', [AgencyChecklistController::class, 'index']);
    Route::get('checklist/{id}', [AgencyChecklistController::class, 'show']);
    Route::post('checklist-store', [AgencyChecklistController::class, 'store']);
    Route::put('checklist-update/{id}', [AgencyChecklistController::class, 'update']);
    Route::put('checklist-bulk-update', [AgencyChecklistController::class, 'bulkUpdate']);
    Route::delete('checklist-destroy/{id}', [AgencyChecklistController::class, 'destroy']);

    //agency tags management route
    Route::get('tags', [AgencyTagController::class, 'index']);
    Route::get('tag/{id}', [AgencyTagController::class, 'show']);
    Route::post('tag-store', [AgencyTagController::class, 'store']);
    Route::put('tag-update/{id}', [AgencyTagController::class, 'update']);
    Route::put('tag-bulk-update', [AgencyTagController::class, 'bulkUpdate']);
    Route::delete('tag-destroy/{id}', [AgencyTagController::class, 'destroy']);

    //agency event type management route
    Route::get('event-types', [AgencyEventTypeController::class, 'index']);
    Route::get('event-type/{id}', [AgencyEventTypeController::class, 'show']);
    Route::post('event-type-store', [AgencyEventTypeController::class, 'store']);
    Route::put('event-type-update/{id}', [AgencyEventTypeController::class, 'update']);
    Route::put('event-type-bulk-update', [AgencyEventTypeController::class, 'bulkUpdate']);
    Route::delete('event-type-destroy/{id}', [AgencyEventTypeController::class, 'destroy']);

    //agency event management route
    Route::get('events', [AgencyEventController::class, 'index']);
    Route::post('event-store', [AgencyEventController::class, 'store']);
    Route::get('event-show/{id}', [AgencyEventController::class, 'show']);
    Route::put('event-update/{id}', [AgencyEventController::class, 'update']);
    Route::delete('event-destroy/{id}', [AgencyEventController::class, 'destroy']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

    //agency type management route
    Route::get('types', [AgencyTypeController::class, 'index']);
    Route::get('type/{id}', [AgencyTypeController::class, 'show']);
    Route::post('type-store', [AgencyTypeController::class, 'store']);
    Route::put('type-update/{id}', [AgencyTypeController::class, 'update']);
    Route::put('type-bulk-update', [AgencyTypeController::class, 'bulkUpdate']);
    Route::delete('type-destroy/{id}', [AgencyTypeController::class, 'destroy']);

    //agency location management route
    Route::get('locations', [AgencyLocationController::class, 'index']);
    Route::get('location/{id}', [AgencyLocationController::class, 'show']);
    Route::post('location-store', [AgencyLocationController::class, 'store']);
    Route::put('location-update/{id}', [AgencyLocationController::class, 'update']);
    Route::put('location-bulk-update', [AgencyLocationController::class, 'bulkUpdate']);
    Route::delete('location-destroy/{id}', [AgencyLocationController::class, 'destroy']);

    //agency checklist management route
    Route::get('checklist', [AgencyChecklistController::class, 'index']);
    Route::get('checklist/{id}', [AgencyChecklistController::class, 'show']);
    Route::post('checklist-store', [AgencyChecklistController::class, 'store']);
    Route::put('checklist-update/{id}', [AgencyChecklistController::class, 'update']);
    Route::put('checklist-bulk-update', [AgencyChecklistController::class, 'bulkUpdate']);
    Route::delete('checklist-destroy/{id}', [AgencyChecklistController::class, 'destroy']);

    //agency tags management route
    Route::get('tags', [AgencyTagController::class, 'index']);
    Route::get('tag/{id}', [AgencyTagController::class, 'show']);
    Route::post('tag-store', [AgencyTagController::class, 'store']);
    Route::put('tag-update/{id}', [AgencyTagController::class, 'update']);
    Route::put('tag-bulk-update', [AgencyTagController::class, 'bulkUpdate']);
    Route::delete('tag-destroy/{id}', [AgencyTagController::class, 'destroy']);

    //agency event type management route
    Route::get('event-types', [AgencyEventTypeController::class, 'index']);
    Route::get('event-type/{id}', [AgencyEventTypeController::class, 'show']);
    Route::post('event-type-store', [AgencyEventTypeController::class, 'store']);
    Route::put('event-type-update/{id}', [AgencyEventTypeController::class, 'update']);
    Route::put('event-type-bulk-update', [AgencyEventTypeController::class, 'bulkUpdate']);
    Route::delete('event-type-destroy/{id}', [AgencyEventTypeController::class, 'destroy']);
});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {});
