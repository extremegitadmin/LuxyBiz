<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission')                      any signed-in, active user
 *        ->middleware('permission:users.view')           needs that permission
 *        ->middleware('permission:users.view,roles.view') needs ANY of them
 * The Super Admin always passes.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account is deactivated.']);
        }

        if ($permissions && ! $user->hasPermission(...$permissions)) {
            abort(403, 'Your role does not have permission to open this page.');
        }

        return $next($request);
    }
}
