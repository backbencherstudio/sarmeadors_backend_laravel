<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\RoleController;


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
    Route::post('/user-store', [UserController::class, 'store'])->name('api.user.store');

    Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
    Route::put('/user-update/{id}', [UserController::class, 'update']);


    Route::post('/update-password', [UserController::class, 'updatePass']);
    Route::put('profile-update', [UserController::class, 'profileUpdate']);

    Route::delete('/user-delete/{id}', [UserController::class, 'destroy']);
});

Route::middleware(['auth:api', 'role:agency'])->group(function () {

});

Route::middleware(['auth:api', 'role:client'])->group(function () {

});

Route::middleware(['auth:api', 'role:candidate'])->group(function () {

});

// Route::middleware(['auth:api', 'role:admin|manager'])->group(function () {
//     Route::get('/reports', [UserController::class, 'reports']);
// });

// Multiple permissions (OR logic)
// Route::middleware(['auth:api', 'permission:users.view|users.edit'])->group(function () {
//     Route::get('/users', [UserController::class, 'index']);
//     Route::post('/users', [UserController::class, 'store']);
// });


// Route::prefix('admin')->middleware(['auth:api'])->group(function () {
//     Route::middleware(['permission:users.view|users.edit'])->group(function () {
//         Route::get('/test', [UserController::class, 'index']);
//     });

// });
