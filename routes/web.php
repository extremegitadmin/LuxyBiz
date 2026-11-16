<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\TourController;
use App\Http\Controllers\Admin\ObjectiveController;
use App\Http\Controllers\Admin\DepartureController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return view('admin.dashboard');
});


Route::redirect('/', '/admin/dashboard');

// Everything lives under /admin (login at /admin/login). Route names stay the same: route('users.index') etc.
Route::prefix('admin')->name('admin.')->group(function () {

    // One login page for everyone: the Super Admin and every role user.
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.submit');
    });

    // 'permission' with no argument = any signed-in, active user. 'permission:xxx' = must hold that permission.
    Route::middleware(['auth', 'permission'])->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ---- Users ----
        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
        Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->middleware('permission:users.edit')->name('users.toggle');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

        // ---- Roles ----
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    });

    Route::resource('tours', TourController::class)->except('show');
    Route::get('tours/{tour}/preview', [TourController::class, 'preview'])->name('tours.preview');
    Route::post('tours/{tour}/duplicate', [TourController::class, 'duplicate'])->name('tours.duplicate');
    Route::patch('tours/{tour}/publish', [TourController::class, 'togglePublish'])->name('tours.publish');
    Route::resource('objectives', ObjectiveController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('departures', DepartureController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::patch('departures/{departure}/toggle', [DepartureController::class, 'toggle'])->name('departures.toggle');
});

require __DIR__ . '/admin/bookings.php';
require __DIR__ . '/admin/payments.php';

