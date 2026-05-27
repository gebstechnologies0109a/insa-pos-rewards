<?php

namespace Database\Seeders;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'GEBS'],
            ['status' => Company::STATUS_ACTIVE],
        );

        $branch = Branch::firstOrCreate(
            ['name' => 'Main Branch'],
            ['address' => 'Default location', 'company_id' => $company->id],
        );

        $accounts = [
            ['email' => 'owner@insapos.com',    'name' => 'Owner',    'role' => 'owner'],
            ['email' => 'admin@insapos.com',    'name' => 'Admin',    'role' => 'admin'],
            ['email' => 'manager@insapos.com',  'name' => 'Manager',  'role' => 'manager'],
            ['email' => 'cashier@insapos.com',  'name' => 'Cashier',  'role' => 'cashier'],
            ['email' => 'stockman@insapos.com', 'name' => 'Stockman', 'role' => 'stockman'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'      => $account['name'],
                    'password'  => Hash::make('password'),
                    'role'      => $account['role'],
                    'branch_id' => $branch->id,
                ],
            );
        }
    }
}
