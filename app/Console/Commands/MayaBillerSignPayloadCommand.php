<?php

namespace App\Console\Commands;

use App\Services\MayaBiller\MayaBillerSignatureGenerator;
use Illuminate\Console\Command;

class MayaBillerSignPayloadCommand extends Command
{
    protected $signature = 'maya-biller:sign-payload
                            {json : Raw JSON body (quote in PowerShell/bash)}
                            {--secret= : Override MAYA_BILLER_SECRET_KEY}';

    protected $description = 'Compute paymaya-signature (Base64 SHA256) for manual curl/Postman testing';

    public function handle(MayaBillerSignatureGenerator $generator): int
    {
        $rawBody = $this->argument('json');
        $secret = $this->option('secret') ?? config('maya_biller.secret_key');

        if (! is_string($secret) || $secret === '') {
            $this->error('Set MAYA_BILLER_SECRET_KEY in .env or pass --secret=');

            return self::FAILURE;
        }

        $signature = $generator->forBody($rawBody, $secret);

        $this->line($signature);
        $this->newLine();
        $this->comment('Use header: paymaya-signature: '.$signature);
        $this->comment('Also send: Request-Reference-No: <unique-per-request>');

        return self::SUCCESS;
    }
}
