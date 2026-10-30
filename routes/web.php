<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;

Route::get('/', function () {
    return view('admin.dashboard');
});

Route::prefix('admin')->name('admin.')->group(function () {
    // ---------- Public admin routes ----------
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');   
});


