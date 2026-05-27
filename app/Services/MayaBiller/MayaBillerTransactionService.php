<?php

namespace App\Services\MayaBiller;

use App\Enums\MayaBillerState;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Models\EPayPlus\Transaction;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MayaBillerTransactionService
{
    public function __construct(
        private readonly MayaBillerCallbackClient $callbackClient
    ) {}

    public function findByRequestReference(string $requestReferenceNo): ?MayaBillerTransaction
    {
        return MayaBillerTransaction::where('request_reference_no', $requestReferenceNo)->first();
    }

    /**
     * Background job: credit ledger, POSTING, then Step 3 callback.
     */
    public function processPosting(MayaBillerTransaction $txn): void
    {
        if ($txn->state->isTerminal()) {
            return;
        }

        if ($txn->state === MayaBillerState::Authorized) {
            $this->transition($txn, MayaBillerState::Posting);
            $txn = $txn->fresh();
        }

        if ($txn->state !== MayaBillerState::Posting) {
            return;
        }

        try {
            $this->completeInternalPosting($txn);
            $callback = $this->callbackClient->sendPostingCallback($txn, fulfilled: true);
            $this->applyCallbackResult($txn, $callback);
        } catch (\Throwable $e) {
            Log::error('Maya Biller internal posting failed', [
                'maya_biller_transaction_id' => $txn->id,
                'request_reference_no' => $txn->request_reference_no,
                'error' => $e->getMessage(),
            ]);

            $txn = $txn->fresh();
            if ($txn->state === MayaBillerState::Posting) {
                $callback = $this->callbackClient->sendPostingCallback($txn, fulfilled: false);
                $this->applyCallbackResult($txn, $callback);
            }
        }
    }

    public function completeInternalPosting(MayaBillerTransaction $txn): void
    {
        if ($txn->epayTransaction) {
            $txn->epayTransaction->markSuccess($txn->maya_transaction_id);
        }
    }

    public function applyCallbackResult(MayaBillerTransaction $txn, MayaCallbackResult $callback): MayaBillerTransaction
    {
        $nextState = $callback->fulfilled
            ? MayaBillerState::Fulfilled
            : MayaBillerState::PostingFailed;

        $txn->update([
            'callback_sent_at' => now(),
            'callback_response' => [
                'url' => $callback->callbackUrl,
                'resultCode' => $callback->resultCode,
                'status' => $callback->httpStatus,
                'body' => $callback->responseBody,
                'httpSuccessful' => $callback->httpSuccessful,
            ],
        ]);

        $txn = $txn->fresh();

        if ($txn->state === MayaBillerState::Posting) {
            $this->transition($txn, $nextState);
        }

        return $txn->fresh();
    }

    public function linkEpayTransaction(MayaBillerTransaction $txn, Transaction $epayTransaction): void
    {
        $txn->update(['epay_transaction_id' => $epayTransaction->id]);
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
