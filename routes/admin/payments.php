<?php

use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentEventController;
use App\Http\Controllers\Admin\GatewaySettingsController;
use App\Http\Middleware\AdminRole;
use Illuminate\Support\Facades\Route;

/*
| Asha's payment routes (Day 7: list). Day 8 adds the detail page.
| Loaded from routes/web.php with: require __DIR__ . '/admin/payments.php';
*/
Route::prefix('admin')->name('admin.')->middleware(AdminRole::class . ':admin')->group(function () {
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/events', [PaymentEventController::class, 'index'])->name('payments.events');   // above {payment}
    Route::get('payments/{payment}', [PaymentController::class, 'show'])
        ->name('payments.show')->whereNumber('payment');
    // Gateway settings (M3). Holds payment keys: keep this to the highest admin role.
    Route::get('settings/gateway', [GatewaySettingsController::class, 'edit'])->name('settings.gateway');
    Route::put('settings/gateway', [GatewaySettingsController::class, 'update'])->name('settings.gateway.update');
    Route::post('settings/gateway/test', [GatewaySettingsController::class, 'test'])->name('settings.gateway.test');

    // "Request online payment" on a booking
    Route::post('bookings/{booking}/payment-link', [BookingPaymentLinkController::class, 'store'])
        ->name('bookings.payment-link')->whereNumber('booking');
});
// Public pages the customer lands on after the hosted payment page. No login, no booking data.
Route::get('payment/return', [PaymentReturnController::class, 'success'])->name('payment.return');
Route::get('payment/cancelled', [PaymentReturnController::class, 'cancelled'])->name('payment.cancelled');


//remove below


Route::get('admin/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
    Route::get('admin/payments/events', [PaymentEventController::class, 'index'])->name('admin.payments.events');   // above {payment}
    Route::get('admin/payments/{payment}', [PaymentController::class, 'show'])
        ->name('admin.payments.show')->whereNumber('payment');
    // Gateway settings (M3). Holds payment keys: keep this to the highest admin role.
    Route::get('admin/settings/gateway', [GatewaySettingsController::class, 'edit'])->name('admin.settings.gateway');
    Route::put('admin/settings/gateway', [GatewaySettingsController::class, 'update'])->name('admin.settings.gateway.update');

   