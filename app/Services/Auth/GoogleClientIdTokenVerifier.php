<?php

namespace App\Services\Auth;

use Google\Client as GoogleClient;

class GoogleClientIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public function verify(string $idToken, string $clientId): ?array
    {
        $client = new GoogleClient(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($idToken);

        return $payload ?: null;
    }
}
