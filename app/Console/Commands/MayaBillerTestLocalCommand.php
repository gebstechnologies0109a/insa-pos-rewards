<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class MayaBillerTestLocalCommand extends Command
{
    protected $signature = 'maya-biller:test-local
                            {--filter= : Optional PHPUnit method/class filter}';

    protected $description = 'Run Maya Biller smoke tests (in-memory HTTP via PHPUnit)';

    public function handle(): int
    {
        $this->info('Running Maya Biller feature + unit tests...');
        $this->newLine();

        $command = [
            PHP_BINARY,
            base_path('vendor/bin/phpunit'),
            '--colors=always',
            'tests/Feature/MayaBiller',
            'tests/Unit/MayaBillerFeeServiceTest.php',
            'tests/Unit/MayaBillerResponseTest.php',
            'tests/Unit/MayaBillerSignatureVerifierTest.php',
        ];

        if ($filter = $this->option('filter')) {
            $command[] = '--filter='.$filter;
        }

        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $exitCode = $process->getExitCode() ?? 1;

        if ($exitCode === 0) {
            $this->newLine();
            $this->info('All Maya Biller smoke tests passed.');
        } else {
            $this->newLine();
            $this->error('Some Maya Biller tests failed (exit '.$exitCode.').');
        }

        $this->newLine();
        $this->comment('For live HTTP against Laragon, import postman/ePayPlus-Maya-Biller-Local-Mock.json');
        $this->comment('See docs/MAYA_BILLER_TESTING.md');

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
