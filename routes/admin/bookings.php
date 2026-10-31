<?php

use App\Http\Controllers\Admin\BookingController;
use Illuminate\Support\Facades\Route;

/*
| Asha's admin routes (Bookings, Payments & Cancellations).
| Loaded from routes/web.php with one line:  require __DIR__ . '/admin/bookings.php';
| Later this line moves into routes/admin.php (Anakha, Day 6).
*/
Route::prefix('admin')->name('admin.')->middleware('admin.role:admin')->group(function () {
    Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])
        ->name('bookings.edit')->whereNumber('booking');
    Route::put('bookings/{booking}', [BookingController::class, 'update'])
        ->name('bookings.update')->whereNumber('booking');
});
