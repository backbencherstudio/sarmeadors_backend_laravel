<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Agency\FormController;
use App\Http\Controllers\Agency\StatusController;
use App\Http\Controllers\Agency\ClientController;
use App\Http\Controllers\Agency\FormFieldController;
use App\Http\Controllers\Agency\AgencyNoteController;
use App\Http\Controllers\Agency\MessageTemplateController;
use App\Http\Controllers\Agency\FormBuilderController;
use App\Http\Controllers\Agency\FormBuilderBlockController;
use App\Http\Controllers\Agency\FormBuilderSectionController;
use App\Http\Controllers\Agency\FormBuilderFieldController;
use App\Http\Controllers\Agency\FormBuilderSubmissionController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;

Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Please login to continue',
    ], 401);
})->name('api.login');

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

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|agency_admin'])->group(function () {
    Route::get('/users', [UserController::class, 'data']);
    Route::get('/role-list', [UserController::class, 'roleList']);
    Route::post('/user-store', [UserController::class, 'store']);
    Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
    Route::post('/user-update/{id}', [UserController::class, 'update']);
    Route::delete('/user-delete/{id}', [UserController::class, 'destroy']);
});

Route::middleware('auth:api')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin'])->prefix('admin')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:super_admin|admin_staff'])->prefix('admin')->group(function () {
    
    //Agency
    Route::get('/agencies', [AgencyController::class, 'data']);
    Route::post('/agency-store', [AgencyController::class, 'store']);
    Route::get('/agency-edit/{id}', [AgencyController::class, 'edit']);
    Route::post('/agency-update/{id}', [AgencyController::class, 'update']);
    Route::delete('/agency-delete/{id}', [AgencyController::class, 'destroy']);

});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin'])->prefix('agency')->group(function () {

    Route::get('/agency-info', [AgencyController::class, 'info']);
    Route::post('info-update/{id}', [AgencyController::class, 'infoUpdate']);
});

Route::middleware(['subdomain', 'auth:api', 'role:agency_admin|agency_staff'])->prefix('agency')->group(function () {

    //Client Status
    Route::get('/statuses', [StatusController::class, 'index']);
    Route::post('/status-store', [StatusController::class, 'store']);
    Route::get('/status-edit/{id}', [StatusController::class, 'edit']);
    Route::put('/status-update/{id}', [StatusController::class, 'update']);
    Route::patch('/status-serial-update/{id}', [StatusController::class, 'serial']);
    Route::delete('/status-delete/{id}', [StatusController::class, 'destroy']);
    //reasons
    Route::post('/status-reasons', [StatusController::class, 'storeReasons']);

    //Form
    Route::post('/forms', [FormController::class, 'store']);
    Route::post('/form-fields', [FormFieldController::class, 'store']);

    Route::post('/form-fields/reorder',[FormFieldController::class,'reorder']);
    Route::get('/forms/{slug}',[FormController::class,'show']);

    //Client Registration
    Route::post('/clients',[ClientController::class,'store']);
    Route::get('/clients/{id}',[ClientController::class,'show']);

    // Form Builder CRUD
    Route::get('/form-builders', [FormBuilderController::class, 'index']);
    Route::post('/form-builders', [FormBuilderController::class, 'store']);
    Route::get('/form-builders/{id}', [FormBuilderController::class, 'show']);
    Route::post('/form-builders/{id}', [FormBuilderController::class, 'update']);
    Route::delete('/form-builders/{id}', [FormBuilderController::class, 'destroy']);
    Route::patch('/form-builders/{id}/publish', [FormBuilderController::class, 'publish']);

    // Blocks
    Route::post('/form-builders/{formBuilderId}/blocks', [FormBuilderBlockController::class, 'store']);
    Route::put('/form-builder-blocks/{id}', [FormBuilderBlockController::class, 'update']);
    Route::delete('/form-builder-blocks/{id}', [FormBuilderBlockController::class, 'destroy']);
    Route::post('/form-builder-blocks/reorder', [FormBuilderBlockController::class, 'reorder']);

    // Sections
    Route::post('/form-builder-blocks/{blockId}/sections', [FormBuilderSectionController::class, 'store']);
    Route::put('/form-builder-sections/{id}', [FormBuilderSectionController::class, 'update']);
    Route::delete('/form-builder-sections/{id}', [FormBuilderSectionController::class, 'destroy']);

    // Fields
    Route::post('/form-builder-sections/{sectionId}/fields', [FormBuilderFieldController::class, 'store']);
    Route::put('/form-builder-fields/{id}', [FormBuilderFieldController::class, 'update']);
    Route::delete('/form-builder-fields/{id}', [FormBuilderFieldController::class, 'destroy']);
    Route::post('/form-builder-fields/reorder', [FormBuilderFieldController::class, 'reorder']);

    Route::get('/form-builders/{id}/submissions', [FormBuilderSubmissionController::class, 'index']);
    Route::get('/form-builder-submissions/{id}', [FormBuilderSubmissionController::class, 'show']);
});

Route::middleware('subdomain')->prefix('public')->group(function () {
    Route::get('/form-builders/{slug}', [FormBuilderController::class, 'publicShow']);
    // Submit a form
    Route::post('/form-builders/{slug}/submit', [FormBuilderSubmissionController::class, 'submit']);
});

Route::middleware(['subdomain', 'auth:api', 'role:client'])->prefix('client')->group(function () {});

Route::middleware(['subdomain', 'auth:api', 'role:candidate'])->prefix('candidate')->group(function () {});
