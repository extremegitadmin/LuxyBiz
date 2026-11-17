<?php

use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo('/admin/dashboard');
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
        $middleware->redirectUsersTo('/dashboard');   // a signed-in person who opens /login goes to the dashboard
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();