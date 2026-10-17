<?php

namespace App\Services;

use App\Contracts\OtpProviderInterface;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public const MAX_ATTEMPTS = 5;
    public const EXPIRY_MINUTES = 10;

    public function __construct(
        protected OtpProviderInterface $otpProvider
    ) {}

    /**
     * Generate and store a cryptographically secure OTP for a mobile/phone number.
     * Note: Only the bcrypt hash is stored in the database.
     */
    public function generateOtp(string $phoneNumber, string $purpose = 'LOGIN', ?string $ipAddress = null, ?int $userId = null): string
    {
        // Cryptographically secure 6-digit random code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate/expire any existing active OTP for this phone and purpose
        OtpVerification::where('phone_number', $phoneNumber)
            ->where('is_verified', false)
            ->update(['expires_at' => now()]);

        // Create new verification record with ONLY the hash
        OtpVerification::create([
            'user_id' => $userId,
            'phone_number' => $phoneNumber,
            'phone' => $phoneNumber,
            'otp_code' => null, // Explicitly null: never store plaintext OTP
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'attempts' => 0,
            'is_verified' => false,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'ip_address' => $ipAddress,
        ]);

        // Dispatch through configured OTP provider
        $this->otpProvider->send($phoneNumber, $code, $purpose);

        return $code;
    }

    /**
     * Verify an OTP code against stored hash with strict attempt tracking and expiration.
     *
     * @return array{success: bool, status: string, message: string, record: ?OtpVerification}
     */
    public function verifyOtp(string $phoneNumber, string $code, ?string $purpose = null): array|bool
    {
        $query = OtpVerification::where('phone_number', $phoneNumber)
            ->where('is_verified', false);

        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }

        $record = $query->latest('id')->first();

        if (! $record) {
            return [
                'success' => false,
                'status' => 'NOT_FOUND',
                'message' => 'No active verification code found for this mobile number.',
                'record' => null,
            ];
        }

        // Check if expired
        if ($record->expires_at->isPast()) {
            return [
                'success' => false,
                'status' => 'EXPIRED',
                'message' => 'Verification code has expired. Please request a new one.',
                'record' => $record,
            ];
        }

        // Check if max attempts reached
        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->update(['expires_at' => now()]);

            return [
                'success' => false,
                'status' => 'MAX_ATTEMPTS',
                'message' => 'Maximum verification attempts exceeded. Please request a new code.',
                'record' => $record,
            ];
        }

        $record->increment('attempts');

        // Verify with constant-time/bcrypt hash check
        if (! Hash::check($code, $record->code_hash)) {
            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->update(['expires_at' => now()]);

                return [
                    'success' => false,
                    'status' => 'MAX_ATTEMPTS',
                    'message' => 'Maximum verification attempts exceeded. Please request a new code.',
                    'record' => $record,
                ];
            }

            $remaining = self::MAX_ATTEMPTS - $record->attempts;

            return [
                'success' => false,
                'status' => 'INVALID',
                'message' => "Invalid verification code. {$remaining} attempt(s) remaining.",
                'record' => $record,
            ];
        }

        // Valid OTP: mark as verified and expire any remaining codes
        $record->markAsVerified();

        OtpVerification::where('phone_number', $phoneNumber)
            ->where('is_verified', false)
            ->update(['expires_at' => now()]);

        return [
            'success' => true,
            'status' => 'SUCCESS',
            'message' => 'Verification successful.',
            'record' => $record,
        ];
    }
}
