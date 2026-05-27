<?php

namespace Tests\Unit;

use App\Enums\MayaBillerState;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Services\MayaBiller\MayaBillerTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MayaBillerStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private MayaBillerTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MayaBillerTransactionService::class);
    }

    public function test_happy_path_transitions(): void
    {
        $txn = $this->makeTxn(MayaBillerState::New);

        $this->service->transition($txn, MayaBillerState::Processing);
        $this->service->transition($txn->fresh(), MayaBillerState::Authorized);
        $this->service->transition($txn->fresh(), MayaBillerState::Posting);
        $this->service->transition($txn->fresh(), MayaBillerState::Fulfilled);

        $this->assertSame(MayaBillerState::Fulfilled, $txn->fresh()->state);
    }

    public function test_posting_failed_from_posting(): void
    {
        $txn = $this->makeTxn(MayaBillerState::Posting);

        $this->service->transition($txn, MayaBillerState::PostingFailed);

        $this->assertSame(MayaBillerState::PostingFailed, $txn->fresh()->state);
        $this->assertTrue($txn->fresh()->state->isTerminal());
    }

    public function test_failed_from_processing(): void
    {
        $txn = $this->makeTxn(MayaBillerState::Processing);

        $this->service->transition($txn, MayaBillerState::Failed);

        $this->assertSame(MayaBillerState::Failed, $txn->fresh()->state);
    }

    public function test_invalid_transition_throws(): void
    {
        $txn = $this->makeTxn(MayaBillerState::Fulfilled);

        $this->expectException(InvalidArgumentException::class);
        $this->service->transition($txn, MayaBillerState::Posting);
    }

    public function test_enum_matches_migration_values(): void
    {
        $expected = [
            'NEW',
            'PROCESSING',
            'AUTHORIZED',
            'POSTING',
            'FAILED',
            'FULFILLED',
            'POSTING_FAILED',
        ];

        $actual = array_map(fn (MayaBillerState $s) => $s->value, MayaBillerState::cases());

        $this->assertSame($expected, $actual);
    }

    protected function makeTxn(MayaBillerState $state): MayaBillerTransaction
    {
        return MayaBillerTransaction::create([
            'request_reference_no' => 'RRN-SM-'.uniqid(),
            'state' => $state,
            'biller_code' => 'MERALCO',
            'account_number' => '1234567890',
            'amount' => 100,
            'fee' => 0,
            'currency' => 'PHP',
        ]);
    }
}
