<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceDeviceLimit
{
    /**
     * Enforce per-user device limit. Optionally auto-register current device if space.
     * Expects auth:sanctum already applied.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if(!$user){ return response()->json(['error'=>'unauthenticated'],401); }

        $deviceUuid = $request->header('X-Device-UUID') ?: $request->input('device_uuid');
        if(!$deviceUuid){
            // Allow if no device context provided (web usage) but still continue.
            return $next($request);
        }

        if(! $user->withinDeviceLimit($deviceUuid)){
            return response()->json(['error'=>'device_limit_reached','limit'=>$user->device_limit ?? 2], 409);
        }

        // Auto-register / update heartbeat
        $user->devices()->updateOrCreate(
            ['device_uuid'=>$deviceUuid],
            [
                'platform' => $request->header('X-Device-Platform'),
                'model' => $request->header('X-Device-Model'),
                'app_version' => $request->header('X-App-Version'),
                'last_ip' => $request->ip(),
                'last_seen_at' => now()
            ]
        );

        return $next($request);
    }
}
