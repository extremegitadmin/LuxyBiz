<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Middleware\AdminRole;
use Illuminate\Support\Facades\Route;

/*
| Asha's admin routes (Bookings, Payments & Cancellations).
| Loaded from routes/web.php with: require __DIR__ . '/admin/bookings.php';
*/
Route::prefix('admin')->name('admin.')->middleware(AdminRole::class . ':admin')->group(function () {
    Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])
        ->name('bookings.edit')->whereNumber('booking');
    Route::put('bookings/{booking}', [BookingController::class, 'update'])
        ->name('bookings.update')->whereNumber('booking');
});