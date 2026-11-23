<?php 
    use App\Http\Controllers\Site\FrontendController;
    use App\Http\Controllers\Site\CustomerController;
    use Illuminate\Support\Facades\Route;

    Route::get('/', [FrontendController::class, 'index'])->name('index');
    Route::get('signin', [CustomerController::class, 'signin'])->name('signin');