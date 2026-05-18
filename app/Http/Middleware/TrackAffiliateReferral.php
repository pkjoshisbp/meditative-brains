<?php

namespace App\Http\Middleware;

use App\Services\AffiliateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAffiliateReferral
{
    public function __construct(private AffiliateService $affiliateService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->affiliateService->captureReferral($request);

        return $next($request);
    }
}