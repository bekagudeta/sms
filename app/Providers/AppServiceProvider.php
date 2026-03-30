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
use App\Services\AutoSchedulerService;
use App\Services\SchedulingEngine;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AutoSchedulerService::class, function ($app) {
            return new AutoSchedulerService();
        });
        
        $this->app->singleton(SchedulingEngine::class, function ($app) {
            return new SchedulingEngine();
        });
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
        // User::observe(UserObserver::class);

        // Do not force a DB health check on every boot; it can prevent the login page
        // from rendering when the connection is misconfigured (which is what we're
        // trying to recover from by redirecting to login).
        // If you want optional DB health checks, do it in a specific admin route.

    }
}
