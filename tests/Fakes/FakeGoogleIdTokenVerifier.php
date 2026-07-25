<?php

namespace Tests\Fakes;

use App\Services\Auth\GoogleIdTokenVerifierInterface;

class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    /**
     * @param array<string, mixed>|null $payload The canned payload to return, or null to simulate an invalid token.
     */
    public function __construct(private readonly ?array $payload)
    {
    }

    public function verify(string $idToken, string $clientId): ?array
    {
        return $this->payload;
    }
}
