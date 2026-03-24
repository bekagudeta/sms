<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use App\Models\User;
use App\Observers\UserObserver;



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
        $this->app->alias(PermissionMiddleware::class, 'permission');
        $this->app->alias(RoleMiddleware::class, 'role');
        $this->app->alias(RoleOrPermissionMiddleware::class, 'role_or_permission');

        // Register UserObserver for integrity enforcement
        User::observe(UserObserver::class);
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            abort(500, 'Database is not available');
        }
    }
}
