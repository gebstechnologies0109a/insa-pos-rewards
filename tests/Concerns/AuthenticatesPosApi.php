<?php

namespace Tests\Concerns;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\Product;
use App\Models\User;

trait AuthenticatesPosApi
{
    protected User $posUser;

    protected function authenticatePosApi(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'GEBS'],
            ['status' => Company::STATUS_ACTIVE],
        );

        Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'company_id' => $company->id],
        );

        if (Product::count() === 0) {
            Product::create(['name' => 'Coke Mismo', 'sku' => 'COKE-001', 'price' => 25]);
            Product::create(['name' => 'Piattos', 'sku' => 'PIA-001', 'price' => 18]);
        }

        $this->posUser = User::factory()->create([
            'role'      => 'cashier',
            'branch_id' => 1,
        ]);

        $this->actingAs($this->posUser);
    }
}
