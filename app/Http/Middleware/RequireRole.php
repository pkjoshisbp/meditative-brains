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
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (empty($roles)) { return $next($request); }
        if (!$user->role || !in_array($user->role, $roles)) {
            return response()->json(['message' => 'Forbidden (role)'], 403);
        }
        return $next($request);
    }
}
