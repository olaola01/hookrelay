<?php

namespace App\Domain\Webhooks\Contracts;

use Illuminate\Http\Request;

interface SignatureVerifier
{
    public function verify(Request $request, string $payload): bool;
}
