<?php

use App\Http\Controllers\Agency\AgencyChecklistController;
use App\Http\Controllers\Agency\AgencyEventController;
use App\Http\Controllers\Agency\AgencyEventTypeController;
use App\Http\Controllers\Agency\AgencyLocationController;
use App\Http\Controllers\Agency\AgencyTagController;
use App\Http\Controllers\Agency\AgencyTypeController;
use App\Http\Controllers\Agency\DocumentTemplateController;
use App\Http\Controllers\Agency\StatusTemplateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:api')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->group(function () {

    //agency type management route
    Route::get('agency-types', [AgencyTypeController::class, 'index']);
    Route::get('agency-type/{id}', [AgencyTypeController::class, 'show']);
    Route::post('agency-type-store', [AgencyTypeController::class, 'store']);
    Route::put('agency-type-update/{id}', [AgencyTypeController::class, 'update']);
    Route::put('agency-type-bulk-update', [AgencyTypeController::class, 'bulkUpdate']);
    Route::delete('agency-type-destroy/{id}', [AgencyTypeController::class, 'destroy']);

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

    //agency status template (get-process-flow) management route
    Route::get('get-process-flow', [StatusTemplateController::class, 'getProcessFlow']);
    Route::post('status-template-store', [StatusTemplateController::class, 'store']);
    Route::get('status-template-show/{id}', [StatusTemplateController::class, 'show']);
    Route::put('status-template-update/{id}', [StatusTemplateController::class, 'update']);
    Route::delete('status-template-destroy/{id}', [StatusTemplateController::class, 'destroy']);
    //status template create new status after
    Route::post('statuses/store-after', [StatusTemplateController::class, 'storeAfter']);

    //document template management route
    Route::get('document-templates', [DocumentTemplateController::class, 'index']);
    Route::post('document-templates/store', [DocumentTemplateController::class, 'store']);
    Route::get('document-templates/show/{id}', [DocumentTemplateController::class, 'show']);
    Route::post('document-templates/update/{id}', [DocumentTemplateController::class, 'update']);
    Route::delete('document-templates/delete/{id}', [DocumentTemplateController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

    //agency type management route
    Route::get('agency-types', [AgencyTypeController::class, 'index']);
    Route::get('agency-type/{id}', [AgencyTypeController::class, 'show']);
    Route::post('agency-type-store', [AgencyTypeController::class, 'store']);
    Route::put('agency-type-update/{id}', [AgencyTypeController::class, 'update']);
    Route::put('agency-type-bulk-update', [AgencyTypeController::class, 'bulkUpdate']);
    Route::delete('agency-type-destroy/{id}', [AgencyTypeController::class, 'destroy']);

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

    //agency status template (get-process-flow) management route
    Route::get('get-process-flow', [StatusTemplateController::class, 'getProcessFlow']);
    Route::post('status-template-store', [StatusTemplateController::class, 'store']);
    Route::get('status-template-show/{id}', [StatusTemplateController::class, 'show']);
    Route::put('status-template-update/{id}', [StatusTemplateController::class, 'update']);
    Route::delete('status-template-destroy/{id}', [StatusTemplateController::class, 'destroy']);
    //status template create new status after
    Route::post('statuses/store-after', [StatusTemplateController::class, 'storeAfter']);

    //document template management route
    Route::get('document-templates', [DocumentTemplateController::class, 'index']);
    Route::post('document-templates/store', [DocumentTemplateController::class, 'store']);
    Route::get('document-templates/show/{id}', [DocumentTemplateController::class, 'show']);
    Route::post('document-templates/update/{id}', [DocumentTemplateController::class, 'update']);
    Route::delete('document-templates/delete/{id}', [DocumentTemplateController::class, 'destroy']);
});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {
    
});
