<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'plain_password' => null,
        ]);

        // Assign a default role so permissions work immediately.
        // We default to "student" for newly registered accounts.
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $user->assignRole('student');
            
            try {
                // Create student record for this user
                \App\Models\Student::create([
                    'student_id' => 'STU' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'first_name' => explode(' ', trim($user->name))[0] ?? null,
                    'last_name' => trim(str_replace(explode(' ', trim($user->name))[0] ?? '', '', $user->name)),
                    'department_id' => \App\Models\Department::first()?->id ?? 1,
                    'semester' => 1,
                    'level' => 'undergraduate',
                    'section' => 'A',
                    'enrollment_date' => now()->toDateString(),
                ]);
            } catch (\Exception $e) {
                // Log the error but don't fail registration
                \Log::error('Failed to create student record during registration: ' . $e->getMessage());
            }
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
