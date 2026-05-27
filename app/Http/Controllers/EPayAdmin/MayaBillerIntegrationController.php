<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Enums\MayaBillerState;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\EPaySetting;
use App\Models\EPayPlus\MayaBillerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MayaBillerIntegrationController extends Controller
{
    public function index(Request $request): View
    {
        $enabled = config('maya_biller.enabled')
            || filter_var(EPaySetting::getValue('maya_biller_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);

        $baseUrl = config('app.url');
        $validateUrl = rtrim($baseUrl, '/').'/api/maya-biller/v1/validate';

        $endpoints = [
            'POST '.$validateUrl => 'Step 1 — Validate Bills Payment (Maya → Partner)',
            'POST '.rtrim($baseUrl, '/').'/api/maya-biller/v1/post' => 'Step 2 — Post Bills Payment (Maya → Partner)',
            'POST '.rtrim($baseUrl, '/').'/api/maya-biller/v1/inquire' => 'Step 3 support — Inquire Transaction',
            'POST '.rtrim($baseUrl, '/').'/api/maya-biller/v1/fee' => 'Get Fee (optional)',
        ];

        $stateFilter = $request->query('state');
        $transactionsQuery = MayaBillerTransaction::query()->latest();

        if ($stateFilter && in_array($stateFilter, array_column(MayaBillerState::cases(), 'value'), true)) {
            $transactionsQuery->where('state', $stateFilter);
        }

        $transactions = $transactionsQuery->limit(50)->get();

        $stateLegend = [
            MayaBillerState::New->value => 'Validate succeeded on Maya side; partner validate does not persist.',
            MayaBillerState::Processing->value => 'Post received; partner queued internal posting.',
            MayaBillerState::Authorized->value => 'Maya debited customer wallet; post accepted.',
            MayaBillerState::Posting->value => 'Partner saved txn; background job posting bill.',
            MayaBillerState::Failed->value => 'Wallet debit or post rejected (4xx/5xx on post).',
            MayaBillerState::Fulfilled->value => 'Step 3 callback result.code = 0000 sent to Maya.',
            MayaBillerState::PostingFailed->value => 'Callback result ≠ 0000; Maya refunds customer.',
        ];

        $defaultFees = config('maya_biller.fees.default', []);
        $feeOverrides = config('maya_biller.fees.biller_overrides', []);

        return view('epayplus.integrations.maya', [
            'enabled' => $enabled,
            'environment' => config('maya_biller.environment'),
            'endpoints' => $endpoints,
            'validateUrl' => $validateUrl,
            'transactions' => $transactions,
            'stateFilter' => $stateFilter,
            'stateLegend' => $stateLegend,
            'stateOptions' => MayaBillerState::cases(),
            'feeContractNote' => config('maya_biller.fees.contract_note'),
            'defaultFees' => $defaultFees,
            'feeOverrides' => $feeOverrides,
            'settlementUrl' => config('maya_biller.settlement_portal_url'),
            'testingGuideUrl' => route('epayplus.integrations.maya.testing'),
        ]);
    }

    public function testingGuide(): Response
    {
        $path = base_path('docs/MAYA_BILLER_TESTING.md');

        abort_unless(is_readable($path), 404);

        $html = Str::markdown(file_get_contents($path) ?: '');

        return response()
            ->view('epayplus.integrations.maya-testing', [
                'title' => 'Maya Biller Testing',
                'html' => $html,
            ]);
    }
}
