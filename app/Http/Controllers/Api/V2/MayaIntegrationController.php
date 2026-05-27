<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Maya\MayaIntegrationConfigService;
use Illuminate\Http\JsonResponse;

class MayaIntegrationController extends Controller
{
    public function __construct(
        private readonly MayaIntegrationConfigService $config
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->config->toApiPayload(),
        ]);
    }
}
