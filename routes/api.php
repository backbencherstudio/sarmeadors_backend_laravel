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


Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Please login to continue',
    ], 401);
})->name('api.login');

Route::middleware('subdomain')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/send-otp', [ForgotPasswordController::class, 'sendOtp'])->name('api.send.otp');
    Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('api.verify.otp');
    Route::post('/password-reset', [ForgotPasswordController::class, 'resetPassword'])->name('api.password.reset');
});


Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'loggout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/update-password', [UserController::class, 'updatePass']);
});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|agency_admin'])->group(function () {
    Route::get('/users', [UserController::class, 'data']);
    route::get('role-list', [UserController::class, 'roleList']);
    Route::post('/user-store', [UserController::class, 'store']);
    Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
    Route::put('/user-update/{id}', [UserController::class, 'update']);
    Route::delete('/user-delete/{id}', [UserController::class, 'destroy']);
});
Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->group(function () {
    //Agency
    Route::get('/agencies', [AgencyController::class, 'data']);
    Route::post('/agency-store', [AgencyController::class, 'store']);
    Route::get('/agency-edit/{id}', [AgencyController::class, 'edit']);
    Route::put('/agency-update/{id}', [AgencyController::class, 'update']);
    Route::delete('/agency-delete/{id}', [AgencyController::class, 'destroy']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->group(function () {


});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->group(function () {

    Route::post('info-update', [AgencyController::class, 'infoUpdate']);

    //Client Status
    Route::get('/client-statuses', [StatusController::class, 'index']);
    Route::post('/client-status-store', [StatusController::class, 'store']);
    Route::get('/client-status-edit/{id}', [StatusController::class, 'edit']);
    Route::put('/client-status-update/{id}', [StatusController::class, 'update']);
    Route::patch('/client-status-serial-update/{id}', [StatusController::class, 'serial']);
    Route::delete('/client-status-delete/{id}', [StatusController::class, 'destroy']);

    //Form
    Route::post('/forms', [FormController::class, 'store']);
    Route::post('/form-fields', [FormFieldController::class, 'store']);

    Route::post('/form-fields/reorder',[FormFieldController::class,'reorder']);
    Route::get('/forms/{slug}',[FormController::class,'show']);

    //Client Registration
    Route::post('/clients',[ClientController::class,'store']);
    Route::get('/clients/{id}',[ClientController::class,'show']);

});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->group(function () {

});

require __DIR__.'/niaz.php';
require __DIR__.'/mahmudul.php';
