<?php

namespace Tests\Feature\POS;

use App\Models\POS\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'uuid'           => 'e7b8c4a2-1f3d-4a5b-9c6e-8d7f0a2b3c4d',
            'card_number'    => 'CARD000001',
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'phone'          => '+639171234567',
            'email'          => 'juan@example.com',
            'loyalty_points' => 150.00,
            'status'         => 'active',
        ]);
    }

    public function test_lookup_by_qr_uuid(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'qr',
            'value' => 'e7b8c4a2-1f3d-4a5b-9c6e-8d7f0a2b3c4d',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'uuid'      => 'e7b8c4a2-1f3d-4a5b-9c6e-8d7f0a2b3c4d',
                    'full_name' => 'Juan Dela Cruz',
                ],
            ]);
    }

    public function test_lookup_by_qr_card_prefix(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'qr',
            'value' => 'CARD:CARD000001',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.card_number', 'CARD000001');
    }

    public function test_lookup_by_qr_base64_encoded_uuid(): void
    {
        $encoded = base64_encode('e7b8c4a2-1f3d-4a5b-9c6e-8d7f0a2b3c4d');

        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'qr',
            'value' => $encoded,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.uuid', 'e7b8c4a2-1f3d-4a5b-9c6e-8d7f0a2b3c4d');
    }

    public function test_lookup_by_barcode(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'barcode',
            'value' => 'CARD000001',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'card_number' => 'CARD000001',
                    'full_name'   => 'Juan Dela Cruz',
                ],
            ]);
    }

    public function test_lookup_by_phone(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'phone',
            'value' => '+639171234567',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.phone', '+639171234567');
    }

    public function test_lookup_by_name_search(): void
    {
        Customer::create([
            'first_name' => 'Jose',
            'last_name'  => 'Dela Cruz',
            'phone'      => '+639391234567',
            'status'     => 'active',
        ]);

        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'search',
            'value' => 'Dela Cruz',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_lookup_returns_404_when_not_found(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'barcode',
            'value' => 'NONEXISTENT',
        ]);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Customer not found.',
            ]);
    }

    public function test_lookup_validates_required_fields(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'value']);
    }

    public function test_lookup_validates_type_enum(): void
    {
        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'invalid',
            'value' => 'test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_inactive_customer_not_returned(): void
    {
        $this->customer->update(['status' => 'inactive']);

        $response = $this->postJson('/api/pos/customer/lookup', [
            'type'  => 'barcode',
            'value' => 'CARD000001',
        ]);

        $response->assertNotFound();
    }
}
