<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/clear', function () {
    Artisan::call('optimize');
    return "Cleared!";
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/welcome', [DashboardController::class, 'welcome'])->name('welcome')->middleware('auth');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

Route::get('settings', [ExampleController::class, 'settings'])->name('example.settings');
Route::get('transactions', [ExampleController::class, 'transactions'])->name('example.transactions');


Route::get('/reset-password', [ExampleController::class, 'resetPassword'])->name('reset.password');
Route::get('/404', [ExampleController::class, 'error404'])->name('404');
Route::get('/500', [ExampleController::class, 'error500'])->name('500');
Route::get('/buttons', [ExampleController::class, 'buttons'])->name('buttons');
Route::get('/notifications', [ExampleController::class, 'notifications'])->name('notifications');
Route::get('/forms', [ExampleController::class, 'forms'])->name('forms');
Route::get('/modals', [ExampleController::class, 'modals'])->name('modals');
Route::get('/typography', [ExampleController::class, 'typography'])->name('typography');

Route::middleware(['permission:user.view'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users-data', [UserController::class, 'userData'])->name('users.data');
});

Route::middleware(['permission:user.create'])->group(function () {
    Route::get('/users-create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users-store', [UserController::class, 'store'])->name('users.store');
});

Route::middleware ( [ 'permission:user.edit' ] )->group ( function () {
    Route::get ( '/user-edit/{id}', [ UserController::class, 'edit' ] )->name ( 'user.edit.view' );
    Route::put ( '/user-edit/{id}', [ UserController::class, 'update' ] )->name ( 'users.update' );
});

Route::delete ( '/user-delete/{id}', [ UserController::class, 'destroy' ] )->middleware ( [ 'permission:user.destroy' ] )->name ( 'users.destroy' );

//Password Reset
Route::middleware(['auth'])->group(function () {
    Route::get('profile', [UserController::class, 'profile'])->name('profile.view');
    Route::post('password-reset', [UserController::class, 'updatePass'])->name('password.update.view');
    Route::put('profile-update', [UserController::class, 'profileUpdate'])->name('profile.update');
});


//Permission Group
Route::middleware(['permission:permission.group.view'])->group(function () {
    Route::get('/permission-group', [PermissionController::class, 'groupIndex'])->name('permission.group.index');
    Route::get('/group-data', [PermissionController::class, 'groupData'])->name('permission.group.data');
});

Route::middleware(['permission:permission.group.create'])->group(function () {
    Route::get('/group-create', [PermissionController::class, 'groupCreate'])->name('permission.group.create');
    Route::post('/group-store', [PermissionController::class, 'groupStore'])->name('permission.group.store');
});

Route::middleware ( [ 'permission:permission.group.edit' ] )->group ( function () {
    Route::get ( '/group-edit/{id}', [ PermissionController::class, 'groupEdit' ] )->name ( 'permission.group.edit' );
    Route::put ( '/group-edit/{id}', [ PermissionController::class, 'groupUpdate' ] )->name ( 'permission.group.update' );
});
Route::delete ( '/group-delete/{id}', [ PermissionController::class, 'groupDestroy' ] )->middleware ( [ 'permission:permission.group.destroy' ] )->name ( 'permission.group.destroy' );

//Permission
Route::middleware(['permission:permission.view'])->group(function () {
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permission.index');
    Route::get('/permission-data', [PermissionController::class, 'data'])->name('permission.data');
});

Route::middleware(['permission:permission.create'])->group(function () {
    Route::get('/permission-create', [PermissionController::class, 'create'])->name('permission.create');
    Route::post('/permission-store', [PermissionController::class, 'store'])->name('permission.store');
});

Route::middleware ( [ 'permission:permission.edit' ] )->group ( function () {
    Route::get ( '/permission-edit/{id}', [ PermissionController::class, 'edit' ] )->name ( 'permission.edit' );
    Route::put ( '/permission-edit/{id}', [ PermissionController::class, 'update' ] )->name ( 'permission.update' );
});
Route::delete ( '/permission-delete/{id}', [ PermissionController::class, 'destroy' ] )->middleware ( [ 'permission:permission.destroy' ] )->name ( 'permission.destroy' );

//Roles
Route::middleware(['permission:role.view'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('role.index');
    Route::get('/role-data', [RoleController::class, 'data'])->name('role.data');
});

Route::middleware(['permission:role.create'])->group(function () {
    Route::get('/role-create', [RoleController::class, 'create'])->name('role.create');
    Route::post('/role-store', [RoleController::class, 'store'])->name('role.store');
});

Route::middleware ( [ 'permission:role.edit' ] )->group ( function () {
    Route::get ( '/role-edit/{id}', [ RoleController::class, 'edit' ] )->name ( 'role.edit' );
    Route::put ( '/role-edit/{id}', [ RoleController::class, 'update' ] )->name ( 'role.update' );
});

Route::delete ( '/role-delete/{id}', [ RoleController::class, 'destroy' ] )->middleware ( [ 'permission:role.destroy' ] )->name ( 'role.destroy' );



require __DIR__.'/auth.php';
