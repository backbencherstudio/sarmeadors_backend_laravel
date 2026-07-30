<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Agency\AgencyController;
use Illuminate\Support\Facades\Route;

// Route::middleware(['subdomain', 'auth:api', 'role:super_admin|agency_admin'])->group(function () {
//     Route::get('/users', [UserController::class, 'data']);
//     Route::get('/role-list', [UserController::class, 'roleList']);
//     Route::post('/user-store', [UserController::class, 'store']);
//     Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
//     Route::post('/user-update/{id}', [UserController::class, 'update']);
//     Route::delete('/user-delete/{id}', [UserController::class, 'destroy']);
// });

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Agency
    Route::get('/agencies', [AgencyController::class, 'data']);
    Route::post('/agency-store', [AgencyController::class, 'store']);
    Route::get('/agency-edit/{id}', [AgencyController::class, 'edit']);
    Route::post('/agency-update/{id}', [AgencyController::class, 'update']);
    Route::delete('/agency-delete/{id}', [AgencyController::class, 'destroy']);
});
