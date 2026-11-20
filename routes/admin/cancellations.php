<?php

use App\Http\Controllers\Admin\CancellationController;
use Illuminate\Support\Facades\Route;

/*
| Asha's cancellation routes. Day 16: list. Day 17: detail. Day 18: refund override. Days 19 to 21 add override, approve/reject, mark refunded.
| Loaded from routes/web.php with: require __DIR__ . '/admin/cancellations.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('cancellations', [CancellationController::class, 'index'])
        ->middleware('permission:admin.cancellations.view')->name('cancellations.index');
    Route::get('cancellations/{cancellation}', [CancellationController::class, 'show'])
        ->middleware('permission:admin.cancellations.view')->name('cancellations.show')->whereNumber('cancellation');
    Route::put('cancellations/{cancellation}/override', [CancellationController::class, 'override'])
        ->middleware('permission:admin.cancellations.override')->name('cancellations.override')->whereNumber('cancellation');
    Route::delete('cancellations/{cancellation}/override', [CancellationController::class, 'resetOverride'])
        ->middleware('permission:admin.cancellations.override')->name('cancellations.override.reset')->whereNumber('cancellation');
});
