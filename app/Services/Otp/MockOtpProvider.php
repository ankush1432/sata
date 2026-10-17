<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;
use Illuminate\Support\Facades\Log;

class MockOtpProvider implements OtpProviderInterface
{
    /**
     * In-memory store of dispatched OTPs for testing inspection.
     * Note: Never exposed to production logs or responses.
     *
     * @var array<string, array{code: string, purpose: string, timestamp: int}>
     */
    protected static array $dispatchedOtps = [];

    public function send(string $mobile, string $otp, string $purpose = 'REGISTRATION'): bool
    {
        // Record in memory for testing verification
        self::$dispatchedOtps[$mobile] = [
            'code' => $otp,
            'purpose' => $purpose,
            'timestamp' => time(),
        ];

        // Mask phone number in logs - NEVER log raw OTP
        $maskedMobile = strlen($mobile) > 4
            ? substr($mobile, 0, 3) . '****' . substr($mobile, -2)
            : '****';

        Log::info("OTP dispatched via configured provider to {$maskedMobile} [purpose: {$purpose}]");

        return true;
    }

    /**
     * Helper for tests to retrieve the latest dispatched OTP code without leaking to logs or API.
     */
    public static function getLastOtpFor(string $mobile): ?string
    {
        return self::$dispatchedOtps[$mobile]['code'] ?? null;
    }

    /**
     * Clear all recorded dispatches (for test teardown).
     */
    public static function clear(): void
    {
        self::$dispatchedOtps = [];
    }
}
