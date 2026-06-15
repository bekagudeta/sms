<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Public self-registration is DISABLED for this closed university system.
     *
     * Accounts are provisioned only by administrators or via bulk Excel import
     * (see App\Imports\StudentsImport / TeachersImport), so anyone cannot create
     * an account and gain access. These methods abort as defense-in-depth even if
     * a route is ever re-added; the register routes themselves are removed from
     * routes/auth.php.
     */
    public function create(): Response
    {
        abort(404);
    }

    public function store(Request $request): RedirectResponse
    {
        abort(404);
    }
}
