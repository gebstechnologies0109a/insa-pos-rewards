<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
class MayaBillerTestLocalCommand extends Command
{
    protected $signature = 'maya-biller:test-local
                            {--filter= : PHPUnit filter (default: MayaBiller)}';

    protected $description = 'Run Maya Biller smoke tests (in-memory HTTP via PHPUnit)';

    public function handle(): int
    {
        $filter = $this->option('filter') ?: 'MayaBiller';

        $this->info('Running Maya Biller test suite (filter: '.$filter.')...');
        $this->newLine();

        $exitCode = $this->call('test', [
            '--filter' => $filter,
        ]);

        if ($exitCode === 0) {
            $this->info('All Maya Biller smoke tests passed.');
        } else {
            $this->error('Some Maya Biller tests failed (exit '.$exitCode.').');
        }

        $this->newLine();
        $this->comment('For live HTTP against Laragon, import postman/ePayPlus-Maya-Biller-Local-Mock.json');
        $this->comment('See docs/MAYA_BILLER_TESTING.md');

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
