<?php

namespace App\Jobs\MayaBiller;

use App\Models\EPayPlus\MayaBillerTransaction;
use App\Services\MayaBiller\MayaBillerTransactionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMayaBillerPostingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $mayaBillerTransactionId
    ) {}

    public function uniqueId(): string
    {
        return 'maya-biller-post-'.$this->mayaBillerTransactionId;
    }

    public function handle(MayaBillerTransactionService $transactionService): void
    {
        $txn = MayaBillerTransaction::query()->find($this->mayaBillerTransactionId);

        if (! $txn) {
            return;
        }

        $transactionService->processPosting($txn);
    }
}
