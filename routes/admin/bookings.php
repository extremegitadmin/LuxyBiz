<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Middleware\AdminRole;
use Illuminate\Support\Facades\Route;

/*
| Asha's admin routes (Bookings, Payments & Cancellations).
| Loaded from routes/web.php with: require __DIR__ . '/admin/bookings.php';
*/
Route::prefix('admin')->name('admin.')->middleware(AdminRole::class . ':admin')->group(function () {
    // List, export, detail (export must stay above the {booking} routes)
    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])
        ->name('bookings.show')->whereNumber('booking');

    // Edit
    Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])
        ->name('bookings.edit')->whereNumber('booking');
    Route::put('bookings/{booking}', [BookingController::class, 'update'])
        ->name('bookings.update')->whereNumber('booking');
});
 // List, export, detail (export must stay above the {booking} routes)
    Route::get('admin/bookings', [BookingController::class, 'index'])->name('admin.bookings.index');
    Route::get('admin/bookings/export', [BookingController::class, 'export'])->name('admin.bookings.export');
    Route::get('admin/bookings/{booking}', [BookingController::class, 'show'])
        ->name('admin.bookings.show')->whereNumber('booking');

    // Edit
    Route::get('admin/bookings/{booking}/edit', [BookingController::class, 'edit'])
        ->name('admin.bookings.edit')->whereNumber('booking');
    Route::put('admin/bookings/{booking}', [BookingController::class, 'update'])
        ->name('admin.bookings.update')->whereNumber('booking');