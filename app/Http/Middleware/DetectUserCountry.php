<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;

class DetectUserCountry
{
    public function handle(Request $request, Closure $next)
    {
        if (! session()->has('user_currency')) {
            try {
                $ip       = $request->ip();
                $position = Location::get($ip);
                $country  = $position ? strtoupper($position->countryCode) : 'US';
            } catch (\Throwable $e) {
                $country = 'US';
            }

            $currency = ($country === 'IN') ? 'INR' : 'USD';
            $gateway  = ($country === 'IN') ? 'razorpay' : 'paypal';

            session([
                'user_currency'  => $currency,
                'user_country'   => $country,
                'payment_gateway'=> $gateway,
            ]);
        }

        return $next($request);
    }
}
