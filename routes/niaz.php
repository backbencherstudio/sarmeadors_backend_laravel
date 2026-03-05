<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


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
