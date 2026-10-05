<?php

use App\Http\Middleware\RequestId;
use App\Http\Middleware\RequireApiScope;
use App\Http\Middleware\WorkspaceAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestId::class);
        $middleware->alias([
            'workspace.admin' => WorkspaceAdmin::class,
            'api.scope' => RequireApiScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'The request payload is invalid.',
                    'details' => $exception->errors(),
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => $exception->getMessage() ?: 'The requested resource was not found.',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                429 => 'RATE_LIMITED',
                default => 'HTTP_ERROR',
            };

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $exception->getMessage() ?: 'The request could not be completed.',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], $status);
        });
    })
    ->create();
