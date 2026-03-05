<?php

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

    Route::get('client-types', [ClientTypeController::class, 'index']);
    Route::get('client-types/{id}', [ClientTypeController::class, 'show']);
    Route::post('client-types', [ClientTypeController::class, 'store']);
    Route::put('client-types/{id}', [ClientTypeController::class, 'update']);
    Route::patch('client-types/{id}', [ClientTypeController::class, 'update']);
    Route::delete('client-types/{id}', [ClientTypeController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

    Route::get('client-types', [ClientTypeController::class, 'index']);
    Route::get('client-types/{id}', [ClientTypeController::class, 'show']);
    Route::post('client-types', [ClientTypeController::class, 'store']);
    Route::put('client-types/{id}', [ClientTypeController::class, 'update']);
    Route::patch('client-types/{id}', [ClientTypeController::class, 'update']);
    Route::delete('client-types/{id}', [ClientTypeController::class, 'destroy']);
    
});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {

});
