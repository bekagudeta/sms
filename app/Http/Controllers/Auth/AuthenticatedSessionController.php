<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Get the authenticated user and ensure they have a role.
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        // If the user has no role, try to derive one from the email (useful for seeded accounts).
        if (! $role) {
            $email = strtolower($user->email);
            $roleMap = [
                'admin@' => 'admin',
                'scheduler@' => 'scheduler',
                'teacher@' => 'teacher',
                'student@' => 'student',
            ];

            foreach ($roleMap as $prefix => $mappedRole) {
                if (str_starts_with($email, $prefix)) {
                    // Ensure the role exists before assigning it
                    Role::firstOrCreate(['name' => $mappedRole, 'guard_name' => 'web']);

                    // Assign role to user (using Spatie Laravel Permission package)
                    $user->assignRole($mappedRole);
                    $role = $mappedRole;
                    break;
                }
            }
        }

        if (! $role) {
            Auth::logout();

            return redirect()->route('login')
                ->with('status', 'Your account is not assigned a role. Please contact an administrator.');
        }

        // Force password change if required (check fresh from database)
        // This ensures the flag is read directly from DB, not from cache
        if ($user->fresh()->must_change_password) {
            return redirect('/change-password');
        }

        // Redirect users to their appropriate dashboard based on role
        $dashboardRoutes = [
            'admin' => '/admin/dashboard',
            'scheduler' => '/scheduler/dashboard', 
            'teacher' => '/teacher/dashboard',
            'student' => '/student/dashboard',
        ];

        return redirect($dashboardRoutes[$role] ?? '/dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
