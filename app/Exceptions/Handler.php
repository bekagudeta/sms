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
    // Detect DB connection error specifically
    if ($e instanceof QueryException || $e instanceof PDOException) {
        
        $errorCode = $e->getCode();

        // MySQL connection refused / server down
        if (in_array($errorCode, [2002, 2006, 1049])) {

            $message = 'Database is currently unavailable. Please try again later.';

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $message
                ], 503);
            }

            // Safe fallback if view missing
            if (view()->exists('errors.db_connection')) {
                return response()->view('errors.db_connection', [
                    'message' => $message
                ], 503);
            }

            return response($message, 503);
        }
    }

    return parent::render($request, $e);
}
}
