<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // statefulApi() already registers: EncryptCookies, AddQueuedCookiesToResponse,
        // StartSession, and EnsureFrontendRequestsAreStateful for API routes.
        // Do NOT add them again via api(prepend:) — that causes double execution.
        $middleware->statefulApi();
        // Register custom middleware aliases
        $middleware->alias([
            'throttle.login.attempts' => \App\Http\Middleware\ThrottleLoginAttempts::class,
            'user.last_seen' => \App\Http\Middleware\UpdateUserLastSeen::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\UpdateUserLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API requests even without correct headers
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        // Handle unauthenticated requests for API - return JSON with custom message
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated. Please login first.',
                ], 401);
            }
        });

        // Handle model not found (e.g. Document::findOrFail, route model binding)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                // Extract clean model name from FQCN (e.g. "App\Models\Document" → "Document")
                $model = class_basename($e->getModel());

                return response()->json([
                    'message' => "{$model} tidak ditemukan.",
                ], 404);
            }
        });

        // Handle authorization failures (policy denials)
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.',
                ], 403);
            }
        });

        // Handle validation errors with consistent format
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();

