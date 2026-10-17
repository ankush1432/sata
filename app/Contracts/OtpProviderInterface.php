<?php

namespace App\Contracts;

interface OtpProviderInterface
{
    /**
     * Send OTP to recipient mobile number.
     *
     * @param string $mobile Recipient mobile number
     * @param string $otp Cryptographically secure OTP code
     * @param string $purpose Purpose of OTP (e.g. REGISTRATION, LOGIN)
     * @return bool True if dispatch succeeded
     */
    public function send(string $mobile, string $otp, string $purpose = 'REGISTRATION'): bool;
}
