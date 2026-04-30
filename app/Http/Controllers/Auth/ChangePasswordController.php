<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ChangePasswordController extends Controller
{
    public function showForm()
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login')
                ->with('status', 'Your session has expired. Please log in again.');
        }

        $updateData = [
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ];

        if (Schema::hasColumn('users', 'plain_password')) {
            $updateData['plain_password'] = '';
        }

        $user->update($updateData);

        return redirect('/')->with('success', 'Password changed successfully.');
    }
}
