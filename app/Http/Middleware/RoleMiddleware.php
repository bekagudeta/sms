<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles  comma-separated list of allowed roles
     */
    public function handle(Request $request, Closure $next, string $roles = null): Response
    {
        // if no roles specified deny access (prevent accidental bypass)
        if (empty($roles)) {
            abort(403, 'Unauthorized. Role validation failed: no roles specified.');
        }

        $allowed = array_map('trim', explode(',', $roles));

        $user = $request->user();

        if (!$user || !$user->hasAnyRole($allowed)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
