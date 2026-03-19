<?php

namespace App\Domain\Webhooks\Services;

use App\Domain\Webhooks\Contracts\SignatureVerifier;
use App\Domain\Webhooks\Services\SignatureVerifiers\GitHubSignatureVerifier;
use App\Domain\Webhooks\Services\SignatureVerifiers\NullSignatureVerifier;
use App\Domain\Webhooks\Services\SignatureVerifiers\ShopifySignatureVerifier;
use App\Domain\Webhooks\Services\SignatureVerifiers\StripeSignatureVerifier;

class SignatureVerifierResolver
{
    public function __construct(
        private readonly GitHubSignatureVerifier $gitHubSignatureVerifier,
        private readonly ShopifySignatureVerifier $shopifySignatureVerifier,
        private readonly StripeSignatureVerifier $stripeSignatureVerifier,
        private readonly NullSignatureVerifier $nullSignatureVerifier,
    ) {
    }

    public function forSource(string $source): SignatureVerifier
    {
        return match ($source) {
            'github' => $this->gitHubSignatureVerifier,
            'shopify' => $this->shopifySignatureVerifier,
            'stripe' => $this->stripeSignatureVerifier,
            default => $this->nullSignatureVerifier,
        };
    }
}
