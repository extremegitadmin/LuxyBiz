<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TourController;
use App\Http\Controllers\Admin\ObjectiveController;
use App\Http\Controllers\Admin\DepartureController;
use App\Http\Controllers\Admin\TourCategoryController;
use App\Http\Controllers\Admin\DepartureOperationsController;
use App\Http\Controllers\Admin\TourPackageController;

Route::prefix('admin')->name('admin.')->group(function () {

    // Tour categories (the existing category module is reused).
    Route::get('tour-categories', [TourCategoryController::class, 'index'])->name('tour-categories.index');
    Route::post('tour-categories', [TourCategoryController::class, 'store'])->name('tour-categories.store');
    Route::get('tour-categories/{id}/edit', [TourCategoryController::class, 'edit'])->name('tour-categories.edit');
    Route::put('tour-categories/{id}', [TourCategoryController::class, 'update'])->name('tour-categories.update');
    Route::delete('tour-categories/{id}', [TourCategoryController::class, 'destroy'])->name('tour-categories.destroy');

    // Tour management.
    Route::resource('tours', TourController::class)->except(['show']); 
    Route::get('tours/{tour}/preview', [TourController::class, 'preview'])->name('tours.preview');
    Route::post('tours/{tour}/duplicate', [TourController::class, 'duplicate'])->name('tours.duplicate');
    Route::patch('tours/{tour}/publish', [TourController::class, 'togglePublish'])->name('tours.publish');
    Route::delete('tours/{tour}/permanent-delete',[TourController::class, 'permanentDelete'])->name('tours.permanent-delete');
    
    // Itinerary CRUD.
    Route::get('tours/{tour}/itinerary', [TourController::class, 'itinerary'])->name('tours.itinerary');
    Route::post('tours/{tour}/itinerary', [TourController::class, 'storeDay'])->name('tours.itinerary.store');
    Route::put('tours/{tour}/itinerary/{day}', [TourController::class, 'updateDay'])->name('tours.itinerary.update');
    Route::delete('tours/{tour}/itinerary/{day}', [TourController::class, 'destroyDay'])->name('tours.itinerary.destroy');

    // Gallery image deletion.
    Route::delete('tours/{tour}/gallery/{image}', [TourController::class, 'destroyGalleryImage'])
        ->name('tours.gallery.destroy');

    // Objectives
    Route::resource('objectives', ObjectiveController::class)->only(['index', 'store', 'update', 'destroy']);

    // Tour Packages (Silver, Gold, Platinum)
    Route::prefix('tours/{tour}/packages')->name('tours.packages.')->controller(TourPackageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{package}/edit', 'edit')->name('edit');
        Route::put('/{package}', 'update')->name('update');
        Route::delete('/{package}', 'destroy')->name('destroy');
    });

    // Departures
    Route::resource('departures', DepartureController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::patch('departures/{departure}/toggle', [DepartureController::class, 'toggle'])
        ->name('departures.toggle');

    // Departure Operations
    Route::prefix('departure-operations')->name('departure-operations.')->group(function () {
        Route::get('/', [DepartureOperationsController::class, 'index'])->name('index');
        Route::get('/{departure}', [DepartureOperationsController::class, 'show'])->name('show');
        Route::get('/{departure}/manifest', [DepartureOperationsController::class, 'manifest'])->name('manifest');
        Route::patch('/{departure}/toggle-sales', [DepartureOperationsController::class, 'toggleSales'])->name('toggle-sales');

    });

});