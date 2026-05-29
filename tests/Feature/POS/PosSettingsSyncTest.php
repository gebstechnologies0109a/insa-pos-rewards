<?php

namespace Tests\Feature\POS;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Services\POS\PosSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosSettingsSyncTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    public function test_sync_map_includes_branch_and_receipt_keys(): void
    {
        $this->authenticatePosApi();
        $branch = Branch::find(1);
        Company::query()->updateOrCreate(
            ['id' => $branch->company_id ?? 1],
            ['name' => 'GEBS Corp', 'status' => 'active'],
        );
        $branch->update(['name' => 'Main Store', 'company_id' => 1]);

        $map = app(PosSettingsService::class)->syncMapForBranch($branch->id);

        $this->assertEquals('Main Store', $map['branch_name']);
        $this->assertEquals('GEBS Corp', $map['company_name']);
        $this->assertArrayHasKey('receipt_header', $map);
        $this->assertArrayHasKey('receipt_footer', $map);
        $this->assertArrayHasKey('vat_rate', $map);
        $this->assertArrayHasKey('printer_paper_size', $map);
        $this->assertArrayHasKey('printer_font_mode', $map);
        $this->assertEquals('57mm', $map['printer_paper_size']);
        $this->assertEquals('paper_size', $map['printer_font_mode']);
    }

    public function test_sync_map_uses_saved_printer_settings(): void
    {
        $this->authenticatePosApi();
        $branch = Branch::find(1);

        app(PosSettingsService::class)->set('printer_paper_size', '87mm');
        app(PosSettingsService::class)->set('printer_font_mode', 'fine_print');

        $map = app(PosSettingsService::class)->syncMapForBranch($branch->id);

        $this->assertEquals('87mm', $map['printer_paper_size']);
        $this->assertEquals('fine_print', $map['printer_font_mode']);
    }

    public function test_settings_update_validates_printer_values(): void
    {
        $this->authenticatePosApi();
        $user = auth()->user();
        $user->role = 'owner';
        $user->save();

        $this->postJson('/pos/settings', [
            'settings' => [
                ['key' => 'printer_paper_size', 'value' => '87mm'],
                ['key' => 'printer_font_mode', 'value' => 'fine_print'],
            ],
        ])->assertOk();

        $this->assertEquals('87mm', app(PosSettingsService::class)->get('printer_paper_size'));
        $this->assertEquals('fine_print', app(PosSettingsService::class)->get('printer_font_mode'));
    }

    public function test_sync_pull_returns_settings_object(): void
    {
        $this->authenticatePosApi();

        $response = $this->postJson('/api/pos/sync/pull', ['branch_id' => 1]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['settings' => ['branch_name', 'company_name', 'vat_rate', 'printer_paper_size', 'printer_font_mode']]);
    }
}
