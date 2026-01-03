<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect(filament()->getUrl());
    }

    return view('welcome');
});

Route::get('/login', fn () => redirect()->to(filament()->getLoginUrl()))->name('login');
