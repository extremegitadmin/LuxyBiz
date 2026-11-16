<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // The Super Admin passes every check.
        Gate::before(fn (User $user) => $user->is_super ? true : null);

        // One gate per permission, so @can('users.create') and $user->can('users.create') work everywhere.
        foreach (Permission::allSlugs() as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }
    }
}
