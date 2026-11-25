
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\InsuranceController;

use App\Http\Controllers\Admin\TourController;
use App\Http\Controllers\Admin\ObjectiveController;
use App\Http\Controllers\Admin\DepartureController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TourCategoryController;
use App\Http\Controllers\Admin\DepartureOperationsController;

// Redirect home to admin dashboard
//Route::redirect('/', '/admin/dashboard');

Route::prefix('admin')->name('admin.')->group(function () {

    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.submit');
        Route::get('forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
        Route::post('forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
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

        // Insurance options (checkout step 3)
        Route::get('insurance', [InsuranceController::class, 'index'])->name('insurance.index');
        Route::post('insurance', [InsuranceController::class, 'store'])->name('insurance.store');
        Route::put('insurance/{option}', [InsuranceController::class, 'update'])->name('insurance.update')->whereNumber('option');
        Route::delete('insurance/{option}', [InsuranceController::class, 'destroy'])->name('insurance.destroy')->whereNumber('option');
    });
});

// Additional route files
require __DIR__ . '/admin/bookings.php';
require __DIR__ . '/admin/payments.php';
require __DIR__ . '/admin/cancellations.php';
require __DIR__.'/admin/catalogue.php';
require __DIR__.'/admin/cms.php';
require __DIR__ . '/admin/documents.php';
require __DIR__ . '/admin/health.php';
require __DIR__ . '/admin/assisted.php';
require __DIR__ . '/site.php';