<?php

use Illuminate\Support\Facades\Route;

Auth::loginUsingId(1);
Route::get('/', function () {
    if (Auth::check()) {
        return redirect(filament()->getUrl());
    }

    return view('welcome');
});

Route::get('/login', fn () => redirect()->to(filament()->getLoginUrl()))->name('login');
