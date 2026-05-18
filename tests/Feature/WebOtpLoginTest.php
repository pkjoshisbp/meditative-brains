<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\TrustedLoginDevice;
use App\Models\User;
use App\Services\OtpLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_email_otp_login_and_trust_browser(): void
    {
        $user = User::create([
            'name' => 'OTP User',
            'username' => 'otp-user',
            'email' => 'otp@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $requestOtp = $this->post(route('login.otp.request'), [
            'identifier' => 'otp@example.com',
            'trust_device' => '1',
        ]);

        $requestOtp->assertRedirect(route('login.otp.challenge'));
        $this->assertDatabaseHas('otp_codes', [
            'identifier' => 'otp@example.com',
            'used' => false,
        ]);

        $code = OtpCode::where('identifier', 'otp@example.com')->latest()->value('code');

        $verifyOtp = $this->post(route('login.otp.verify'), [
            'code' => $code,
        ]);

        $verifyOtp->assertRedirect(route('account.dashboard'));
        $verifyOtp->assertCookie(OtpLoginService::TRUSTED_DEVICE_COOKIE);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('trusted_login_devices', 1);
    }

    public function test_phone_login_sends_same_code_to_phone_and_email_when_both_exist(): void
    {
        User::create([
            'name' => 'OTP User',
            'username' => 'otp-user',
            'email' => 'otp@example.com',
            'mobile' => '+15551234567',
            'password' => Hash::make('secret123'),
        ]);

        $this->post(route('login.otp.request'), [
            'identifier' => '+15551234567',
        ])->assertRedirect(route('login.otp.challenge'));

        $codes = OtpCode::query()
            ->whereIn('identifier', ['+15551234567', 'otp@example.com'])
            ->pluck('code', 'identifier');

        $this->assertCount(2, $codes);
        $this->assertSame($codes['+15551234567'], $codes['otp@example.com']);
    }

    public function test_trusted_browser_bypasses_second_otp_request(): void
    {
        $user = User::create([
            'name' => 'OTP User',
            'username' => 'otp-user',
            'email' => 'otp@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post(route('login.otp.request'), [
            'identifier' => 'otp@example.com',
            'trust_device' => '1',
        ]);

        $code = OtpCode::where('identifier', 'otp@example.com')->latest()->value('code');

        $verifyOtp = $this->post(route('login.otp.verify'), [
            'code' => $code,
        ]);

        $cookie = collect($verifyOtp->headers->getCookies())
            ->first(fn ($item) => $item->getName() === OtpLoginService::TRUSTED_DEVICE_COOKIE);

        $this->assertNotNull($cookie);

        $this->post(route('logout'));

        $otpCount = OtpCode::count();

        $bypass = $this
            ->withCookie(OtpLoginService::TRUSTED_DEVICE_COOKIE, $cookie->getValue())
            ->post(route('login.otp.request'), [
                'identifier' => 'otp@example.com',
                'trust_device' => '1',
            ]);

        $bypass->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($otpCount, OtpCode::count());
        $this->assertSame(1, TrustedLoginDevice::count());
    }
}