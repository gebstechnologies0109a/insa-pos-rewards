<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_records_mutating_backoffice_post(): void
    {
        $manager = User::factory()->create([
            'role'      => 'manager',
            'branch_id' => 1,
        ]);
        $this->actingAs($manager);

        $this->post(route('backoffice.inventory.adjustment.store'), [
            'branch_id'  => 1,
            'product_id' => 1,
            'direction'  => 'in',
            'qty'        => 1,
            'reason'     => 'Test audit',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'module'  => 'backoffice',
        ]);
    }

    public function test_audit_log_model_record_helper(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'branch_id' => 1]);

        $log = AuditLog::record($user->id, 'test', 'action.test', 'test.route', 'POST');

        $this->assertEquals('test', $log->module);
        $this->assertEquals('action.test', $log->action);
    }
}
