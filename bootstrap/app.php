<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Contracts\Exceptions\HasProblemDetailsInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global or route middleware configuration
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 🛡️ 1. Domain Business Invariant Exceptions (Self-Mapping RFC 7807 Problem Details)
        $exceptions->render(function (HasProblemDetailsInterface $e, Request $request) {
            $payload = array_merge([
                'type'      => 'https://errors.flashcommerce.com/' . strtolower(str_replace('_', '-', $e->getErrorCode())),
                'title'     => $e->getTitle(),
                'status'    => $e->getStatusCode(),
                'detail'    => $e->getDetail(),
                'code'      => $e->getErrorCode(),
                'instance'  => '/' . ltrim($request->path(), '/'),
                'timestamp' => now()->toIso8601String(),
            ], $e->getExtensions());

            return response()->json($payload, $e->getStatusCode(), $e->getHeaders());
        });

        // 🛡️ 2. Physical Database Concurrency & Constraint Collisions (MySQL Error 1062)
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'type'      => 'https://errors.flashcommerce.com/resource-already-exists',
                    'title'     => 'Conflict',
                    'status'    => Response::HTTP_CONFLICT,
                    'detail'    => 'A record with this identifier already exists (Email or Unique Key collision).',
                    'code'      => 'RESOURCE_ALREADY_EXISTS',
                    'instance'  => '/' . ltrim($request->path(), '/'),
                    'timestamp' => now()->toIso8601String(),
                ], Response::HTTP_CONFLICT);
            }
        });
    })->create();
