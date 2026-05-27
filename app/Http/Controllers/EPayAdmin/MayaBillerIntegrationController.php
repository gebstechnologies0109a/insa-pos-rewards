<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\EPaySetting;
use App\Models\EPayPlus\MayaBillerTransaction;
use Illuminate\View\View;

class MayaBillerIntegrationController extends Controller
{
    public function index(): View
    {
        $enabled = config('maya_biller.enabled')
            || filter_var(EPaySetting::getValue('maya_biller_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);

        $baseUrl = config('app.url');
        $validateUrl = rtrim($baseUrl, '/').'/api/maya-biller/v1/validate';

        $endpoints = [
            'POST '.$validateUrl => 'Step 1 — Validate Bills Payment (Maya → Partner)',
            'POST '.rtrim($baseUrl, '/').'/api/maya-biller/v1/post' => 'Post Bills Payment',
            'POST '.rtrim($baseUrl, '/').'/api/maya-biller/v1/inquire' => 'Inquire Transaction',
            'POST '.rtrim($baseUrl, '/').'/api/maya-biller/v1/fee' => 'Get Fee (optional)',
        ];

        $transactions = MayaBillerTransaction::query()
            ->latest()
            ->limit(25)
            ->get();

        $defaultFees = config('maya_biller.fees.default', []);
        $feeOverrides = config('maya_biller.fees.biller_overrides', []);

        return view('epayplus.integrations.maya', [
            'enabled' => $enabled,
            'environment' => config('maya_biller.environment'),
            'endpoints' => $endpoints,
            'validateUrl' => $validateUrl,
            'transactions' => $transactions,
            'feeContractNote' => config('maya_biller.fees.contract_note'),
            'defaultFees' => $defaultFees,
            'feeOverrides' => $feeOverrides,
        ]);
    }
}
