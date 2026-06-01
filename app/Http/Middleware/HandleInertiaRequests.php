<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        
        $permissions = [];
        $roles = [];
        
        if ($user) {
            // Ensure permissions are properly loaded
            $user->loadMissing('permissions', 'roles');
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            $roles = $user->getRoleNames()->toArray();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->load('roles', 'permissions') : null,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'errors' => function () use ($request) {
                return $request->session()->get('errors') 
                    ? $request->session()->get('errors')->getBag('default')->getMessages() 
                    : (object) [];
            },
            'csrf_token' => $request->session()->token(),
        ]);
    }
}
