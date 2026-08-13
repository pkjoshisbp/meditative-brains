<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    public function sendOtp(string $phone, string $code): bool
    {
        if (! config('services.whatsapp.enabled')) {
            return false;
        }

        $phoneNumberId = trim((string) config('services.whatsapp.phone_number_id'));
        $accessToken = trim((string) config('services.whatsapp.access_token'));
        $template = trim((string) config('services.whatsapp.template'));

        if ($phoneNumberId === '' || $accessToken === '' || $template === '') {
            Log::warning('[WhatsApp] OTP delivery is enabled but credentials or template are missing.');

            return false;
        }

        $recipient = $this->normalizePhone($phone);
        $version = trim((string) config('services.whatsapp.graph_version', 'v25.0'));
        $language = trim((string) config('services.whatsapp.template_language', 'en_US'));
        $bodyParameterName = trim((string) config('services.whatsapp.template_body_parameter_name', 'code'));
        $bodyParameterNames = array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            explode(',', (string) config('services.whatsapp.template_body_parameter_names', ''))
        )));
        $loginContext = trim((string) config('services.whatsapp.template_login_context', 'Login'));
        $bodyParameters = [[
            'type' => 'text',
            'text' => $code,
        ]];

        if ($bodyParameterName !== '') {
            $bodyParameters[0]['parameter_name'] = $bodyParameterName;
        }

        if ($loginContext !== '') {
            $bodyParameters[] = [
                'type' => 'text',
                'text' => $loginContext,
            ];
        }

        foreach ($bodyParameters as $index => $parameter) {
            if (isset($bodyParameterNames[$index]) && $bodyParameterNames[$index] !== '') {
                $bodyParameters[$index]['parameter_name'] = $bodyParameterNames[$index];
            }
        }

        $components = [[
            'type' => 'body',
            'parameters' => $bodyParameters,
        ]];

        if (config('services.whatsapp.template_has_copy_code_button', true)) {
            $buttonSubType = trim((string) config('services.whatsapp.template_button_sub_type', 'copy_code'));
            $buttonParameter = $buttonSubType === 'copy_code'
                ? [
                    'type' => 'coupon_code',
                    'coupon_code' => $code,
                ]
                : [
                    'type' => 'text',
                    'text' => $code,
                ];

            $components[] = [
                'type' => 'button',
                'sub_type' => $buttonSubType,
                'index' => '0',
                'parameters' => [$buttonParameter],
            ];
        }

        $bodyTexts = array_map(
            static fn (array $parameter): string => (string) ($parameter['text'] ?? ''),
            $bodyParameters
        );
        $buttonParameterTexts = array_map(
            static fn (array $component): array => array_map(
                static fn (array $parameter): string => (string) ($parameter['text'] ?? $parameter['coupon_code'] ?? ''),
                (array) ($component['parameters'] ?? [])
            ),
            array_values(array_filter(
                $components,
                static fn (array $component): bool => ($component['type'] ?? null) === 'button'
            ))
        );

        Log::info('[WhatsApp] Sending OTP template payload', [
            'recipient' => $this->maskPhone($recipient),
            'template' => $template,
            'language' => $language,
            'body_parameter_names' => array_map(
                static fn (array $parameter): ?string => $parameter['parameter_name'] ?? null,
                $bodyParameters
            ),
            'body_parameter_lengths' => array_map('mb_strlen', $bodyTexts),
            'button_sub_type' => $components[1]['sub_type'] ?? null,
            'button_parameter_lengths' => array_map(
                static fn (array $texts): array => array_map('mb_strlen', $texts),
                $buttonParameterTexts
            ),
        ]);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('services.whatsapp.timeout', 10))
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($recipient, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $language],
                        'components' => $components,
                    ],
                ]);

            if ($response->successful()) {
                Log::info('[WhatsApp] OTP template accepted by Meta', [
                    'recipient' => $this->maskPhone($recipient),
                    'message_id' => $response->json('messages.0.id'),
                ]);

                return true;
            }

            Log::error('[WhatsApp] Meta rejected OTP template', [
                'recipient' => $this->maskPhone($recipient),
                'status' => $response->status(),
                'error_code' => $response->json('error.code'),
                'error_subcode' => $response->json('error.error_subcode'),
                'error_message' => $response->json('error.message'),
                'error_details' => $response->json('error.error_data.details'),
                'fbtrace_id' => $response->json('error.fbtrace_id'),
                'template' => $template,
                'language' => $language,
                'component_types' => array_column($components, 'type'),
                'body_parameter_name' => $bodyParameterName !== '' ? $bodyParameterName : null,
                'body_parameter_names' => $bodyParameterNames,
                'body_parameter_count' => count($bodyParameters),
                'button_sub_type' => $components[1]['sub_type'] ?? null,
                'button_enabled' => (bool) config('services.whatsapp.template_has_copy_code_button', true),
            ]);
        } catch (Throwable $exception) {
            Log::error('[WhatsApp] OTP request failed', [
                'recipient' => $this->maskPhone($recipient),
                'error' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    private function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]+/', '', trim($phone));

        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        $clean = ltrim($clean, '0');

        if (strlen($clean) === 10 && preg_match('/^[6-9]/', $clean)) {
            return '+91'.$clean;
        }

        return '+'.$clean;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return str_repeat('*', max(strlen($digits) - 4, 2)).substr($digits, -4);
    }

}
