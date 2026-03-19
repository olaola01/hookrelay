<?php

namespace App\Domain\Webhooks\Services\SignatureVerifiers;

use App\Domain\Webhooks\Contracts\SignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GitHubSignatureVerifier implements SignatureVerifier
{
    public function verify(Request $request, string $payload): bool
    {
        $secret = config('hookrelay.signatures.github.secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $signature256 = $request->header('X-Hub-Signature-256');

        if (is_string($signature256) && $signature256 !== '') {
            return $this->matchesSignature($signature256, $payload, $secret, 'sha256');
        }

        $signature = $request->header('X-Hub-Signature');

        if (is_string($signature) && $signature !== '') {
            return $this->matchesSignature($signature, $payload, $secret, 'sha1');
        }

        return false;
    }

    private function matchesSignature(string $header, string $payload, string $secret, string $algorithm): bool
    {
        $prefix = $algorithm.'=';

        if (! Str::startsWith($header, $prefix)) {
            return false;
        }

        $providedSignature = Str::after($header, $prefix);
        $expectedSignature = hash_hmac($algorithm, $payload, $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
