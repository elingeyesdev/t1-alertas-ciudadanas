<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivale al RoleGuard + decorador @Roles() de NestJS.
 * Uso en rutas: ->middleware('role:admin,autoridad')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403, 'Forbidden resource');
        }

        return $next($request);
    }
}
