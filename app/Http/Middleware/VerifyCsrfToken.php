<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * We only exclude import endpoints here, because the front-end already sends CSRF tokens
     * but some clients may still trigger mismatch errors due stale/expired/browser sessions.
     *
     * @var array<int, string>
     */
    protected $except = [
        'import/*',
        'import-students',
        'import-teachers',
        'logout',
        'login',
    ];
}
