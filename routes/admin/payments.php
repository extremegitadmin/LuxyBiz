<?php

use App\Http\Controllers\Admin\PaymentController;
use App\Http\Middleware\AdminRole;
use Illuminate\Support\Facades\Route;

/*
| Asha's payment routes (Day 7: list). Day 8 adds the detail page.
| Loaded from routes/web.php with: require __DIR__ . '/admin/payments.php';
*/
Route::prefix('admin')->name('admin.')->middleware(AdminRole::class . ':admin')->group(function () {
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])
        ->name('payments.show')->whereNumber('payment');
});

Route::get('admin/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
Route::get('admin/payments/{payment}', [PaymentController::class, 'show'])
        ->name('admin.payments.show')->whereNumber('payment');
