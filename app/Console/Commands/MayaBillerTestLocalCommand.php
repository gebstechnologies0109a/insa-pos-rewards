<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
class MayaBillerTestLocalCommand extends Command
{
    protected $signature = 'maya-biller:test-local
                            {--filter= : Optional PHPUnit method/class filter within Maya Biller tests}';

    protected $description = 'Run Maya Biller smoke tests (in-memory HTTP via PHPUnit)';

    public function handle(): int
    {
        $this->info('Running Maya Biller feature + unit tests...');
        $this->newLine();

        $params = [
            'tests/Feature/MayaBiller',
            'tests/Unit/MayaBillerFeeServiceTest.php',
            'tests/Unit/MayaBillerResponseTest.php',
            'tests/Unit/MayaBillerSignatureVerifierTest.php',
        ];

        if ($filter = $this->option('filter')) {
            $params['--filter'] = $filter;
        }

        $exitCode = $this->call('test', $params);

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
