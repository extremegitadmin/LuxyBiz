<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\TourController;
use App\Http\Controllers\Admin\ObjectiveController;
use App\Http\Controllers\Admin\DepartureController;


Route::get('/', function () {
    return view('admin.dashboard');
});

Route::prefix('admin')->name('admin.')->group(function () {
    
    // ---------- Public admin routes ----------
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');


    Route::resource('tours', TourController::class)->except('show');
    Route::get('tours/{tour}/preview', [TourController::class, 'preview'])->name('tours.preview');
    Route::post('tours/{tour}/duplicate', [TourController::class, 'duplicate'])->name('tours.duplicate');
    Route::patch('tours/{tour}/publish', [TourController::class, 'togglePublish'])->name('tours.publish');
    Route::resource('objectives', ObjectiveController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('departures', DepartureController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::patch('departures/{departure}/toggle', [DepartureController::class, 'toggle'])->name('departures.toggle');

});

require __DIR__ . '/admin/bookings.php';

