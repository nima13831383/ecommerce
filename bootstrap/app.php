<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            if ($exception instanceof ModelNotFoundException || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404)) {
                return response()->json([
                    'message' => 'The requested resource was not found.',
                    'errors' => [],
                    'code' => 'not_found',
                ], 404);
            }

            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Authentication is required.',
                    'errors' => [],
                    'code' => 'unauthenticated',
                ], 401);
            }

            if ($exception instanceof TokenMismatchException) {
                return response()->json([
                    'message' => 'The CSRF token is invalid or missing.',
                    'errors' => [],
                    'code' => 'csrf_token_mismatch',
                ], 419);
            }

            report($exception);

            return response()->json([
                'message' => 'An unexpected error occurred.',
                'errors' => [],
                'code' => 'server_error',
            ], 500);
        });
    })->create();
