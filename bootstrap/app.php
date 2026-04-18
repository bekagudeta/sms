<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->trustProxies(
            '*',
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (QueryException|\PDOException $e, $request) {
            // Check if we're on an auth route - if so, don't show database error
            $path = $request->path();

            if (str_starts_with($path, 'login') || str_starts_with($path, 'register') || str_starts_with($path, 'password')) {
                // For auth routes, don't intercept database errors - let them fail normally
                // This will allow the login page to load even without database
                return null;
            }

            $message = 'We are currently unable to process your request due to temporary system availability. Please try again shortly.';

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 503);
            }

            // return response()->view('errors.db_connection', ['message' => $message], 503);
        });
    })->create();