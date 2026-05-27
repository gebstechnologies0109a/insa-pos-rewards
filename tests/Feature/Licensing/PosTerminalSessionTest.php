<?php

namespace Tests\Feature\Licensing;

use App\Models\POS\Branch;
use App\Models\POS\PosLicense;
use App\Models\POS\PosTerminalSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosTerminalSessionTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected Branch $branch;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticatePosApi();
        $this->branch = Branch::find(1);
        $this->cashier = $this->posUser;

        PosLicense::create([
            'branch_id' => $this->branch->id,
            'pos_slots' => 2,
            'active' => true,
            'status' => 'active',
        ]);
    }

    public function test_register_creates_session_within_slot_limit(): void
    {
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/pos/terminal/register', [
                'device_fingerprint' => 'device-a',
                'branch_id' => $this->branch->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['session_id', 'slots']);

        $this->assertDatabaseHas('pos_terminal_sessions', [
            'branch_id' => $this->branch->id,
            'device_fingerprint' => 'device-a',
            'is_active' => true,
        ]);
    }

    public function test_blocks_third_session_when_slots_exhausted(): void
    {
        PosTerminalSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'device_fingerprint' => 'seat-1',
            'started_at' => now(),
            'is_active' => true,
        ]);
        PosTerminalSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'device_fingerprint' => 'seat-2',
            'started_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/pos/terminal/register', [
                'device_fingerprint' => 'seat-3',
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'license_limit_reached')
            ->assertJsonFragment(['message' => 'License limit reached. All cashier seats for this branch are in use. Close another session or contact your administrator.']);
    }

    public function test_resume_same_fingerprint_does_not_consume_extra_slot(): void
    {
        $session = PosTerminalSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'device_fingerprint' => 'device-resume',
            'started_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/pos/terminal/register', [
                'device_fingerprint' => 'device-resume',
                'session_id' => $session->id,
                'branch_id' => $this->branch->id,
            ])
            ->assertOk()
            ->assertJsonPath('resumed', true);

        $this->assertEquals(1, PosTerminalSession::where('branch_id', $this->branch->id)->active()->count());
    }

    public function test_cashier_page_shows_license_limit_message_in_api(): void
    {
        PosTerminalSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'device_fingerprint' => 'x1',
            'started_at' => now(),
            'is_active' => true,
        ]);
        PosTerminalSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'device_fingerprint' => 'x2',
            'started_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier)
            ->get(route('pos.cashier'))
            ->assertOk()
            ->assertSee('License limit reached', false);

        $this->actingAs($this->cashier)
            ->postJson('/api/pos/terminal/register', [
                'device_fingerprint' => 'blocked-device',
                'branch_id' => $this->branch->id,
            ])
            ->assertForbidden();
    }
}
