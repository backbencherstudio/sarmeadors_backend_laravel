<?php

use App\Http\Controllers\Client\ClientController as ClientClientController;
use Illuminate\Support\Facades\Route;

Route::middleware('subdomain')->group(function () {

    Route::prefix('client')->group(function () {
        Route::post('/register', [ClientClientController::class, 'store']);
    });
});

Route::middleware('auth:api')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->prefix('admin')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->prefix('admin')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->prefix('agency')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->prefix('agency')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->prefix('client')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->prefix('candidate')->group(function () {});

require __DIR__.'/niaz.php';
require __DIR__.'/mahmudul.php';
require __DIR__.'/shanto.php';
