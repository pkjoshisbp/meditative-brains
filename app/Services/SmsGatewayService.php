<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * SmsGatewayService
 *
 * Pushes an sms.send event to the Flutter SMS gateway app via the internal
 * TCP push channel that runs inside the Ratchet WebSocket server process.
 *
 * Flow:
 *   AuthController::sendOtp()
 *       → SmsGatewayService::dispatch()
 *           → TCP socket → 127.0.0.1:8092 (Ratchet push channel)
 *               → TtsWebSocketServer::pushSmsEvent()
 *                   → Flutter SMS gateway WebSocket connection
 *                       → Android SmsManager → actual SMS
 */
class SmsGatewayService
{
    /** Port where the Ratchet process listens for internal push commands. */
    private int $pushPort;

    public function __construct()
    {
        $this->pushPort = (int) env('SMS_PUSH_PORT', 8092);
    }

    /**
     * Dispatch an OTP SMS via the connected Flutter SMS gateway.
     *
     * @param  string  $phone    Destination mobile number (any reasonable format)
     * @param  string  $message  Full SMS text
     * @return bool              true if the payload was delivered to the push channel
     */
    public function dispatch(string $phone, string $message): bool
    {
        $normalized = $this->normalizePhone($phone);

        $payload = json_encode([
            'event'      => 'sms.send',
            'phone'      => $normalized,
            'message'    => $message,
            'request_id' => (string) Str::uuid(),
        ]);

        $socket = @fsockopen('127.0.0.1', $this->pushPort, $errno, $errstr, 2);

        if (!$socket) {
            Log::error("[SMS] Could not connect to push channel on port {$this->pushPort}: {$errstr} ({$errno})");
            return false;
        }

        fwrite($socket, $payload . "\n");
        fclose($socket);

        Log::info("[SMS] Dispatched sms.send to push channel", [
            'phone_raw'  => $phone,
            'phone_e164' => $normalized,
            'port'       => $this->pushPort,
        ]);

        return true;
    }

    /**
     * Normalize a phone number to E.164 format.
     *
     * Handles common Indian formats; falls back to prefixing '+' if the number
     * already looks like it has a country code.
     *
     * Examples:
     *   "7978628122"      → "+917978628122"   (10-digit mobile, starts with 6-9)
     *   "917978628122"    → "+917978628122"   (12-digit with country code, no +)
     *   "+917978628122"   → "+917978628122"   (already E.164)
     *   "07978628122"     → "+917978628122"   (0-prefixed UK-style, treat as IN)
     */
    private function normalizePhone(string $phone): string
    {
        // Strip spaces, dashes, parentheses
        $clean = preg_replace('/[\s\-().]+/', '', $phone);

        // Already E.164
        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        // Leading 0 (trunk prefix) — strip and treat as Indian
        if (str_starts_with($clean, '0')) {
            $clean = substr($clean, 1);
        }

        // 10-digit number starting with 6,7,8,9 → Indian mobile
        if (strlen($clean) === 10 && preg_match('/^[6-9]/', $clean)) {
            return '+91' . $clean;
        }

        // 12-digit number starting with 91 (India) → prepend +
        if (strlen($clean) === 12 && str_starts_with($clean, '91')) {
            return '+' . $clean;
        }

        // Unknown format — prefix '+' and hope for the best
        return '+' . $clean;
    }
}

