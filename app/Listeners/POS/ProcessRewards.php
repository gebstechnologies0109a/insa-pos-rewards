<?php

namespace App\Listeners\POS;

use App\Events\POS\SaleCompleted;
use App\Services\Rewards\RewardsEngineService;

class ProcessRewards
{
    public function __construct(
        protected RewardsEngineService $engine,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $this->engine->processSale($event->sale);
    }
}
