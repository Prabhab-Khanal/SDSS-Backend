<?php

namespace App\Services;

use App\Models\AccessRequest;
use App\Models\AuthorizationToken;
use Illuminate\Support\Str;

class AuthorizationTokenService
{
    public function generate(AccessRequest $accessRequest): AuthorizationToken
    {
        return AuthorizationToken::create([
            'token'             => Str::random(64),
            'access_request_id' => $accessRequest->id,
            'user_id'           => $accessRequest->requester_id,
            'file_id'           => $accessRequest->file_id,
            'expires_at'        => now()->addMinutes(5),
        ]);
    }

    public function validate(string $token): AuthorizationToken
    {
        $authToken = AuthorizationToken::where('token', $token)->first();

        if (!$authToken) {
            abort(404, 'Authorization token not found.');
        }

        if ($authToken->isExpired()) {
            abort(403, 'Authorization token has expired.');
        }

        if ($authToken->isConsumed()) {
            abort(403, 'Authorization token has already been used.');
        }

        if ($authToken->isRevoked()) {
            abort(403, 'Authorization token has been revoked.');
        }

        return $authToken;
    }

    public function consume(AuthorizationToken $token): void
    {
        $token->update(['used_at' => now()]);
    }
}
