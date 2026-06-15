<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ChangePasswordController extends Controller
{
    public function showForm()
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function update(Request $request)
    {
        // Determine if this is a forced password change (first login) or user-initiated
        $user = Auth::user();
        $isForcedChange = $user && $user->must_change_password;

        // Validation rules depend on whether it's forced or user-initiated
        $rules = [
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ];

        // Only require current_password for user-initiated changes
        if (!$isForcedChange) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules);

        if (! $user) {
            return redirect()->route('login')
                ->with('status', 'Your session has expired. Please log in again.');
        }

        // CRITICAL: Build update data with ONLY password-related fields
        // Do NOT include any other fields that could trigger observers or side effects
        $updateData = [
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ];

        // Use raw update with timestamp to avoid observer issues while preserving data integrity
        $user->update($updateData);
        
        // Force reload from database to ensure fresh data
        $user = $user->fresh();

        // CRITICAL: Regenerate session after password change to issue fresh CSRF token
        $request->session()->regenerate();

        // Refresh user in auth to reflect changes
        Auth::setUser($user);
        
        // Explicitly set the flag in the session to ensure it's persisted
        $request->session()->put('auth.password_changed', true);

        // Determine redirect based on whether it was a forced change
        if ($isForcedChange) {
            // First login password change - redirect to appropriate dashboard
            $role = $user->roles->first()?->name ?? 'admin';
            $dashboardRoutes = [
                'admin' => '/admin/dashboard',
                'scheduler' => '/scheduler/dashboard',
                'teacher' => '/teacher/dashboard',
                'student' => '/student/dashboard',
            ];
            $route = $dashboardRoutes[$role] ?? '/profile';
            return redirect($route)->with('success', 'Password changed successfully. Welcome!');
        }

        return redirect('/profile')->with('success', 'Password changed successfully.');
    }
}
