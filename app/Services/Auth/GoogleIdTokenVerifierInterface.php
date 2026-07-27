<?php

namespace App\Services\Auth;

interface GoogleIdTokenVerifierInterface
{
    /**
     * Verify a Google ID token and return its decoded payload, or null if invalid.
     */
    public function verify(string $idToken, string $clientId): ?array;
}
