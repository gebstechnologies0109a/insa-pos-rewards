@extends('layouts.epayplus')

@section('title', 'Settings')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">System Settings</h4>
    <small class="text-muted">Configure ePayPlus system parameters</small>
</div>

<form method="POST" action="{{ route('epayplus.settings.update') }}">
    @csrf

    <div class="row g-4">
        {{-- General Settings --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-gear"></i> General</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Default Commission Rate (%)</label>
                        <input type="number" name="default_commission_rate" class="form-control"
                               value="{{ $settings['default_commission_rate'] ?? 5 }}" step="0.01" min="0" max="100">
                        <small class="text-muted">Applied to new products without explicit commission</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Max Top-up Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="max_topup_amount" class="form-control"
                                   value="{{ $settings['max_topup_amount'] ?? 50000 }}" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Low Balance Threshold</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="low_balance_threshold" class="form-control"
                                   value="{{ $settings['low_balance_threshold'] ?? 100 }}" min="0">
                        </div>
                        <small class="text-muted">Send alert when retailer balance drops below this</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Admin Notification Email</label>
                        <input type="email" name="notify_admin_email" class="form-control"
                               value="{{ $settings['notify_admin_email'] ?? '' }}" placeholder="admin@example.com">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="auto_approve_topups" value="1" class="form-check-input"
                               {{ ($settings['auto_approve_topups'] ?? '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-medium">Auto-approve top-ups</label>
                        <small class="d-block text-muted">Automatically approve incoming top-up requests</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Maintenance & SMS --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-tools"></i> Maintenance</h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="maintenance_mode" value="1" class="form-check-input"
                               {{ ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-medium text-danger">Maintenance Mode</label>
                        <small class="d-block text-muted">Block all retailer transactions while enabled</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Maintenance Message</label>
                        <textarea name="maintenance_message" class="form-control" rows="2">{{ $settings['maintenance_message'] ?? 'System is under maintenance. Please try again later.' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-chat-dots"></i> SMS Templates</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Top-up Approved</label>
                        <textarea name="sms_topup_approved" class="form-control" rows="2">{{ $settings['sms_topup_approved'] ?? 'Your top-up of {amount} has been approved. New balance: {balance}' }}</textarea>
                        <small class="text-muted">Variables: {amount}, {balance}, {retailer}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Top-up Rejected</label>
                        <textarea name="sms_topup_rejected" class="form-control" rows="2">{{ $settings['sms_topup_rejected'] ?? 'Your top-up request of {amount} has been rejected. Reason: {reason}' }}</textarea>
                        <small class="text-muted">Variables: {amount}, {reason}, {retailer}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Low Balance Alert</label>
                        <textarea name="sms_low_balance" class="form-control" rows="2">{{ $settings['sms_low_balance'] ?? 'Your balance is low ({balance}). Please top-up to continue transacting.' }}</textarea>
                        <small class="text-muted">Variables: {balance}, {retailer}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Save Settings</button>
    </div>
</form>
@endsection
