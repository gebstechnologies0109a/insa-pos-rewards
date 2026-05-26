<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\PosShift;
use App\Services\POS\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $service,
    ) {}

    public function current(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $shift = $this->service->getActiveShift($user);

        return response()->json([
            'success' => true,
            'shift'   => $shift,
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'opening_cash' => 'required|numeric|min:0',
        ]);

        try {
            $shift = $this->service->openShift(
                $user,
                (float) $request->opening_cash,
                $request,
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'shift'   => $shift,
        ], 201);
    }

    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'closing_cash' => 'required|numeric|min:0',
        ]);

        $shift = PosShift::where('user_id', auth()->id())
            ->where('status', 'open')
            ->first();

        if (! $shift) {
            return response()->json([
                'success' => false,
                'message' => 'No active shift found.',
            ], 404);
        }

        try {
            $closed = $this->service->closeShift($shift, (float) $request->closing_cash, $request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'shift'   => $closed,
        ]);
    }
}
