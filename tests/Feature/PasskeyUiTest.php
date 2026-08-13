<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasskeyUiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_login_page_displays_passkey_entry_point(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Sign In with Passkey');
        $response->assertSee('autocomplete="email webauthn"', false);
    }

    public function test_profile_page_displays_passkey_management_for_authenticated_users(): void
    {
        $user = User::make([
            'name' => 'Passkey User',
            'username' => 'passkey-user',
            'email' => 'passkey@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->actingAs($user)->get(route('account.profile'));

        $response->assertOk();
        $response->assertSee('Passkeys');
        $response->assertSee('Add Passkey');
    }

    public function test_passkey_registration_options_prefer_platform_authenticators(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/user/passkeys/options');

        $response->assertOk();
        $response->assertJsonPath('options.authenticatorSelection.authenticatorAttachment', 'platform');
        $response->assertJsonPath('options.authenticatorSelection.userVerification', 'preferred');
        $response->assertJsonPath('options.authenticatorSelection.residentKey', 'preferred');
    }

    public function test_passkey_login_options_require_identifier_for_registered_passkeys(): void
    {
        $user = User::factory()->create([
            'name' => 'Passkey User',
            'username' => 'passkey-user',
            'email' => 'passkey@example.com',
        ]);

        $user->passkeys()->create([
            'name' => 'Work Laptop',
            'credential_id' => 'AQID',
            'credential' => [],
        ]);

        $response = $this->getJson('/passkeys/login/options?identifier=passkey@example.com');

        $response->assertOk();
        $response->assertJsonPath('options.userVerification', 'preferred');
        $response->assertJsonCount(1, 'options.allowCredentials');
        $response->assertJsonPath('options.allowCredentials.0.type', 'public-key');
    }
}