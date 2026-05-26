<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\EPaySetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = EPaySetting::pluck('value', 'key')->toArray();
        return view('epayplus.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'default_commission_rate' => 'nullable|numeric|min:0|max:100',
            'maintenance_mode'       => 'nullable|boolean',
            'maintenance_message'    => 'nullable|string|max:1000',
            'sms_topup_approved'     => 'nullable|string|max:500',
            'sms_topup_rejected'     => 'nullable|string|max:500',
            'sms_low_balance'        => 'nullable|string|max:500',
            'low_balance_threshold'  => 'nullable|numeric|min:0',
            'notify_admin_email'     => 'nullable|email|max:255',
            'auto_approve_topups'    => 'nullable|boolean',
            'max_topup_amount'       => 'nullable|numeric|min:0',
        ]);

        $settingsToSave = [
            'default_commission_rate' => $request->input('default_commission_rate', '5'),
            'maintenance_mode'        => $request->boolean('maintenance_mode') ? '1' : '0',
            'maintenance_message'     => $request->input('maintenance_message', ''),
            'sms_topup_approved'      => $request->input('sms_topup_approved', 'Your top-up of {amount} has been approved. New balance: {balance}'),
            'sms_topup_rejected'      => $request->input('sms_topup_rejected', 'Your top-up request of {amount} has been rejected. Reason: {reason}'),
            'sms_low_balance'         => $request->input('sms_low_balance', 'Your balance is low ({balance}). Please top-up to continue transacting.'),
            'low_balance_threshold'   => $request->input('low_balance_threshold', '100'),
            'notify_admin_email'      => $request->input('notify_admin_email', ''),
            'auto_approve_topups'     => $request->boolean('auto_approve_topups') ? '1' : '0',
            'max_topup_amount'        => $request->input('max_topup_amount', '50000'),
        ];

        foreach ($settingsToSave as $key => $value) {
            EPaySetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        AuditLog::record(auth()->id(), 'settings_updated', null, 'System settings updated');

        return back()->with('success', 'Settings updated successfully.');
    }
}
