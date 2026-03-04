<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:api')->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:Super Admin'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:Super Admin|Admin Staff'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:Agency Admin'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:Agency Admin|Agency Staff'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:Client'])->group(function () {

});

Route::middleware(['subdomain', 'auth:api', 'role:Candidate'])->group(function () {

});
