<?php

namespace Tests\Feature\EPayPlus;

use Database\Seeders\EPayPlusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EPayPlusSeeder::class);
    }

    public function test_login_with_mobile_number_and_pin(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'mobile_number' => '09171234567',
            'pin' => '1234',
            'device_id' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('account.id', 'EPDEMO001')
            ->assertJsonStructure(['token', 'account' => ['mobileNumber', 'balance']]);
    }

    public function test_login_accepts_plus_63_format(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'mobile_number' => '+63 917 123 4567',
            'pin' => '1234',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_login_rejects_invalid_mobile(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'mobile_number' => '12345',
            'pin' => '1234',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['mobile_number']);
    }

    public function test_login_rejects_wrong_pin(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'mobile_number' => '09171234567',
            'pin' => '9999',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_deprecated_account_id_login_still_works(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'account_id' => 'EPDEMO001',
            'pin' => '1234',
        ]);

        $response->assertOk()->assertJsonPath('account.id', 'EPDEMO001');
    }
}
