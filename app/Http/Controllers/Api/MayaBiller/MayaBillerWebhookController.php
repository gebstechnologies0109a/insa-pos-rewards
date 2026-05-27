<?php

namespace App\Http\Controllers\Api\MayaBiller;

use App\Enums\MayaBillerState;
use App\Http\Controllers\Controller;
use App\Http\Requests\MayaBiller\GetFeeRequest;
use App\Http\Requests\MayaBiller\InquireTransactionRequest;
use App\Http\Requests\MayaBiller\PostPaymentRequest;
use App\Http\Requests\MayaBiller\ValidateBillsPaymentRequest;
use App\Services\MayaBiller\MayaBillerTransactionService;
use App\Services\MayaBiller\MayaBillerValidatePaymentService;
use App\Support\MayaBiller\MayaBillerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MayaBillerWebhookController extends Controller
{
    public function __construct(
        private readonly MayaBillerTransactionService $transactionService,
        private readonly MayaBillerValidatePaymentService $validatePaymentService
    ) {}

    /**
     * Step 1: Validate Bills Payment (Maya → Partner).
     * Stateless — no database writes.
     */
    public function validatePayment(ValidateBillsPaymentRequest $request): JsonResponse
    {
        if (! config('maya_biller.enabled')) {
            return MayaBillerResponse::error(
                'ACQ018',
                'The biller cannot accept payments right now. Please try again later.'
            );
        }

        $result = $this->validatePaymentService->validate(
            billerCode: $request->billerCode(),
            accountNumber: $request->accountNumber(),
            amount: $request->amount(),
            mobileNo: $request->mobileNo(),
            billExpiry: $request->billExpiry(),
            referenceExpiry: $request->referenceExpiry(),
            referenceNo: $request->referenceNo(),
            billingData: $request->billingData()
        );

        if ($result['code'] === '0000') {
            return MayaBillerResponse::success();
        }

        return MayaBillerResponse::error(
            $result['code'],
            $result['message'] ?? 'Validation failed.'
        );
    }

    /**
     * Post Bills Payment (Maya → Partner) — customer debited; partner must post/credit.
     */
    public function postPayment(PostPaymentRequest $request): JsonResponse
    {
        if (! config('maya_biller.enabled')) {
            return $this->disabledResponse();
        }

        $rrn = $this->requestReferenceNo($request);

        try {
            $txn = $this->transactionService->recordPost($rrn, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'resultCode' => '4002',
                'resultMessage' => $e->getMessage(),
                'requestReferenceNo' => $rrn,
            ], 409);
        }

        if ($txn->state === MayaBillerState::Posting) {
            $this->transactionService->sendPostingCallback($txn, fulfilled: true);
            $txn = $txn->fresh();
        }

        return response()->json([
            'resultCode' => '0000',
            'resultMessage' => 'ACCEPTED',
            'requestReferenceNo' => $txn->request_reference_no,
            'transactionId' => $txn->maya_transaction_id,
            'status' => $txn->state->value,
        ]);
    }

    /**
     * Inquire Transaction status by Request Reference No.
     */
    public function inquireTransaction(InquireTransactionRequest $request): JsonResponse
    {
        $rrn = $request->requestReferenceNo();
        $txn = $this->transactionService->findByRequestReference($rrn);

        if (! $txn) {
            return response()->json([
                'resultCode' => '4040',
                'resultMessage' => 'Transaction not found.',
                'requestReferenceNo' => $rrn,
            ], 404);
        }

        return response()->json([
            'resultCode' => '0000',
            'resultMessage' => 'SUCCESS',
            'requestReferenceNo' => $txn->request_reference_no,
            'transactionId' => $txn->maya_transaction_id,
            'status' => $txn->state->value,
            'amount' => (float) $txn->amount,
            'fee' => (float) $txn->fee,
            'currency' => $txn->currency,
            'callbackSentAt' => $txn->callback_sent_at?->toIso8601String(),
        ]);
    }

    /**
     * Get Fee (optional inbound).
     */
    public function getFee(GetFeeRequest $request): JsonResponse
    {
        if (! config('maya_biller.enabled')) {
            return $this->disabledResponse();
        }

        $amount = (float) $request->input('amount');
        $fee = round($amount * 0.01, 2);

        return response()->json([
            'resultCode' => '0000',
            'resultMessage' => 'SUCCESS',
            'billerCode' => $request->billerCode(),
            'amount' => $amount,
            'fee' => $fee,
            'currency' => config('maya_biller.default_currency', 'PHP'),
        ]);
    }

    protected function requestReferenceNo(Request $request): string
    {
        return (string) (
            $request->attributes->get('maya_request_reference_no')
            ?? $request->header('Request-Reference-No')
            ?? $request->input('requestReferenceNo')
            ?? $request->input('request_reference_no')
        );
    }

    protected function disabledResponse(): JsonResponse
    {
        return response()->json([
            'resultCode' => '5030',
            'resultMessage' => 'Maya Biller integration is disabled.',
        ], 503);
    }
}
