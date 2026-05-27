<?php

namespace App\Http\Middleware;

use App\Services\MayaBiller\MayaBillerSignatureVerifier;
use App\Support\MayaBiller\MayaBillerResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MayaBillerSignatureMiddleware
{
    public function __construct(
        private readonly MayaBillerSignatureVerifier $signatureVerifier
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestReference = $request->header('Request-Reference-No');

        if (! $requestReference) {
            return MayaBillerResponse::error(
                'ACQ018',
                'Request-Reference-No header is required.'
            );
        }

        $request->attributes->set('maya_request_reference_no', $requestReference);

        if (config('maya_biller.skip_signature')) {
            return $next($request);
        }

        $signature = $request->header('paymaya-signature');
        $rawBody = $request->getContent();
        $secret = (string) config('maya_biller.secret_key');

        if (! $this->signatureVerifier->verify($rawBody, $signature, $secret)) {
            return MayaBillerResponse::error(
                'ACQ018',
                'Maya signature mismatch.'
            );
        }

        return $next($request);
    }
}
