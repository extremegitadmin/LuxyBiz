<?php

use App\Http\Controllers\Admin\CancellationController;
use App\Http\Controllers\Admin\CancellationSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Asha's cancellation routes.
| Day 14: cancellation rules settings. Day 16: list. Day 17: detail. Day 18: refund override.
| Days 19 to 21 add approve/reject and mark refunded.
| Loaded from routes/web.php with: require __DIR__ . '/admin/cancellations.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    // Cancellation rules settings (Day 14)
    Route::get('settings/cancellation', [CancellationSettingsController::class, 'edit'])
        ->middleware('permission:admin.settings.cancellation.view')->name('settings.cancellation');
    Route::put('settings/cancellation', [CancellationSettingsController::class, 'update'])
        ->middleware('permission:admin.settings.cancellation.edit')->name('settings.cancellation.update');

    // Requests: list (Day 16), detail (Day 17), refund override (Day 18)
    Route::get('cancellations', [CancellationController::class, 'index'])
        ->middleware('permission:admin.cancellations.view')->name('cancellations.index');
    Route::get('cancellations/{cancellation}', [CancellationController::class, 'show'])
        ->middleware('permission:admin.cancellations.view')->name('cancellations.show')->whereNumber('cancellation');
    Route::put('cancellations/{cancellation}/override', [CancellationController::class, 'override'])
        ->middleware('permission:admin.cancellations.override')->name('cancellations.override')->whereNumber('cancellation');
    Route::delete('cancellations/{cancellation}/override', [CancellationController::class, 'resetOverride'])
        ->middleware('permission:admin.cancellations.override')->name('cancellations.override.reset')->whereNumber('cancellation');
});