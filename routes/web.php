<?php

use Illuminate\Support\Facades\Route;

Auth::loginUsingId(1);
Route::get('/', function () {
    return view('welcome');
});
