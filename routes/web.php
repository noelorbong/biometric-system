<?php

use Illuminate\Support\Facades\Route;

Route::get('/uploads/{path}', [App\Http\Controllers\MediaFileController::class, 'desktopUpload'])
    ->where('path', '.*');

Route::get('{any}', function () {
    return view('layouts.app');
})->where('any', '.*');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
