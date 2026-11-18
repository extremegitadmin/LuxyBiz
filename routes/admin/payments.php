<?php

use App\Http\Controllers\Admin\BookingPaymentLinkController;
use App\Http\Controllers\Admin\GatewaySettingsController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentEventController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Asha's payment routes (Day 7: list, 8: detail, 9: webhook events log, 10: gateway settings,
| 11: payment links and return pages, 12: webhook receiver).
| Loaded from routes/web.php with: require __DIR__ . '/admin/payments.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('payments', [PaymentController::class, 'index'])
        ->middleware('permission:admin.payments.view')->name('payments.index');
    Route::get('payments/events', [PaymentEventController::class, 'index'])   // above {payment}
        ->middleware('permission:admin.payments.events.view')->name('payments.events');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])
        ->middleware('permission:admin.payments.view')->name('payments.show')->whereNumber('payment');

    // Gateway settings (M3). Holds payment keys: give the edit permission to the highest admin role only.
    // Each provider has its own form; routing says which provider takes INR and which takes other currencies.
    Route::get('settings/gateway', [GatewaySettingsController::class, 'edit'])
        ->middleware('permission:admin.settings.gateway.view')->name('settings.gateway');
    Route::put('settings/gateway/routing', [GatewaySettingsController::class, 'routing'])
        ->middleware('permission:admin.settings.gateway.edit')->name('settings.gateway.routing');
    Route::put('settings/gateway/{provider}', [GatewaySettingsController::class, 'update'])
        ->middleware('permission:admin.settings.gateway.edit')
        ->name('settings.gateway.update')->whereIn('provider', ['razorpay', 'stripe']);
    Route::post('settings/gateway/{provider}/test', [GatewaySettingsController::class, 'test'])
        ->middleware('permission:admin.settings.gateway.edit')
        ->name('settings.gateway.test')->whereIn('provider', ['razorpay', 'stripe']);

    // "Request online payment" on a booking
    Route::post('bookings/{booking}/payment-link', [BookingPaymentLinkController::class, 'store'])
        ->middleware('permission:admin.payments.link.create')
        ->name('bookings.payment-link')->whereNumber('booking');
});

// Public pages the customer lands on after the hosted payment page. No login, no booking data.
Route::get('payment/return', [PaymentReturnController::class, 'success'])->name('payment.return');
Route::get('payment/cancelled', [PaymentReturnController::class, 'cancelled'])->name('payment.cancelled');

// A payment provider calls this (one address per provider). No login, no CSRF token (the provider cannot have one):
// the signature check is the protection.
Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->name('webhooks.payments')
    ->whereIn('provider', ['razorpay', 'stripe'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
