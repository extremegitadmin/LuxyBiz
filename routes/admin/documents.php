<?php

use App\Http\Controllers\Admin\CustomerDocumentAccessController;
use App\Http\Controllers\Admin\CustomerDocumentController;
use Illuminate\Support\Facades\Route;

/*
| Asha's customer document routes (G3). Day 22: upload, delete. Day 23: view and download (each opening is logged).
| Loaded from routes/web.php with: require __DIR__ . '/admin/documents.php';
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission'])->group(function () {
    Route::post('bookings/{booking}/documents', [CustomerDocumentController::class, 'store'])
        ->middleware('permission:admin.documents.upload')->name('documents.store')->whereNumber('booking');
    Route::delete('documents/{document}', [CustomerDocumentController::class, 'destroy'])
        ->middleware('permission:admin.documents.delete')->name('documents.destroy')->whereNumber('document');

    // Opening a file: 30 per minute per person, so nobody can pull every passport in one go.
    Route::get('documents/{document}/view', [CustomerDocumentAccessController::class, 'view'])
        ->middleware(['permission:admin.documents.view', 'throttle:30,1'])->name('documents.view')->whereNumber('document');
    Route::get('documents/{document}/download', [CustomerDocumentAccessController::class, 'download'])
        ->middleware(['permission:admin.documents.download', 'throttle:30,1'])->name('documents.download')->whereNumber('document');
});
