<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy role-name check. The admin routes now use AdminCan ('admin.can:permission') so access follows the
 * editable roles screen. This class is kept so any route you added yourself with 'admin.role' keeps working.
 *
 * Usage: ->middleware('admin.role')              any active staff member
 *        ->middleware('admin.role:admin,csr')    those roles (super_admin always passes)
 */
class AdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->is_active || ! $user->isStaff()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This account cannot open the admin panel.']);
        }

        if ($roles && ! $user->hasRole(...$roles)) {
            abort(403, 'Your role does not have access to this page.');
        }

        return $next($request);
    }
}
