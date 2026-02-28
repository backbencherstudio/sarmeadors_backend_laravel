<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/welcome');

Route::get('/clear', function () {
    Artisan::call('optimize');
    return "Cleared!";
});

Route::get('/welcome', [DashboardController::class, 'welcome'])->name('welcome');
