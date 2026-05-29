<?php

namespace App\Services\POS;

use App\Models\POS\Branch;
use App\Models\POS\PosSetting;

class PosSettingsService
{
    /** @var array<string, array{label: string, default: string, group: string, type: string}> */
    protected array $defaults = [
        'rewards_enabled'        => ['label' => 'Rewards Enabled',          'default' => '1',      'group' => 'rewards', 'type' => 'boolean'],
        'reward_mode'            => ['label' => 'Reward Mode',              'default' => 'rebate', 'group' => 'rewards', 'type' => 'select'],
        'reward_value'           => ['label' => 'Reward Value',             'default' => '0.50',   'group' => 'rewards', 'type' => 'decimal'],
        'reward_block_amount'    => ['label' => 'Block Amount',             'default' => '200',    'group' => 'rewards', 'type' => 'decimal'],
        'rewards_override_l2'    => ['label' => 'Override Rate (Level 2)',  'default' => '1',      'group' => 'overrides', 'type' => 'percent'],
        'rewards_override_l3'    => ['label' => 'Override Rate (Level 3)',  'default' => '1',      'group' => 'overrides', 'type' => 'percent'],
        'rewards_override_l4'    => ['label' => 'Override Rate (Level 4)',  'default' => '1',      'group' => 'overrides', 'type' => 'percent'],
        'receipt_header'         => ['label' => 'Receipt Header',           'default' => '',       'group' => 'receipt', 'type' => 'text'],
        'receipt_footer'         => ['label' => 'Receipt Footer',           'default' => 'Thank you!', 'group' => 'receipt', 'type' => 'text'],
        'vat_rate'               => ['label' => 'VAT Rate (%)',             'default' => '12',     'group' => 'receipt', 'type' => 'decimal'],
        'vat_inclusive'          => ['label' => 'Prices VAT Inclusive',     'default' => '1',      'group' => 'receipt', 'type' => 'boolean'],
        'printer_paper_size'     => ['label' => 'Paper Size',               'default' => '57mm',   'group' => 'printer', 'type' => 'select'],
        'printer_font_mode'      => ['label' => 'Font Mode',                'default' => 'paper_size', 'group' => 'printer', 'type' => 'select'],
        'customer_display.enabled'       => ['label' => 'Customer Display Enabled', 'default' => '1',      'group' => 'customer_display', 'type' => 'boolean'],
        'customer_display.photo'         => ['label' => 'Customer Display Photo',   'default' => '',       'group' => 'customer_display', 'type' => 'text'],
        'customer_display.video'         => ['label' => 'Customer Display Video',   'default' => '',       'group' => 'customer_display', 'type' => 'text'],
        'customer_display.orientation'   => ['label' => 'Display Orientation',      'default' => 'auto',   'group' => 'customer_display', 'type' => 'select'],
        'customer_display.rotation_mode' => ['label' => 'Media Rotation Mode',      'default' => 'mix',    'group' => 'customer_display', 'type' => 'select'],
        'customer_display.show_cart'     => ['label' => 'Show Cart on Display',     'default' => '1',      'group' => 'customer_display', 'type' => 'boolean'],
    ];

    public function get(string $key): string
    {
        $default = $this->defaults[$key]['default'] ?? '';

        return PosSetting::getValue($key, $default);
    }

    public function getFloat(string $key): float
    {
        return (float) $this->get($key);
    }

    public function getBool(string $key): bool
    {
        return in_array($this->get($key), ['1', 'true', 'yes'], true);
    }

    public function set(string $key, string $value): void
    {
        $meta = $this->defaults[$key] ?? ['label' => $key, 'group' => 'general', 'type' => 'string'];

        PosSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'label' => $meta['label'] ?? $key,
                'group' => $meta['group'] ?? 'general',
            ],
        );
    }

    /**
     * @return array<string, array{key: string, label: string, value: string, group: string, type: string}>
     */
    public function all(?string $group = null): array
    {
        $settings = [];

        foreach ($this->defaults as $key => $meta) {
            if ($group && $meta['group'] !== $group) {
                continue;
            }

            $settings[$key] = [
                'key'   => $key,
                'label' => $meta['label'],
                'value' => PosSetting::getValue($key, $meta['default']),
                'group' => $meta['group'],
                'type'  => $meta['type'],
            ];
        }

        return $settings;
    }

    /**
     * Flat key => value map for native offline sync (branch/company + receipt + rewards).
     *
     * @return array<string, string|int|float|bool>
     */
    public function syncMapForBranch(int $branchId): array
    {
        $branch = Branch::with('company')->find($branchId);
        $flat = [];

        foreach ($this->defaults as $key => $meta) {
            $flat[$key] = PosSetting::getValue($key, $meta['default']);
        }

        $flat['branch_id'] = $branchId;
        $flat['branch_name'] = $branch?->name ?? '';
        $flat['company_name'] = $branch?->company?->name ?? config('app.name', 'INSA POS');
        $flat['branch_address'] = $branch?->address ?? '';

        return $flat;
    }
}
