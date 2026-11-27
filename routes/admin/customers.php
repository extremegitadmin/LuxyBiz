<?php

use App\Http\Controllers\Admin\CustomerListController;
use Illuminate\Support\Facades\Route;

/* Customers menu. Loaded from routes/web.php with: require __DIR__ . '/admin/customers.php'; */
Route::prefix('admin/customers')->name('admin.customers.')->middleware(['auth', 'permission:admin.customers.view'])->group(function () {
    Route::get('/', [CustomerListController::class, 'index'])->name('index');
    Route::get('{customer}', [CustomerListController::class, 'show'])->name('show')->whereNumber('customer');
});
