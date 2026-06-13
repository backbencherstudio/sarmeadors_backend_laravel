<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::view('/', 'development')->name('home');

Route::get('/clear', function () {
    Artisan::call('optimize');

    return 'Cleared!';
});
