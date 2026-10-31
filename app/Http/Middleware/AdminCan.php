<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('admin.can')                       any active staff member
 *        ->middleware('admin.can:tours.manage')          needs that permission
 *        ->middleware('admin.can:reports.view,bookings.view')   needs ANY of them
 * A super admin always passes. Permissions are edited at Admin > Roles and permissions.
 */
class AdminCan
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
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

        if ($permissions && ! $user->hasPermission(...$permissions)) {
            abort(403, 'Your role does not have permission to open this page.');
        }

        return $next($request);
    }
}
