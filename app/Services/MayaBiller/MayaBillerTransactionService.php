<?php

namespace App\Services\MayaBiller;

use App\Enums\MayaBillerState;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Models\EPayPlus\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MayaBillerTransactionService
{
    public function __construct(
        private readonly MayaBillerCallbackClient $callbackClient
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordValidate(string $requestReferenceNo, array $payload): MayaBillerTransaction
    {
        $txn = MayaBillerTransaction::firstOrNew([
            'request_reference_no' => $requestReferenceNo,
        ]);

        if ($txn->exists && $txn->state !== MayaBillerState::New) {
            return $txn;
        }

        $txn->fill([
            'state' => MayaBillerState::Processing,
            'biller_code' => (string) ($payload['billerCode'] ?? $payload['biller_code'] ?? ''),
            'account_number' => (string) ($payload['accountNumber'] ?? $payload['account_number'] ?? ''),
            'amount' => (float) ($payload['amount'] ?? 0),
            'fee' => (float) ($payload['fee'] ?? 0),
            'currency' => (string) ($payload['currency'] ?? config('maya_biller.default_currency', 'PHP')),
            'customer_name' => $payload['customerName'] ?? $payload['customer_name'] ?? null,
            'customer_phone' => $payload['customerPhone'] ?? $payload['customer_phone'] ?? null,
            'raw_validate_payload' => $payload,
        ]);
        $txn->save();

        return $txn;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordPost(string $requestReferenceNo, array $payload): MayaBillerTransaction
    {
        return DB::transaction(function () use ($requestReferenceNo, $payload) {
            $txn = MayaBillerTransaction::where('request_reference_no', $requestReferenceNo)
                ->lockForUpdate()
                ->first();

            if (! $txn) {
                $txn = new MayaBillerTransaction([
                    'request_reference_no' => $requestReferenceNo,
                    'state' => MayaBillerState::New,
                    'biller_code' => (string) ($payload['billerCode'] ?? $payload['biller_code'] ?? ''),
                    'account_number' => (string) ($payload['accountNumber'] ?? $payload['account_number'] ?? ''),
                    'amount' => (float) ($payload['amount'] ?? 0),
                    'currency' => (string) ($payload['currency'] ?? config('maya_biller.default_currency', 'PHP')),
                ]);
                $txn->save();
            }

            if ($txn->state === MayaBillerState::New) {
                $this->transition($txn, MayaBillerState::Processing);
            }

            $this->transition($txn, MayaBillerState::Authorized);

            $txn->maya_transaction_id = (string) (
                $payload['transactionId']
                ?? $payload['mayaTransactionId']
                ?? $payload['maya_transaction_id']
                ?? $txn->maya_transaction_id
            );
            $txn->raw_post_payload = $payload;
            $txn->amount = (float) ($payload['amount'] ?? $txn->amount);
            $txn->fee = (float) ($payload['fee'] ?? $txn->fee);
            $txn->save();

            $this->transition($txn, MayaBillerState::Posting);
            $this->dispatchInternalBillPosting($txn);

            return $txn->fresh();
        });
    }

    public function findByRequestReference(string $requestReferenceNo): ?MayaBillerTransaction
    {
        return MayaBillerTransaction::where('request_reference_no', $requestReferenceNo)->first();
    }

    /**
     * Stub: link to epay_transactions / retailer bill payment when fully integrated.
     */
    protected function dispatchInternalBillPosting(MayaBillerTransaction $txn): void
    {
        // TODO: resolve retailer/system account, call existing bills payment pipeline.
        if ($txn->epay_transaction_id) {
            return;
        }
    }

    public function linkEpayTransaction(MayaBillerTransaction $txn, Transaction $epayTransaction): void
    {
        $txn->update(['epay_transaction_id' => $epayTransaction->id]);
    }

    /**
     * Send posting callback and update terminal state.
     *
     * @param  array<string, mixed>  $extra
     */
    public function sendPostingCallback(
        MayaBillerTransaction $txn,
        bool $fulfilled,
        array $extra = []
    ): MayaBillerTransaction {
        $resultCode = $fulfilled ? '0000' : '9999';
        $nextState = $fulfilled ? MayaBillerState::Fulfilled : MayaBillerState::PostingFailed;

        $payload = array_merge([
            'requestReferenceNo' => $txn->request_reference_no,
            'transactionId' => $txn->maya_transaction_id,
            'resultCode' => $resultCode,
            'resultMessage' => $fulfilled ? 'FULFILLED' : 'POSTING_FAILED',
        ], $extra);

        if (! config('maya_biller.enabled')) {
            $txn->update([
                'callback_response' => ['skipped' => true, 'reason' => 'integration_disabled'],
            ]);

            return $txn;
        }

        $response = $this->callbackClient->sendPostingCallback($payload);

        $txn->update([
            'callback_sent_at' => now(),
            'callback_response' => [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ],
        ]);

        if ($txn->state === MayaBillerState::Posting) {
            $this->transition($txn, $nextState);
        }

        return $txn->fresh();
    }

    public function transition(MayaBillerTransaction $txn, MayaBillerState $next): void
    {
        $current = $txn->state instanceof MayaBillerState
            ? $txn->state
            : MayaBillerState::from((string) $txn->state);

        if ($current === $next) {
            return;
        }

        if (! $current->canTransitionTo($next)) {
            throw new InvalidArgumentException(
                "Invalid Maya biller state transition: {$current->value} → {$next->value}"
            );
        }

        $txn->update(['state' => $next]);
    }

    public function markFailed(MayaBillerTransaction $txn): MayaBillerTransaction
    {
        if (! $txn->state->isTerminal()) {
            $this->transition($txn, MayaBillerState::Failed);
        }

        return $txn->fresh();
    }
}
