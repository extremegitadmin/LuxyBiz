<?php

use App\Http\Controllers\Admin\AssistedBookingController;
use App\Http\Controllers\PayLinkController;
use Illuminate\Support\Facades\Route;

/*
| Days 26-30: CSR assisted bookings. Loaded from routes/web.php with: require __DIR__ . '/admin/assisted.php';
| Taking payment reuses the existing admin.payments.link.create permission.
*/
Route::prefix('admin/assisted')->name('admin.assisted.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('/', [AssistedBookingController::class, 'index'])->middleware('permission:admin.assisted.view')->name('index');
    Route::get('create', [AssistedBookingController::class, 'create'])->middleware('permission:admin.assisted.create')->name('create');
    Route::post('/', [AssistedBookingController::class, 'store'])->middleware(['permission:admin.assisted.create', 'throttle:20,1'])->name('store');
    Route::get('customers', [AssistedBookingController::class, 'customers'])->middleware(['permission:admin.assisted.create', 'throttle:60,1'])->name('customers');
    Route::get('quote', [AssistedBookingController::class, 'quote'])->middleware(['permission:admin.assisted.create', 'throttle:120,1'])->name('quote');

    Route::get('{booking}/payment', [AssistedBookingController::class, 'payment'])->middleware('permission:admin.payments.link.create')->name('payment')->whereNumber('booking');
    Route::post('{booking}/hosted', [AssistedBookingController::class, 'hosted'])->middleware(['permission:admin.payments.link.create', 'throttle:20,1'])->name('hosted')->whereNumber('booking');
    Route::post('{booking}/send-link', [AssistedBookingController::class, 'sendLink'])->middleware(['permission:admin.payments.link.create', 'throttle:20,1'])->name('send-link')->whereNumber('booking');
    Route::post('{booking}/links/{paymentRequest}/resend', [AssistedBookingController::class, 'resendLink'])->middleware(['permission:admin.payments.link.create', 'throttle:20,1'])->name('resend-link')->whereNumber(['booking', 'paymentRequest']);
    Route::post('{booking}/links/{paymentRequest}/cancel', [AssistedBookingController::class, 'cancelLink'])->middleware('permission:admin.payments.link.create')->name('cancel-link')->whereNumber(['booking', 'paymentRequest']);
});

// Public: the customer's payment link. No login. The token is the only key, so the pages are never cached or indexed.
Route::get('pay/{token}', [PayLinkController::class, 'show'])->middleware('throttle:30,1')->name('pay.show');
Route::post('pay/{token}', [PayLinkController::class, 'start'])->middleware('throttle:10,1')->name('pay.start');
