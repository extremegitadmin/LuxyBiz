<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Only count what this person is allowed to open.
        $cards = [];
        if ($user->can('users.view')) {
            $cards[] = ['Users', User::count(), 'bi-people', route('users.index')];
        }
        if ($user->can('roles.view')) {
            $cards[] = ['Roles', Role::count(), 'bi-shield-lock', route('roles.index')];
        }
        if ($user->can('products.view')) {
            $cards[] = ['Products', Product::count(), 'bi-box-seam', route('products.index')];
        }

        return view('dashboard', compact('cards'));
    }
}
