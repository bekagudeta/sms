<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        Vite::prefetch(3);

        // bind middleware aliases so kernel->terminate() can resolve them
        // by name when cleaning up after a request.
        $this->app->alias(\Spatie\Permission\Middleware\PermissionMiddleware::class, 'permission');
        $this->app->alias(\Spatie\Permission\Middleware\RoleMiddleware::class, 'role');
        $this->app->alias(\Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class, 'role_or_permission');

        // Register UserObserver for integrity enforcement
        \App\Models\User::observe(\App\Observers\UserObserver::class);
    }
}
