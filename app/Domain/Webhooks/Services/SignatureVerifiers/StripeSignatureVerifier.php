<?php

namespace App\Domain\Webhooks\Services\SignatureVerifiers;

use App\Domain\Webhooks\Contracts\SignatureVerifier;
use Illuminate\Http\Request;

class StripeSignatureVerifier implements SignatureVerifier
{
    public function verify(Request $request, string $payload): bool
    {
        $secret = config('hookrelay.signatures.stripe.secret');
        $signatureHeader = $request->header('Stripe-Signature');

        if (! is_string($secret) || $secret === '' || ! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        $parts = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $parts['t'] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! is_string($timestamp) || $timestamp === '' || $signatures === []) {
            return false;
        }

        if (! $this->isWithinTolerance($timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{t?: string, v1?: list<string>}
     */
    private function parseSignatureHeader(string $signatureHeader): array
    {
        $parts = ['v1' => []];

        foreach (explode(',', $signatureHeader) as $component) {
            [$key, $value] = array_map('trim', explode('=', $component, 2) + [null, null]);

            if ($key === null || $value === null || $value === '') {
                continue;
            }

            if ($key === 't') {
                $parts['t'] = $value;
            }

            if ($key === 'v1') {
                $parts['v1'][] = $value;
            }
        }

        return $parts;
    }

    private function isWithinTolerance(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = (int) config('hookrelay.signatures.stripe.tolerance_seconds', 300);

        return abs(now()->timestamp - (int) $timestamp) <= $tolerance;
    }
}
