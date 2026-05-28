<?php

namespace Database\Seeders;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\Device;
use App\Models\POS\PosTerminalSession;
use Illuminate\Database\Seeder;

class CompanyBranchDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['name' => 'GEBS'],
            ['status' => Company::STATUS_ACTIVE],
        );

        $branch = Branch::updateOrCreate(
            ['name' => 'INSAPOS'],
            [
                'company_id' => $company->id,
                'address'    => 'INSA POS default branch',
            ],
        );

        Branch::query()
            ->whereNull('company_id')
            ->update(['company_id' => $company->id]);

        $fingerprints = PosTerminalSession::query()
            ->where(function ($q) use ($branch) {
                $q->where('branch_id', $branch->id)
                    ->orWhereNull('branch_id');
            })
            ->whereNotNull('device_fingerprint')
            ->where('device_fingerprint', '!=', '')
            ->distinct()
            ->pluck('device_fingerprint');

        $deviceIndex = 1;
        foreach ($fingerprints as $fingerprint) {
            $device = Device::updateOrCreate(
                ['device_fingerprint' => $fingerprint],
                [
                    'branch_id'   => $branch->id,
                    'device_name' => 'Device ' . $deviceIndex,
                    'status'      => Device::STATUS_ACTIVE,
                ],
            );

            PosTerminalSession::query()
                ->where('device_fingerprint', $fingerprint)
                ->where(function ($q) use ($branch) {
                    $q->where('branch_id', $branch->id)->orWhereNull('branch_id');
                })
                ->update([
                    'device_id'  => $device->id,
                    'branch_id'  => $branch->id,
                ]);

            $deviceIndex++;
        }

        $linkedCount = Device::where('branch_id', $branch->id)->count();
        $sessionsLinked = PosTerminalSession::where('branch_id', $branch->id)->whereNotNull('device_id')->count();

        $this->command?->info("CompanyBranchDeviceSeeder: company={$company->name}, branch={$branch->name}, devices={$linkedCount}, sessions_linked={$sessionsLinked}");
    }
}
