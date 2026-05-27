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

        $publicBase = rtrim(
            (string) config('maya_biller.public_base_url', config('app.url')),
            '/'
        );

        $endpointUrls = [
            'validate' => $publicBase.'/api/maya-biller/v1/validate',
            'post' => $publicBase.'/api/maya-biller/v1/post',
            'inquire' => $publicBase.'/api/maya-biller/v1/inquire',
            'fee' => $publicBase.'/api/maya-biller/v1/fee',
        ];

        $endpoints = [
            ['method' => 'POST', 'url' => $endpointUrls['validate'], 'label' => 'Step 1 — Validate Bills Payment (Maya → Partner)'],
            ['method' => 'POST', 'url' => $endpointUrls['post'], 'label' => 'Step 2 — Post Bills Payment (Maya → Partner)'],
            ['method' => 'POST', 'url' => $endpointUrls['inquire'], 'label' => 'Inquire Transaction'],
            ['method' => 'POST', 'url' => $endpointUrls['fee'], 'label' => 'Get Fee (optional)'],
        ];

        $validateUrl = $endpointUrls['validate'];

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

        $onboardingSteps = [
            ['id' => 'review_docs', 'phase' => 'Create & Develop', 'label' => 'Review integration guide + API reference'],
            ['id' => 'endpoints_live', 'phase' => 'Create & Develop', 'label' => 'Deploy four inbound HTTPS endpoints'],
            ['id' => 'local_mock', 'phase' => 'Create & Develop', 'label' => 'Pass local mock tests (Postman + PHPUnit)'],
            ['id' => 'submit_form', 'phase' => 'Create & Develop', 'label' => 'Submit integration form to Maya RM'],
            ['id' => 'gpg_keys', 'phase' => 'Sandbox', 'label' => 'Exchange GPG keys; load sandbox secrets'],
            ['id' => 'sandbox_postman', 'phase' => 'Sandbox', 'label' => 'Complete Maya sandbox Postman sign-off'],
            ['id' => 'uat', 'phase' => 'UAT', 'label' => 'UAT with Maya RM'],
            ['id' => 'go_live', 'phase' => 'Go-live', 'label' => 'MAYA_BILLER_ENABLED=true on production'],
        ];

        return view('epayplus.integrations.maya', [
            'enabled' => $enabled,
            'environment' => config('maya_biller.environment'),
            'publicBase' => $publicBase,
            'endpoints' => $endpoints,
            'endpointUrls' => $endpointUrls,
            'validateUrl' => $validateUrl,
            'onboardingSteps' => $onboardingSteps,
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
