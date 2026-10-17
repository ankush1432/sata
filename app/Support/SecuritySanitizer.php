<?php

namespace App\Support;

class SecuritySanitizer
{
    /**
     * Keys that represent sensitive credentials and secrets.
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'admin_password',
        'reauth_password',
        'user_password',
        'current_password',
        'new_password',
        'password_confirmation',
        'otp',
        'otp_code',
        'verification_code',
        'code',
        'activation_code',
        'plain_code',
        'token',
        'auth_token',
        'access_token',
        'refresh_token',
        'bearer_token',
        'fcm_token',
        'secret',
        'private_secret',
        'client_secret',
        'private_key',
        'public_key',
        'api_key',
        'pin',
        'credentials',
    ];

    /**
     * Recursively sanitize array data, redacting any sensitive key values.
     */
    public static function sanitize(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', (string) $key));

            if (self::isSensitiveKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } elseif (is_string($value) && self::containsLikelySecret($value)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a key name matches any known sensitive patterns.
     */
    protected static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string value contains bearer tokens or raw JWT signatures.
     */
    protected static function containsLikelySecret(string $value): bool
    {
        if (str_starts_with(strtolower($value), 'bearer ') && strlen($value) > 20) {
            return true;
        }

        return false;
    }
}
