<?php

use App\Http\Controllers\Admin\CancellationController;
use Illuminate\Support\Facades\Route;

/*
| Asha's cancellation routes. Day 16: list. Day 17: detail. Days 18 to 21 add override, approve/reject, mark refunded.
| Loaded from routes/web.php with: require __DIR__ . '/admin/cancellations.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('cancellations', [CancellationController::class, 'index'])
        ->middleware('permission:admin.cancellations.view')->name('cancellations.index');
    Route::get('cancellations/{cancellation}', [CancellationController::class, 'show'])
        ->middleware('permission:admin.cancellations.view')->name('cancellations.show')->whereNumber('cancellation');
});
