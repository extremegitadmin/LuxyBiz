<?php

use App\Http\Controllers\Admin\CustomerDocumentController;
use Illuminate\Support\Facades\Route;

/*
| Asha's customer document routes (G3). Day 22: upload, delete. Day 23 adds view and download.
| Loaded from routes/web.php with: require __DIR__ . '/admin/documents.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    Route::post('bookings/{booking}/documents', [CustomerDocumentController::class, 'store'])
        ->middleware('permission:admin.documents.upload')->name('documents.store')->whereNumber('booking');
    Route::delete('documents/{document}', [CustomerDocumentController::class, 'destroy'])
        ->middleware('permission:admin.documents.delete')->name('documents.destroy')->whereNumber('document');
});
