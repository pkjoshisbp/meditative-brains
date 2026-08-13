<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\TrustedLoginDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class OtpLoginService
{
    public const TRUSTED_DEVICE_COOKIE = 'trusted_login_device';
    public const TRUSTED_DEVICE_DAYS = 30;

    private ?array $userColumns = null;

    public function findUser(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return User::whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first();
        }

        if (preg_match('/^\+?[0-9]{7,15}$/', $identifier)) {
            if (! $this->hasUserColumn('mobile')) {
                return null;
            }

            $candidates = array_unique(array_filter([
                $identifier,
                ltrim($identifier, '+'),
                preg_replace('/^\+?91/', '', $identifier),
            ]));

            return User::whereIn('mobile', $candidates)->first();
        }

        $query = User::query();

        if ($this->hasUserColumn('username')) {
            $query->where('username', $identifier)->orWhere('name', $identifier);
        } else {
            $query->where('name', $identifier);
        }

        return $query->first();
    }

    public function issue(string $identifier): ?array
    {
        $recipient = $this->resolveOtpRecipient($identifier);

        if (! $recipient) {
            return null;
        }

        OtpCode::whereIn('identifier', $recipient['verification_identifiers'])
            ->where('used', false)
            ->update(['used' => true]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $deliveryResults = [];

        foreach ($recipient['deliveries'] as $delivery) {
            OtpCode::create([
                'identifier' => $delivery['identifier'],
                'type' => $delivery['type'],
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);

            $deliveryResults[] = $this->deliverCode($delivery['identifier'], $delivery['type'], $code);
        }

        $recipient['delivery_ok'] = in_array(true, $deliveryResults, true);

        return $recipient;
    }

    public function verify(string $identifier, string $code): ?User
    {
        $recipient = $this->resolveOtpRecipient($identifier);

        if (! $recipient) {
            return null;
        }

        return $this->verifyIssuedCode($recipient['user'], $recipient['verification_identifiers'], $code)
            ? $recipient['user']
            : null;
    }

    public function verifyIssuedCode(User $user, string|array $identifiers, string $code): bool
    {
        $identifiers = array_values(array_unique(array_map('trim', (array) $identifiers)));

        $otp = OtpCode::whereIn('identifier', $identifiers)
            ->where('code', trim($code))
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        OtpCode::whereIn('identifier', $identifiers)
            ->where('code', trim($code))
            ->where('used', false)
            ->update(['used' => true]);

        return true;
    }

    public function trustedDeviceMatchesUser(Request $request, User $user): ?TrustedLoginDevice
    {
        $device = $this->trustedDeviceFromRequest($request);

        if (! $device || (int) $device->user_id !== (int) $user->id) {
            return null;
        }

        $device->forceFill([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        return $device;
    }

    public function createTrustedDevice(User $user, Request $request): string
    {
        $selector = Str::random(24);
        $token = Str::random(64);

        TrustedLoginDevice::create([
            'user_id' => $user->id,
            'selector' => $selector,
            'token_hash' => hash('sha256', $token),
            'device_name' => Str::limit((string) $request->userAgent(), 120, ''),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::TRUSTED_DEVICE_DAYS),
        ]);

        return $selector.'|'.$token;
    }

    public function trustedDeviceCookieMinutes(): int
    {
        return self::TRUSTED_DEVICE_DAYS * 24 * 60;
    }

    public function maskIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            [$name, $domain] = explode('@', $identifier, 2);
            $visible = Str::substr($name, 0, 2);

            return $visible.str_repeat('*', max(Str::length($name) - 2, 2)).'@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $identifier);
        if ($digits === '') {
            return $identifier;
        }

        return str_repeat('*', max(strlen($digits) - 4, 2)).substr($digits, -4);
    }

    public function describeDestinations(array $deliveries): string
    {
        $masked = array_map(
            function (array $delivery): string {
                $destination = $this->maskIdentifier($delivery['identifier']);

                return match ($delivery['type']) {
                    'sms' => 'SMS at '.$destination,
                    'whatsapp' => 'WhatsApp at '.$destination,
                    default => $destination,
                };
            },
            $deliveries
        );

        if (count($masked) <= 1) {
            return $masked[0] ?? 'your registered contact';
        }

        $last = array_pop($masked);

        return implode(', ', $masked).' and '.$last;
    }

    private function resolveOtpRecipient(string $identifier): ?array
    {
        $identifier = trim($identifier);
        $user = $this->findUser($identifier);

        if (! $user) {
            return null;
        }

        if (str_contains($identifier, '@') && $user->email) {
            return [
                'user' => $user,
                'deliveries' => [[
                    'type' => 'email',
                    'identifier' => Str::lower(trim($user->email)),
                ]],
                'verification_identifiers' => [Str::lower(trim($user->email))],
            ];
        }

        if (preg_match('/^\+?[0-9]{7,15}$/', $identifier) && $user->mobile) {
            $deliveries = [[
                'type' => 'sms',
                'identifier' => trim($user->mobile),
            ], [
                'type' => 'whatsapp',
                'identifier' => trim($user->mobile),
            ]];

            if ($user->email) {
                $deliveries[] = [
                    'type' => 'email',
                    'identifier' => Str::lower(trim($user->email)),
                ];
            }

            return [
                'user' => $user,
                'deliveries' => $deliveries,
                'verification_identifiers' => array_column($deliveries, 'identifier'),
            ];
        }

        if ($user->email) {
            return [
                'user' => $user,
                'deliveries' => [[
                    'type' => 'email',
                    'identifier' => Str::lower(trim($user->email)),
                ]],
                'verification_identifiers' => [Str::lower(trim($user->email))],
            ];
        }

        if ($user->mobile) {
            return [
                'user' => $user,
                'deliveries' => [[
                    'type' => 'sms',
                    'identifier' => trim($user->mobile),
                ], [
                    'type' => 'whatsapp',
                    'identifier' => trim($user->mobile),
                ]],
                'verification_identifiers' => [trim($user->mobile)],
            ];
        }

        return null;
    }

    private function deliverCode(string $identifier, string $type, string $code): bool
    {
        if ($type === 'email') {
            try {
                Mail::raw(
                    "Your Mental Fitness login code is {$code}. It expires in 10 minutes.",
                    static function ($message) use ($identifier) {
                        $message->to($identifier)
                            ->subject('Your Mental Fitness login code');
                    }
                );

                return true;
            } catch (Throwable $exception) {
                Log::error('[OTP] Failed to send email OTP', [
                    'identifier' => $identifier,
                    'error' => $exception->getMessage(),
                ]);

                return false;
            }
        }

        if ($type === 'whatsapp') {
            return app(WhatsAppService::class)->sendOtp($identifier, $code);
        }

        $message = "Your MentalFitness OTP is {$code}. Valid for 10 minutes. Do not share.";
        $sent = app(SmsGatewayService::class)->dispatch($identifier, $message);

        if (! $sent) {
            Log::warning("[OTP] SMS gateway unavailable — identifier={$identifier} code={$code}");
        }

        return $sent;
    }

    private function trustedDeviceFromRequest(Request $request): ?TrustedLoginDevice
    {
        $cookie = (string) $request->cookie(self::TRUSTED_DEVICE_COOKIE, '');

        if (! str_contains($cookie, '|')) {
            return null;
        }

        [$selector, $token] = explode('|', $cookie, 2);

        if ($selector === '' || $token === '') {
            return null;
        }

        $device = TrustedLoginDevice::where('selector', $selector)
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return null;
        }

        if (! hash_equals($device->token_hash, hash('sha256', $token))) {
            return null;
        }

        return $device;
    }

    private function hasUserColumn(string $column): bool
    {
        return in_array($column, $this->userColumns(), true);
    }

    private function userColumns(): array
    {
        if ($this->userColumns !== null) {
            return $this->userColumns;
        }

        return $this->userColumns = Schema::getColumnListing((new User())->getTable());
    }
}
