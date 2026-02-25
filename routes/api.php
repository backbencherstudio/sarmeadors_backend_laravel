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
Route::post('/register', [AuthController::class, 'register'])->name('api.register');


Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth:api', 'permission:api.role.view'])->get('/roles', [RoleController::class, 'data'])->name('api.role.view');
Route::middleware(['auth:api', 'permission:api.role.create'])->group(function () {
    Route::get('/permissions', [RoleController::class, 'permissions'])->name('api.permissions.view');
    Route::post('/role-store', [RoleController::class, 'store'])->name('api.role.store');
});
Route::middleware(['auth:api', 'permission:api.role.edit'])->group(function () {
    Route::get('/role-edit-data/{id}', [RoleController::class, 'edit'])->name('api.role.edit');
    Route::put('/role-update/{id}', [RoleController::class, 'update'])->name('api.role.update');
});
Route::middleware(['auth:api', 'permission:api.role.destroy'])->delete('/role-delete/{id}', [RoleController::class, 'destroy'])->name('api.role.destroy');

Route::middleware(['auth:api', 'permission:api.user.view'])->get('/users', [UserController::class, 'data'])->name('api.user.view');
Route::middleware(['auth:api', 'permission:api.user.create'])->group(function () {
    Route::get('/role-list', [UserController::class, 'roleList'])->name('api.role.list');
    Route::post('/user-store', [UserController::class, 'store'])->name('api.user.store');
});

Route::middleware(['auth:api', 'permission:api.user.edit'])->group(function () {
    Route::get('/user-edit-data/{id}', [UserController::class, 'edit'])->name('api.user.edit');
    Route::put('/user-update/{id}', [UserController::class, 'update'])->name('api.user.update');
});

Route::middleware('auth:api')->post('/update-password', [UserController::class, 'updatePass'])->name('api.update.password');
Route::middleware('auth:api')->put('profile-update', [UserController::class, 'profileUpdate'])->name('api.profile.update');

Route::middleware(['auth:api', 'permission:api.user.destroy'])->delete('/user-delete/{id}', [UserController::class, 'destroy'])->name('api.user.destroy');



// Route::middleware(['auth:api', 'role:admin'])->group(function () {
//     Route::get('/users', [UserController::class, 'index'])->name('users.index');
//     Route::post('/users', [UserController::class, 'store'])->name('users.store');
// });

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
