<?php

use App\Http\Controllers\Admin\BookingController;
use Illuminate\Support\Facades\Route;

/*
| Asha's admin routes (Bookings, Payments & Cancellations).
| Loaded from routes/web.php with: require __DIR__ . '/admin/bookings.php';
| Same pattern as the rest of the admin: signed in + active ('auth', 'permission'), then one permission per route.
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    // List, export, detail (export must stay above the {booking} routes)
    Route::get('bookings', [BookingController::class, 'index'])
        ->middleware('permission:admin.bookings.view')->name('bookings.index');
    Route::get('bookings/export', [BookingController::class, 'export'])
        ->middleware('permission:admin.bookings.export')->name('bookings.export');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])
        ->middleware('permission:admin.bookings.view')->name('bookings.show')->whereNumber('booking');

    // Edit
    Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])
        ->middleware('permission:admin.bookings.edit')->name('bookings.edit')->whereNumber('booking');
    Route::put('bookings/{booking}', [BookingController::class, 'update'])
        ->middleware('permission:admin.bookings.edit')->name('bookings.update')->whereNumber('booking');
});
