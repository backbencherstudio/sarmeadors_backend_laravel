<?php

use App\Http\Controllers\Client\ClientLocationController;
use App\Http\Controllers\Client\ClientTypeController;
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
    Route::delete('client-type-destroy/{id}', [ClientTypeController::class, 'destroy']);

    //client location management route
    Route::get('client-locations', [ClientLocationController::class, 'index']);
    Route::get('client-location/{id}', [ClientLocationController::class, 'show']);
    Route::post('client-location-store', [ClientLocationController::class, 'store']);
    Route::put('client-location-update/{id}', [ClientLocationController::class, 'update']);
    Route::delete('client-location-destroy/{id}', [ClientLocationController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

    //client type management route
    Route::get('client-types', [ClientTypeController::class, 'index']);
    Route::get('client-type/{id}', [ClientTypeController::class, 'show']);
    Route::post('client-type-store', [ClientTypeController::class, 'store']);
    Route::put('client-type-update/{id}', [ClientTypeController::class, 'update']);
    Route::delete('client-type-destroy/{id}', [ClientTypeController::class, 'destroy']);

    //client location management route
    Route::get('client-locations', [ClientLocationController::class, 'index']);
    Route::get('client-location/{id}', [ClientLocationController::class, 'show']);
    Route::post('client-location-store', [ClientLocationController::class, 'store']);
    Route::put('client-location-update/{id}', [ClientLocationController::class, 'update']);
    Route::delete('client-location-destroy/{id}', [ClientLocationController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {

});
