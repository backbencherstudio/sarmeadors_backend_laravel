<?php

use App\Http\Controllers\Api\AgencyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotPasswordController;


Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Please login to continue',
    ], 401);
})->name('api.login');

Route::post('/login', [AuthController::class, 'login']);

Route::post('/send-otp', [ForgotPasswordController::class, 'sendOtp'])->name('api.send.otp');
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('api.verify.otp');
Route::post('/password-reset', [ForgotPasswordController::class, 'resetPassword'])->name('api.password.reset');


Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth:api', 'role:Super Admin'])->group(function () {
    Route::get('/users', [UserController::class, 'data']);
    route::get('role-list', [UserController::class, 'roleList']);
    Route::post('/user-store', [UserController::class, 'store']);
    Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
    Route::put('/user-update/{id}', [UserController::class, 'update']);
    Route::post('/update-password', [UserController::class, 'updatePass']);
    Route::delete('/user-delete/{id}', [UserController::class, 'destroy']);

    //Agency
    Route::get('/agencies', [AgencyController::class, 'data']);
    Route::post('/agency-store', [AgencyController::class, 'store']);
    Route::get('/agency-edit/{id}', [AgencyController::class, 'edit']);
    Route::put('/agency-update/{id}', [AgencyController::class, 'update']);
    Route::delete('/agency-delete/{id}', [AgencyController::class, 'destroy']);
});

Route::middleware(['auth:api', 'role:Super Admin|Admin Staff'])->group(function () {

});

Route::middleware(['auth:api', 'role:Agency Admin'])->group(function () {

});

Route::middleware(['auth:api', 'role:Agency Admin|Agency Staff'])->group(function () {

});

Route::middleware(['auth:api', 'role:Client'])->group(function () {

});

Route::middleware(['auth:api', 'role:Candidate'])->group(function () {

});
