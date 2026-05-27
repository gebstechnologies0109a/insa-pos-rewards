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
     * Stateless — no database writes; records validate proof in cache for Post.
     */
    public function validatePayment(ValidateBillsPaymentRequest $request): JsonResponse
    {
        if (! config('maya_biller.enabled')) {
            return MayaBillerResponse::error(
                'ACQ018',
                'The biller cannot accept payments right now. Please try again later.'
            );
        }

        $rrn = $this->requestReferenceNo($request);

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

        if ($result['code'] !== '0000') {
            return MayaBillerResponse::error(
                $result['code'],
                $result['message'] ?? 'Validation failed.'
            );
        }

        $fees = $this->feeService->compute($request->billerCode(), $request->amount());

        $this->validateProofService->remember($rrn, [
            'billerCode' => $request->billerCode(),
            'accountNumber' => $request->accountNumber(),
            'amount' => $request->amount(),
            'fees' => $fees,
        ]);

        return MayaBillerResponse::success($fees);
    }

    /**
     * Step 2: Post Bills Payment (Maya → Partner).
     * Respond immediately with 202 Accepted; background job posts and callbacks.
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
        } catch (\InvalidArgumentException $e) {
            $parts = explode(':', $e->getMessage(), 2);
            $code = $parts[0] ?? '2596';
            $message = $parts[1] ?? 'Validation failed.';

            if (in_array($code, ['2559', '2596'], true)) {
                return MayaBillerResponse::validationError($code, $message);
            }

            if ($code === 'ACQ018') {
                return MayaBillerResponse::validationError('ACQ018', $message);
            }

            return response()->json([
                'result' => ['code' => '4002', 'message' => $message],
                'requestReferenceNo' => $rrn,
            ], 409);
        } catch (\RuntimeException $e) {
            $message = str_replace('ACQ018:', '', $e->getMessage());

            return MayaBillerResponse::validationError(
                'ACQ018',
                trim($message) ?: 'Prior validate required.'
            );
        }

        $txn = $result['txn'];

        if ($result['idempotent']) {
            return response()->json([
                'resultCode' => '0000',
                'resultMessage' => 'ALREADY_ACCEPTED',
                'requestReferenceNo' => $txn->request_reference_no,
                'transactionId' => $txn->maya_transaction_id,
                'status' => $txn->state->value,
                'queued' => false,
            ], $result['status']);
        }

        return MayaBillerResponse::postAccepted(
            $txn->request_reference_no,
            $txn->state->value,
            $result['status']
        );
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
                'result' => [
                    'code' => '4040',
                    'message' => 'Transaction not found.',
                ],
                'requestReferenceNo' => $rrn,
            ], 404);
        }

        return response()->json([
            'result' => ['code' => '0000'],
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

    protected function disabledResponse(): JsonResponse
    {
        return response()->json([
            'resultCode' => '5030',
            'resultMessage' => 'Maya Biller integration is disabled.',
        ], 503);
    }
}
