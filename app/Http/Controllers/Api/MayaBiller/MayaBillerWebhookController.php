<?php

namespace App\Http\Controllers\Api\MayaBiller;

use App\Http\Controllers\Controller;
use App\Http\Requests\MayaBiller\GetFeeRequest;
use App\Http\Requests\MayaBiller\InquireTransactionRequest;
use App\Http\Requests\MayaBiller\PostBillsPaymentRequest;
use App\Http\Requests\MayaBiller\ValidateBillsPaymentRequest;
use App\Services\MayaBiller\MayaBillerFeeService;
use App\Services\MayaBiller\MayaBillerPostPaymentService;
use App\Services\MayaBiller\MayaBillerTransactionService;
use App\Services\MayaBiller\MayaBillerValidatePaymentService;
use App\Services\MayaBiller\MayaBillerValidateProofService;
use App\Support\MayaBiller\MayaBillerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MayaBillerWebhookController extends Controller
{
    public function __construct(
        private readonly MayaBillerTransactionService $transactionService,
        private readonly MayaBillerValidatePaymentService $validatePaymentService,
        private readonly MayaBillerValidateProofService $validateProofService,
        private readonly MayaBillerPostPaymentService $postPaymentService,
        private readonly MayaBillerFeeService $feeService
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
            $rrn = $this->requestReferenceNo($request);
            $this->validateProofService->remember($rrn, [
                'billerCode' => $request->billerCode(),
                'accountNumber' => $request->accountNumber(),
                'amount' => $request->amount(),
            ]);

            $fees = $this->feeService->compute(
                $request->billerCode(),
                $request->amount()
            );

            return MayaBillerResponse::success($fees);
        }

        return MayaBillerResponse::error(
            $result['code'],
            $result['message'] ?? 'Validation failed.'
        );
    }

    /**
     * Step 2: Post Bills Payment (Maya → Partner) — customer debited; persist and queue posting.
     */
    public function postPayment(PostBillsPaymentRequest $request): JsonResponse
    {
        if (config('maya_biller.maintenance')) {
            return MayaBillerResponse::maintenance();
        }

        if (! config('maya_biller.enabled')) {
            return MayaBillerResponse::error(
                'ACQ018',
                'The biller cannot accept payments right now. Please try again later.'
            );
        }

        $rrn = $this->requestReferenceNo($request);

        try {
            $result = $this->postPaymentService->accept($rrn, $request);

            return MayaBillerResponse::accepted($result['status']);
        } catch (\InvalidArgumentException $e) {
            [$code, $message] = array_pad(explode(':', $e->getMessage(), 2), 2, 'Validation failed.');

            if (in_array($code, ['2559', '2596'], true)) {
                return MayaBillerResponse::validationError($code, $message);
            }

            return MayaBillerResponse::validationError('2596', $message);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'ACQ018:')) {
                return MayaBillerResponse::validationError(
                    'ACQ018',
                    trim(substr($e->getMessage(), 7)) ?: 'Prior validate required.'
                );
            }

            throw $e;
        }
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
            return MayaBillerResponse::error(
                'ACQ018',
                'The biller cannot accept payments right now. Please try again later.'
            );
        }

        $fees = $this->feeService->compute(
            $request->billerCode(),
            (float) $request->input('amount')
        );

        return MayaBillerResponse::success($fees);
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
}
