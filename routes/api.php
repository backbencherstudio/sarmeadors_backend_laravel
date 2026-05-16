<?php

use App\Http\Controllers\Agency\ClientController;
use App\Http\Controllers\Agency\FormController;
use App\Http\Controllers\Api\AgencyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Agency\StatusController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Agency\FormFieldController;
use App\Http\Controllers\Client\ClientController as ClientClientController;


Route::middleware('subdomain')->group(function () {

    Route::prefix('client')->group(function () {
        Route::post('/register', [ClientClientController::class, 'store']);
    });
});


Route::middleware('auth:api')->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {

});

require __DIR__.'/niaz.php';
require __DIR__.'/mahmudul.php';
require __DIR__.'/shanto.php';
