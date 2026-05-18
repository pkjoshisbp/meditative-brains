<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stevebauman\Location\Facades\Location;

class DetectUserCountry
{
    public function handle(Request $request, Closure $next)
    {
        $clientIp = $this->resolveClientIp($request);

        if (
            ! session()->has('user_currency')
            || session('detected_client_ip') !== $clientIp
        ) {
            [$country, $currency, $gateway] = $this->resolveGeoContext($clientIp);

            session([
                'detected_client_ip' => $clientIp,
                'user_currency'      => $currency,
                'user_country'       => $country,
                'payment_gateway'    => $gateway,
            ]);
        }

        return $next($request);
    }

    private function resolveGeoContext(string $ip): array
    {
        try {
            $position = Location::get($ip);
            $country = $position && $position->countryCode
                ? strtoupper($position->countryCode)
                : 'US';
        } catch (\Throwable) {
            $country = 'US';
        }

        $currency = $country === 'IN' ? 'INR' : 'USD';
        $gateway = $country === 'IN' ? 'razorpay' : 'paypal';

        return [$country, $currency, $gateway];
    }

    private function resolveClientIp(Request $request): string
    {
        $candidates = [
            $request->headers->get('cf-connecting-ip'),
            $request->headers->get('x-real-ip'),
            $request->headers->get('x-forwarded-for'),
            $request->ip(),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            foreach (explode(',', $candidate) as $possibleIp) {
                $possibleIp = trim($possibleIp);

                if ($possibleIp === '' || ! filter_var($possibleIp, FILTER_VALIDATE_IP)) {
                    continue;
                }

                if ($this->isPublicIp($possibleIp)) {
                    return $possibleIp;
                }
            }
        }

        return '8.8.8.8';
    }

    private function isPublicIp(string $ip): bool
    {
        if (Str::startsWith($ip, ['127.', '10.', '192.168.'])) {
            return false;
        }

        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)) {
            return false;
        }

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
