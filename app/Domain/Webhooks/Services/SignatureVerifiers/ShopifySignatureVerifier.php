<?php

namespace App\Domain\Webhooks\Services\SignatureVerifiers;

use App\Domain\Webhooks\Contracts\SignatureVerifier;
use Illuminate\Http\Request;

class ShopifySignatureVerifier implements SignatureVerifier
{
    public function verify(Request $request, string $payload): bool
    {
        $secret = config('hookrelay.signatures.shopify.secret');
        $signatureHeader = $request->header('X-Shopify-Hmac-Sha256');

        if (! is_string($secret) || $secret === '' || ! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expectedSignature, $signatureHeader);
    }
}
