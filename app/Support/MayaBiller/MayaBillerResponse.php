<?php

namespace App\Support\MayaBiller;

use Illuminate\Http\JsonResponse;

class MayaBillerResponse
{
    public const ALLOWED_CODES = ['0000', '2559', '2596', 'ACQ018'];

    /**
     * @param  array{convenienceFee: float, serviceFee: float, totalFee: float}|null  $fees
     */
    public static function success(?array $fees = null): JsonResponse
    {
        return self::build('0000', null, $fees);
    }

    public static function accepted(int $status = 202): JsonResponse
    {
        return response()->json(['result' => ['code' => '0000']], $status);
    }

    public static function alreadyAccepted(int $status = 202): JsonResponse
    {
        return response()->json([
            'result' => ['code' => '0000'],
            'resultMessage' => 'ALREADY_ACCEPTED',
        ], $status);
    }

    /**
     * Post Bills Payment acceptance (Maya → Partner).
     */
    public static function postAccepted(
        string $requestReferenceNo,
        string $status,
        int $httpStatus = 202,
        bool $queued = true
    ): JsonResponse {
        return response()->json([
            'resultCode' => '0000',
            'resultMessage' => 'ACCEPTED',
            'requestReferenceNo' => $requestReferenceNo,
            'status' => $status,
            'queued' => $queued,
        ], $httpStatus);
    }

    public static function validationError(string $code, string $message): JsonResponse
    {
        if (! in_array($code, self::ALLOWED_CODES, true)) {
            throw new \InvalidArgumentException("Maya Biller result code [{$code}] is not allowed.");
        }

        return response()->json([
            'result' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 400);
    }

    public static function maintenance(): JsonResponse
    {
        return response()->json([
            'result' => [
                'code' => 'ACQ018',
                'message' => 'Biller is under maintenance. Please try again later.',
            ],
        ], 503);
    }

    public static function error(string $code, string $message): JsonResponse
    {
        if (! in_array($code, self::ALLOWED_CODES, true)) {
            throw new \InvalidArgumentException("Maya Biller result code [{$code}] is not allowed.");
        }

        return self::build($code, $message);
    }

    /**
     * @param  array{convenienceFee: float, serviceFee: float, totalFee: float}|null  $fees
     */
    protected static function build(string $code, ?string $message = null, ?array $fees = null): JsonResponse
    {
        $result = ['code' => $code];

        if ($code !== '0000' && $message !== null && $message !== '') {
            $result['message'] = $message;
        }

        $payload = ['result' => $result];

        if ($code === '0000' && $fees !== null) {
            $payload['fees'] = [
                'convenienceFee' => round((float) $fees['convenienceFee'], 2),
                'serviceFee' => round((float) $fees['serviceFee'], 2),
                'totalFee' => round((float) $fees['totalFee'], 2),
            ];
        }

        return response()->json($payload);
    }
}
