<?php

namespace App\Services\MayaBiller;

use App\Enums\MayaBillerState;
use App\Http\Requests\MayaBiller\PostBillsPaymentRequest;
use App\Jobs\MayaBiller\ProcessMayaBillerPostingJob;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MayaBillerPostPaymentService
{
    public function __construct(
        private readonly MayaBillerValidatePaymentService $validatePaymentService,
        private readonly MayaBillerValidateProofService $validateProofService,
        private readonly MayaBillerTransactionService $transactionService
    ) {}

    /**
     * @return array{txn: MayaBillerTransaction, idempotent: bool, status: int}
     */
    public function accept(string $requestReferenceNo, PostBillsPaymentRequest $request): array
    {
        $existing = MayaBillerTransaction::where('request_reference_no', $requestReferenceNo)->first();

        if ($existing !== null && $existing->state === MayaBillerState::Failed) {
            throw new \InvalidArgumentException('4002:Transaction previously failed.');
        }

        if ($existing !== null && $this->isIdempotentReplay($existing)) {
            return [
                'txn' => $existing,
                'idempotent' => true,
                'status' => (int) config('maya_biller.post_accept_status', 202),
            ];
        }

        if (
            config('maya_biller.require_validate_proof', true)
            && ! $this->validateProofService->hasProof($requestReferenceNo)
        ) {
            throw new \RuntimeException('ACQ018:Prior validate required.');
        }

        $validation = $this->validatePaymentService->validate(
            billerCode: $request->billerCode(),
            accountNumber: $request->accountNumber(),
            amount: $request->amount(),
            mobileNo: $request->mobileNo(),
            billExpiry: $request->billExpiry(),
            referenceExpiry: $request->referenceExpiry(),
            referenceNo: $request->referenceNo(),
            billingData: $request->billingData()
        );

        if ($validation['code'] !== '0000') {
            throw new \InvalidArgumentException(
                $validation['code'].':'.($validation['message'] ?? 'Validation failed.')
            );
        }

        $txn = DB::transaction(function () use ($requestReferenceNo, $request) {
            $txn = MayaBillerTransaction::where('request_reference_no', $requestReferenceNo)
                ->lockForUpdate()
                ->first();

            if ($txn !== null && $this->isIdempotentReplay($txn)) {
                return $txn;
            }

            if ($txn === null) {
                $txn = new MayaBillerTransaction([
                    'request_reference_no' => $requestReferenceNo,
                    'state' => MayaBillerState::Processing,
                ]);
            } elseif ($txn->state === MayaBillerState::New) {
                $this->transactionService->transition($txn, MayaBillerState::Processing);
            }

            $txn->fill([
                'biller_code' => $request->billerCode(),
                'account_number' => $request->accountNumber(),
                'amount' => $request->amount(),
                'fee' => $request->fee(),
                'currency' => (string) ($request->input('currency') ?? config('maya_biller.default_currency', 'PHP')),
                'customer_name' => $request->customerName(),
                'customer_phone' => $request->mobileNo(),
                'callback_url' => $request->callbackUrl(),
                'maya_transaction_id' => $request->mayaTransactionId(),
                'raw_post_payload' => $request->payload(),
            ]);
            $txn->save();

            if ($txn->state === MayaBillerState::Processing) {
                $this->transactionService->transition($txn, MayaBillerState::Authorized);
            }

            if (! $txn->epay_transaction_id) {
                $epayTxn = $this->createEpayTransaction($txn, $requestReferenceNo);
                $txn->update(['epay_transaction_id' => $epayTxn->id]);
            }

            return $txn->fresh();
        });

        ProcessMayaBillerPostingJob::dispatch($txn->id);
        $this->validateProofService->forget($requestReferenceNo);

        return [
            'txn' => $txn,
            'idempotent' => false,
            'status' => (int) config('maya_biller.post_accept_status', 202),
        ];
    }

    protected function isIdempotentReplay(MayaBillerTransaction $txn): bool
    {
        return in_array($txn->state, [
            MayaBillerState::Authorized,
            MayaBillerState::Posting,
            MayaBillerState::Fulfilled,
            MayaBillerState::PostingFailed,
        ], true);
    }

    protected function createEpayTransaction(MayaBillerTransaction $txn, string $requestReferenceNo): Transaction
    {
        $retailer = $this->resolveSystemRetailer();
        $product = Product::query()
            ->active()
            ->ofType('BILLS')
            ->where('code', $txn->biller_code.'_PAY')
            ->first();

        return Transaction::create([
            'retailer_id' => $retailer->id,
            'product_id' => $product?->id,
            'type' => 'BILLS',
            'reference_number' => $this->generateReferenceNumber(),
            'provider_code' => $txn->biller_code,
            'product_code' => $product?->code,
            'product_name' => $product?->name ?? 'Maya Bill Payment',
            'target_number' => $txn->account_number,
            'amount' => $txn->amount,
            'fee' => $txn->fee,
            'commission' => $product?->commission ?? 0,
            'retailer_cost' => (float) $txn->amount + (float) $txn->fee,
            'status' => 'PROCESSING',
            'payment_method' => 'MAYA_BILLER',
            'remarks' => 'source:maya_biller|rrn:'.$requestReferenceNo,
            'external_ref' => $txn->maya_transaction_id,
            'balance_before' => $retailer->balance,
            'balance_after' => $retailer->balance,
        ]);
    }

    protected function resolveSystemRetailer(): Retailer
    {
        $accountId = (string) config('maya_biller.system_retailer_account_id', 'EPDEMO001');

        $retailer = Retailer::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->first();

        if ($retailer === null) {
            throw new \RuntimeException('Maya Biller system retailer is not configured.');
        }

        return $retailer;
    }

    protected function generateReferenceNumber(): string
    {
        return 'MB'.now()->format('ymdHis').strtoupper(Str::random(4));
    }
}
