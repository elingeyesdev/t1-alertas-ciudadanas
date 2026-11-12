<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',      // prefijo /api (igual que setGlobalPrefix('/api'))
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('panel*') ? route('panel.login') : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Formato de error igual al de NestJS:
         * { "statusCode": 404, "message": "...", "error": "Not Found" }
         * Así el frontend web y la app móvil no necesitan cambios.
         */
        $nestError = function (int $status, string|array $message, array $extra = []) {
            return response()->json(array_merge([
                'statusCode' => $status,
                'message' => $message,
                'error' => Response::$statusTexts[$status] ?? 'Error',
            ], $extra), $status);
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($nestError) {
            if ($request->is('api/*')) {
                return $nestError(400, collect($e->errors())->flatten()->values()->all(), ['errors' => $e->errors()]);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($nestError) {
            if ($request->is('api/*')) {
                return $nestError(401, 'Unauthorized');
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($nestError) {
            if ($request->is('api/*')) {
                return $nestError(403, 'Forbidden resource');
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($nestError) {
            if ($request->is('api/*')) {
                return $nestError(404, 'Recurso no encontrado');
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($nestError) {
            // 23503 = violación de llave foránea (ej. borrar un rol que tiene usuarios)
            if ($request->is('api/*') && $e->getCode() === '23503') {
                return $nestError(409, 'No se puede eliminar o modificar: existen registros relacionados');
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($nestError) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$status] ?? 'Error');

                return $nestError($status, $message);
            }
        });
    })->create();
