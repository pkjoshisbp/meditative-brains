<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AccessControlService;

class CheckContentAccess
{
    protected $accessControlService;

    public function __construct(AccessControlService $accessControlService)
    {
        $this->accessControlService = $accessControlService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $contentType
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $contentType = null)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required',
                'code' => 'AUTH_REQUIRED'
            ], 401);
        }

        // Check for expired subscriptions and clean up access
        $this->cleanupExpiredAccess($user);

        // Let the request pass through - individual controllers will check specific access
        return $next($request);
    }

    /**
     * Clean up expired access for user
     */
    private function cleanupExpiredAccess($user)
    {
        // Check for expired subscription
        $activeSubscription = $user->getActiveSubscription();
        if ($activeSubscription && $activeSubscription->isExpired()) {
            // Mark subscription as expired
            $activeSubscription->update(['status' => 'expired']);
            
            // Revoke subscription-based access
            $this->accessControlService->revokeSubscriptionAccess($user, $activeSubscription->id);
        }

        // Deactivate expired individual access controls
        $user->musicAccessControls()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);

        $user->ttsCategoryAccess()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
    }
}
