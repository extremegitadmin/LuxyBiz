<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Only count what this person is allowed to open.
        $cards = [];
        if ($user->can('users.view')) {
            $cards[] = ['Users', User::count(), 'bi-people', route('admin.users.index')];
        }
        if ($user->can('roles.view')) {
            $cards[] = ['Roles', Role::count(), 'bi-shield-lock', route('admin.roles.index')];
        }

        return view('admin/dashboard', compact('cards'));
    }
}
