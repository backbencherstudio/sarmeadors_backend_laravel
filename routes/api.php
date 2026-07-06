<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / shared authentication routes (not tied to a single role)
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Please login to continue',
    ], 401);
})->name('api.login');


// Refresh token route

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware('subdomain')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/send-otp', [ForgotPasswordController::class, 'sendOtp'])->name('api.send.otp');
    Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('api.verify.otp');
    Route::post('/password-reset', [ForgotPasswordController::class, 'resetPassword'])->name('api.password.reset');
});

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update-password', [UserController::class, 'updatePass']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);
});

/*
|--------------------------------------------------------------------------
| Role-scoped route files
|--------------------------------------------------------------------------
*/
require __DIR__ . '/superadmin.php';
require __DIR__ . '/agency.php';
require __DIR__ . '/client.php';
require __DIR__ . '/candidate.php';
