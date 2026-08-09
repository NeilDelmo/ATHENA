<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class LiteratureFullTextToken
{
    /** @param array{url: string, provider: string, identifier?: string|null} $source */
    public static function issue(array $source): string
    {
        return Crypt::encryptString(json_encode([
            ...$source,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{url: string, provider: string, identifier: ?string} */
    public static function decode(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages([
                'source_token' => 'This full-text preview link is invalid or has expired. Search for the paper again.',
            ]);
        }

        if (! is_array($payload)
            || ! is_string($payload['url'] ?? null)
            || ! is_string($payload['provider'] ?? null)
            || (int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'source_token' => 'This full-text preview link is invalid or has expired. Search for the paper again.',
            ]);
        }

        return [
            'url' => $payload['url'],
            'provider' => $payload['provider'],
            'identifier' => is_string($payload['identifier'] ?? null) ? $payload['identifier'] : null,
        ];
    }
}
