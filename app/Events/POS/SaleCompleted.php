<?php

namespace App\Events\POS;

use App\Models\POS\PosSale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCompleted
{
    use Dispatchable, SerializesModels;

    public PosSale $sale;

    public function __construct(PosSale $sale)
    {
        $this->sale = $sale;
    }
}
