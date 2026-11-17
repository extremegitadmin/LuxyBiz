
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;

use App\Http\Controllers\Admin\TourController;
use App\Http\Controllers\Admin\ObjectiveController;
use App\Http\Controllers\Admin\DepartureController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TourCategoryController;


// Redirect home to admin dashboard
Route::redirect('/', '/admin/dashboard');

Route::prefix('admin')->name('admin.')->group(function () {

    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.submit');
    });

    // Authenticated routes
    Route::middleware(['auth', 'permission'])->group(function () {

        Route::post('logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // User management
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.view')->name('users.index');

        Route::get('users/create', [UserController::class, 'create'])
            ->middleware('permission:users.create')->name('users.create');

        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users.create')->name('users.store');

        Route::get('users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:users.edit')->name('users.edit');

        Route::put('users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.edit')->name('users.update');

        Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])
            ->middleware('permission:users.edit')->name('users.toggle');

        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')->name('users.destroy');

        // Role management
        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.view')->name('roles.index');

        Route::get('roles/create', [RoleController::class, 'create'])
            ->middleware('permission:roles.create')->name('roles.create');

        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create')->name('roles.store');

        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:roles.edit')->name('roles.edit');

        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.edit')->name('roles.update');

        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete')->name('roles.destroy');
    });

    // Tour categories (the existing category module is reused).
    Route::get('tour-categories', [TourCategoryController::class, 'index'])->name('tour-categories.index');
    Route::post('tour-categories', [TourCategoryController::class, 'store'])->name('tour-categories.store');
    Route::get('tour-categories/{id}/edit', [TourCategoryController::class, 'edit'])->name('tour-categories.edit');
    Route::put('tour-categories/{id}', [TourCategoryController::class, 'update'])->name('tour-categories.update');
    Route::delete('tour-categories/{id}', [TourCategoryController::class, 'destroy'])->name('tour-categories.destroy');

    // Tour management.
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

    Route::resource('tours', TourController::class)->except(['show']);


 

    // Objectives
    Route::resource('objectives', ObjectiveController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Departures
    Route::resource('departures', DepartureController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::patch('departures/{departure}/toggle', [DepartureController::class, 'toggle'])
        ->name('departures.toggle');
});

// Additional route files
require __DIR__ . '/admin/bookings.php';
require __DIR__ . '/admin/payments.php';
