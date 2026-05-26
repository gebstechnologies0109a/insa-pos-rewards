<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'store_name',
        'contact_number',
        'store_address',
        'invoice_header',
        'invoice_footer',
        'tax_id',
    ];

    protected $casts = [
        'branch_id' => 'integer',
    ];

    public static function forBranch(?int $branchId): self
    {
        return static::firstOrCreate(
            ['branch_id' => $branchId],
            [
                'store_name'      => '',
                'contact_number'  => '',
                'store_address'   => '',
                'invoice_header'  => '',
                'invoice_footer'  => 'Thank you for your purchase!',
                'tax_id'          => '',
            ]
        );
    }
}
