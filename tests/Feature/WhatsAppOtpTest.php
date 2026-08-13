<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppOtpTest extends TestCase
{
    public function test_whatsapp_service_sends_the_authentication_template(): void
    {
        config()->set('services.whatsapp', [
            'enabled' => true,
            'graph_version' => 'v25.0',
            'phone_number_id' => '123456789',
            'access_token' => 'test-access-token',
            'app_secret' => '',
            'verify_token' => 'test-verify-token',
            'template' => 'mental_fitness_single_param',
            'template_language' => 'en',
            'template_body_parameter_name' => '',
            'template_body_parameter_names' => '',
            'template_login_context' => '',
            'template_has_copy_code_button' => true,
            'template_button_sub_type' => 'url',
            'timeout' => 10,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test']],
            ], 200),
        ]);

        $this->assertTrue(app(WhatsAppService::class)->sendOtp('9876543210', '123456'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v25.0/123456789/messages'
                && $request['to'] === '919876543210'
                && $request['template']['name'] === 'mental_fitness_single_param'
                && $request['template']['language']['code'] === 'en'
                && ! isset($request['template']['components'][0]['parameters'][0]['parameter_name'])
                && $request['template']['components'][0]['parameters'][0]['text'] === '123456'
                && count($request['template']['components'][0]['parameters']) === 1
                && $request['template']['components'][1]['type'] === 'button'
                && $request['template']['components'][1]['sub_type'] === 'url'
                && $request['template']['components'][1]['index'] === '0'
                && $request['template']['components'][1]['parameters'][0]['type'] === 'text'
                && $request['template']['components'][1]['parameters'][0]['text'] === '123456'
                && count($request['template']['components']) === 2;
        });
    }

    public function test_whatsapp_service_can_send_named_library_template_parameters(): void
    {
        config()->set('services.whatsapp', [
            'enabled' => true,
            'graph_version' => 'v25.0',
            'phone_number_id' => '123456789',
            'access_token' => 'test-access-token',
            'app_secret' => '',
            'verify_token' => 'test-verify-token',
            'template' => 'verify_otp_usecase',
            'template_language' => 'en_US',
            'template_body_parameter_name' => '',
            'template_body_parameter_names' => 'code,text',
            'template_login_context' => 'Login',
            'template_has_copy_code_button' => false,
            'template_button_sub_type' => 'url',
            'timeout' => 10,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test']],
            ], 200),
        ]);

        $this->assertTrue(app(WhatsAppService::class)->sendOtp('9876543210', '123456'));

        Http::assertSent(function ($request): bool {
            return $request['template']['name'] === 'verify_otp_usecase'
                && $request['template']['components'][0]['parameters'][0]['parameter_name'] === 'code'
                && $request['template']['components'][0]['parameters'][0]['text'] === '123456'
                && $request['template']['components'][0]['parameters'][1]['parameter_name'] === 'text'
                && $request['template']['components'][0]['parameters'][1]['text'] === 'Login'
                && count($request['template']['components']) === 1;
        });
    }

    public function test_meta_can_verify_the_whatsapp_webhook(): void
    {
        config()->set('services.whatsapp.verify_token', 'test-verify-token');

        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=123456')
            ->assertOk()
            ->assertSeeText('123456');
    }

    public function test_whatsapp_webhook_rejects_an_invalid_signature(): void
    {
        config()->set('services.whatsapp.app_secret', 'test-app-secret');

        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);

        $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            ],
            $payload
        )->assertForbidden();
    }
}
