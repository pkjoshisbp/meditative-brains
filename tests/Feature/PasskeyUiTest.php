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
}