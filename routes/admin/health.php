<?php

use App\Http\Controllers\Admin\HealthIntakeController;
use Illuminate\Support\Facades\Route;

/*
| Asha's restricted health and intake routes (G4). Day 24: the page and editing. Day 25: every view and change is logged first.
| Loaded from routes/web.php with: require __DIR__ . '/admin/health.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('bookings/{booking}/health', [HealthIntakeController::class, 'show'])
        ->middleware(['permission:admin.health.view', 'throttle:60,1'])->name('health.show')->whereNumber('booking');
    Route::put('bookings/{booking}/travelers/{traveler}/health', [HealthIntakeController::class, 'update'])
        ->middleware('permission:admin.health.edit')->name('health.update')->whereNumber(['booking', 'traveler']);
});
