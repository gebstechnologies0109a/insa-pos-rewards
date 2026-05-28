@extends('layouts.backoffice')

@section('content')
<div>
    <h1 class="text-2xl font-bold mb-6">POS Settings</h1>

    <div class="max-w-4xl">

        <div id="saveMessage" class="hidden mb-4 p-3 rounded text-sm"></div>

        <form id="settingsForm" onsubmit="saveSettings(event)">

            <!-- DIY BIZ REWARDS -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-1">DIY Biz Rewards Engine</h2>
                <p class="text-gray-500 text-sm mb-6">Control how rewards are computed for each member sale.</p>

                <div class="space-y-5">

                    <!-- Rewards ON/OFF -->
                    <div class="flex items-center justify-between border-b pb-4">
                        <div>
                            <label class="font-medium text-gray-800">Enable Rewards Engine</label>
                            <p class="text-xs text-gray-400 mt-0.5">Turn the entire rewards system on or off</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox"
                                   id="rewards_enabled"
                                   name="rewards_enabled"
                                   class="sr-only peer"
                                   {{ $rewards['rewards_enabled']['value'] === '1' ? 'checked' : '' }}>
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-700" id="toggleLabel">{{ $rewards['rewards_enabled']['value'] === '1' ? 'ON' : 'OFF' }}</span>
                        </label>
                    </div>

                    <!-- Reward Mode -->
                    <div class="flex items-center justify-between border-b pb-4">
                        <div>
                            <label class="font-medium text-gray-800">Reward Mode</label>
                            <p class="text-xs text-gray-400 mt-0.5">Rebate credits wallet cash; Points credits loyalty points</p>
                        </div>
                        <select id="reward_mode" name="reward_mode"
                                class="p-2 border rounded text-lg font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="rebate" {{ $rewards['reward_mode']['value'] === 'rebate' ? 'selected' : '' }}>Rebate (Cash Wallet)</option>
                            <option value="points" {{ $rewards['reward_mode']['value'] === 'points' ? 'selected' : '' }}>Loyalty Points</option>
                        </select>
                    </div>

                    <!-- Reward Value -->
                    <div class="flex items-center justify-between border-b pb-4">
                        <div>
                            <label class="font-medium text-gray-800">Reward Value</label>
                            <p class="text-xs text-gray-400 mt-0.5">Amount earned per block (e.g. 0.50, 1.25, 2.00)</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 font-medium">&#8369;</span>
                            <input type="number" id="reward_value" name="reward_value"
                                   value="{{ $rewards['reward_value']['value'] }}"
                                   step="0.01" min="0"
                                   class="w-28 p-2 border rounded text-right text-lg font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- Block Amount -->
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-medium text-gray-800">Block Amount</label>
                            <p class="text-xs text-gray-400 mt-0.5">Spend threshold per reward block (default: &#8369;200)</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 font-medium">&#8369;</span>
                            <input type="number" id="reward_block_amount" name="reward_block_amount"
                                   value="{{ $rewards['reward_block_amount']['value'] }}"
                                   step="1" min="1"
                                   class="w-28 p-2 border rounded text-right text-lg font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- OVERRIDE RATES -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-1">Override Rates</h2>
                <p class="text-gray-500 text-sm mb-6">Percentage-based overrides credited to upline sponsors on each member sale.</p>

                <div class="space-y-5">
                    @foreach($overrides as $key => $setting)
                    <div class="flex items-center justify-between {{ !$loop->last ? 'border-b pb-4' : '' }}">
                        <div>
                            <label for="{{ $key }}" class="font-medium text-gray-800">{{ $setting['label'] }}</label>
                            <p class="text-xs text-gray-400 mt-0.5">Key: {{ $key }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" id="{{ $key }}" name="{{ $key }}"
                                   value="{{ $setting['value'] }}"
                                   step="0.01" min="0" max="100"
                                   class="w-24 p-2 border rounded text-right text-lg font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <span class="text-gray-500 font-medium">%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- PRINTER SETTINGS -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-1">Printer Settings</h2>
                <p class="text-gray-500 text-sm mb-6">Configure thermal receipt paper width and font mode. Syncs to Android POS on next pull.</p>

                <div class="space-y-5">
                    <div class="flex items-center justify-between border-b pb-4">
                        <div>
                            <label for="printer_paper_size" class="font-medium text-gray-800">Paper Size</label>
                            <p class="text-xs text-gray-400 mt-0.5">57mm (58mm thermal) or 87mm (80mm thermal)</p>
                        </div>
                        <select id="printer_paper_size" name="printer_paper_size"
                                class="p-2 border rounded text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="57mm" {{ ($printer['printer_paper_size']['value'] ?? '57mm') === '57mm' ? 'selected' : '' }}>57mm (32 cols / 384 dots)</option>
                            <option value="87mm" {{ ($printer['printer_paper_size']['value'] ?? '') === '87mm' ? 'selected' : '' }}>87mm (48 cols / 576 dots)</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="printer_font_mode" class="font-medium text-gray-800">Font Mode</label>
                            <p class="text-xs text-gray-400 mt-0.5">Fine print uses smaller font for more characters per line</p>
                        </div>
                        <select id="printer_font_mode" name="printer_font_mode"
                                class="p-2 border rounded text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="paper_size" {{ ($printer['printer_font_mode']['value'] ?? 'paper_size') === 'paper_size' ? 'selected' : '' }}>Paper Size (standard font)</option>
                            <option value="fine_print" {{ ($printer['printer_font_mode']['value'] ?? '') === 'fine_print' ? 'selected' : '' }}>Fine Print (condensed font)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- HOW IT WORKS -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-1">How Rewards Work</h2>
                <p class="text-gray-500 text-sm mb-4">When a member completes a purchase:</p>
                <ul class="text-sm text-gray-700 space-y-2">
                    <li><span class="font-medium">Block Reward</span> — <code class="bg-gray-100 px-1 rounded">floor(total / block_amount) &times; reward_value</code></li>
                    <li><span class="font-medium">Example</span> — Total &#8369;788, Block &#8369;200, Value &#8369;0.50 = <code class="bg-gray-100 px-1 rounded">3 blocks &times; &#8369;0.50 = &#8369;1.50</code></li>
                    <li><span class="font-medium">Rebate Mode</span> — Credits the computed amount to the member's cash wallet.</li>
                    <li><span class="font-medium">Points Mode</span> — Credits the computed amount as loyalty points.</li>
                    <li><span class="font-medium">Overrides</span> — Percentage of sale total credited to upline sponsors (levels 2-4).</li>
                </ul>
            </div>

            @if(auth()->user()->canManageSettings())
            <div class="flex justify-end">
                <button type="submit" id="saveBtn"
                        class="px-8 py-3 bg-blue-700 text-white font-semibold rounded hover:bg-blue-800 transition text-lg">
                    Save All Settings
                </button>
            </div>
            @else
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                You have read-only access to settings. Contact an admin or owner to make changes.
            </div>
            @endif
        </form>
    </div>
</div>
</div>

<script>
document.getElementById('rewards_enabled').addEventListener('change', function() {
    document.getElementById('toggleLabel').textContent = this.checked ? 'ON' : 'OFF';
});

function saveSettings(e) {
    e.preventDefault();

    const btn = document.getElementById('saveBtn');
    const msg = document.getElementById('saveMessage');

    const settings = [];

    settings.push({ key: 'rewards_enabled', value: document.getElementById('rewards_enabled').checked ? '1' : '0' });
    settings.push({ key: 'reward_mode', value: document.getElementById('reward_mode').value });
    settings.push({ key: 'reward_value', value: document.getElementById('reward_value').value });
    settings.push({ key: 'reward_block_amount', value: document.getElementById('reward_block_amount').value });

    document.querySelectorAll('#settingsForm input[type="number"][name^="rewards_override"]').forEach(input => {
        settings.push({ key: input.name, value: input.value });
    });

    settings.push({ key: 'printer_paper_size', value: document.getElementById('printer_paper_size').value });
    settings.push({ key: 'printer_font_mode', value: document.getElementById('printer_font_mode').value });

    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch('{{ route("pos.settings.update") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ settings }),
    })
    .then(res => res.json())
    .then(data => {
        msg.className = 'mb-4 p-3 rounded text-sm bg-green-100 text-green-800';
        msg.textContent = data.message || 'Settings saved.';
        msg.classList.remove('hidden');
        setTimeout(() => msg.classList.add('hidden'), 3000);
    })
    .catch(() => {
        msg.className = 'mb-4 p-3 rounded text-sm bg-red-100 text-red-800';
        msg.textContent = 'Failed to save settings. Please try again.';
        msg.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Save All Settings';
    });
}
</script>
@endsection
