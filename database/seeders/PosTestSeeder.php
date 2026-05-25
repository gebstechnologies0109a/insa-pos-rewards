<?php

namespace Database\Seeders;

use App\Models\POS\Customer;
use Illuminate\Database\Seeder;

class PosTestSeeder extends Seeder
{
    public function run(): void
    {
        Customer::create([
            'uuid'           => 'e7b8c4a2-1f3d-4a5b-9c6e-8d7f0a2b3c4d',
            'card_number'    => 'CARD000001',
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'phone'          => '+639171234567',
            'email'          => 'juan@example.com',
            'loyalty_points' => 150.00,
            'status'         => 'active',
        ]);

        Customer::create([
            'uuid'           => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'card_number'    => 'CARD000002',
            'first_name'     => 'Maria',
            'last_name'      => 'Santos',
            'phone'          => '+639281234567',
            'email'          => 'maria@example.com',
            'loyalty_points' => 320.50,
            'status'         => 'active',
        ]);

        Customer::create([
            'uuid'           => 'f0e9d8c7-b6a5-4321-fedc-ba0987654321',
            'card_number'    => 'CARD000003',
            'first_name'     => 'Jose',
            'last_name'      => 'Dela Cruz',
            'phone'          => '+639391234567',
            'email'          => 'jose@example.com',
            'loyalty_points' => 0,
            'status'         => 'active',
        ]);
    }
}
