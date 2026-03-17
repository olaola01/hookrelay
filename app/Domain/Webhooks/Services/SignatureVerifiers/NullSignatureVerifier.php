<?php

namespace App\Domain\Webhooks\Services\SignatureVerifiers;

use App\Domain\Webhooks\Contracts\SignatureVerifier;
use Illuminate\Http\Request;

class NullSignatureVerifier implements SignatureVerifier
{
    public function verify(Request $request, string $payload): bool
    {
        return true;
    }
}
