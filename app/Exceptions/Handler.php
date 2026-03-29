<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        // Add non-reportable exceptions here.
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Leave empty unless you need custom reportable callbacks.
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof QueryException || $e instanceof PDOException) {

            $errorCode = $e->getCode();

            if (in_array($errorCode, [2002, 2006, 1045, 1049])) {
                // If the error occurred while attempting login, send user back to login with a friendly message
                if ($request->is('login')) {
                    return redirect()->route('login')
                        ->withErrors(['email' => 'Cannot connect to authentication database. Please try again shortly.'])
                        ->withInput($request->only('email'));
                }

                return response()->view('errors.db_connection', [
                    'message' => 'We are currently unable to process your request. Please try again shortly.'
                ], 503);
            }
        }

        return parent::render($request, $e);
    }
}
