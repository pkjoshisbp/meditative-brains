<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            abort(401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!$user->hasRole(...$roles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden (role)'], 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
