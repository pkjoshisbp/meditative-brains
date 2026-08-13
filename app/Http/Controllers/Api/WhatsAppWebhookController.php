<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = (string) $request->query('hub.verify_token', $request->query('hub_verify_token', ''));
        $challenge = (string) $request->query('hub.challenge', $request->query('hub_challenge', ''));
        $verifyToken = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[WhatsApp] Webhook verification rejected.');

        return response('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('[WhatsApp] Webhook signature rejected.');

            return response('Invalid signature', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                $value = (array) data_get($change, 'value', []);

                foreach ((array) data_get($value, 'statuses', []) as $status) {
                    Log::info('[WhatsApp] Message status update', [
                        'message_id' => data_get($status, 'id'),
                        'status' => data_get($status, 'status'),
                        'timestamp' => data_get($status, 'timestamp'),
                        'error_codes' => collect((array) data_get($status, 'errors', []))
                            ->pluck('code')
                            ->filter()
                            ->values()
                            ->all(),
                    ]);
                }

                foreach ((array) data_get($value, 'messages', []) as $message) {
                    Log::info('[WhatsApp] Inbound message received', [
                        'message_id' => data_get($message, 'id'),
                        'type' => data_get($message, 'type'),
                        'timestamp' => data_get($message, 'timestamp'),
                    ]);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function hasValidSignature(Request $request): bool
    {
        $appSecret = (string) config('services.whatsapp.app_secret');

        if ($appSecret === '') {
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }
}
