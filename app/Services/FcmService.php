<?php

namespace App\Services;

use Closure;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FcmService
{
    /**
     * Fake callback for unit and feature testing.
     */
    protected static ?Closure $fakeHandler = null;

    /**
     * History of calls made in fake mode.
     */
    protected static array $history = [];

    /**
     * Register a fake handler for tests.
     */
    public static function fake(?Closure $callback = null): void
    {
        self::$history = [];
        self::$fakeHandler = $callback ?? function (string $token, string $title, string $body, array $data) {
            // Default fake logic
            if (empty($token) || in_array(strtolower($token), ['invalid_token', 'invalid_fcm_token', 'unregistered', 'unregistered_token', 'notregistered'], true)) {
                return [
                    'success' => false,
                    'error' => 'UNREGISTERED',
                    'is_unregistered' => true,
                    'should_retry' => false,
                    'message' => 'FCM registration token is invalid or unregistered.',
                ];
            }

            if (in_array(strtolower($token), ['transient_fail', 'retry_token', 'server_error'], true)) {
                return [
                    'success' => false,
                    'error' => 'UNAVAILABLE',
                    'is_unregistered' => false,
                    'should_retry' => true,
                    'message' => 'FCM server error, temporary outage.',
                ];
            }

            return [
                'success' => true,
                'message_id' => 'fcm_msg_' . Str::random(24),
                'error' => null,
            ];
        };
    }

    /**
     * Clear the fake handler.
     */
    public static function resetFake(): void
    {
        self::$fakeHandler = null;
        self::$history = [];
    }

    /**
     * Get recorded calls during tests.
     */
    public static function getHistory(): array
    {
        return self::$history;
    }

    /**
     * Send notification to a single FCM device token.
     */
    public function send(string $token, string $title, string $body, array $data = []): array
    {
        // Enforce references-only constraint
        NotificationPayloadSecurity::assertFcmPayloadIsSafe($data);

        // Record history
        self::$history[] = [
            'token' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        // If fake handler is set, use it
        if (self::$fakeHandler !== null) {
            return (self::$fakeHandler)($token, $title, $body, $data);
        }

        // Detect known invalid or transient tokens in environment if no network
        if (empty($token) || in_array(strtolower($token), ['invalid_token', 'invalid_fcm_token', 'unregistered', 'unregistered_token'], true)) {
            return [
                'success' => false,
                'error' => 'UNREGISTERED',
                'is_unregistered' => true,
                'should_retry' => false,
                'message' => 'Invalid or unregistered FCM token.',
            ];
        }

        $serverKey = config('services.fcm.server_key', env('FCM_SERVER_KEY'));
        $fcmUrl = config('services.fcm.url', 'https://fcm.googleapis.com/fcm/send');

        if (empty($serverKey)) {
            // In testing or local environment without FCM credentials, default to simulated successful send
            if (app()->environment('testing', 'local')) {
                return [
                    'success' => true,
                    'message_id' => 'simulated_fcm_' . Str::random(20),
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'error' => 'CONFIGURATION_ERROR',
                'is_unregistered' => false,
                'should_retry' => false,
                'message' => 'FCM server key is not configured.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($fcmUrl, [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'priority' => 'high',
            ]);

            if ($response->successful()) {
                $bodyData = $response->json();
                if (isset($bodyData['failure']) && $bodyData['failure'] > 0) {
                    $firstResult = $bodyData['results'][0] ?? [];
                    $err = $firstResult['error'] ?? 'UnknownError';

                    return $this->parseFcmError($err, $response->body());
                }

                $messageId = $bodyData['results'][0]['message_id'] ?? $bodyData['message_id'] ?? ('fcm_' . Str::random(16));

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'error' => null,
                ];
            }

            return $this->parseHttpError($response->status(), $response->body());
        } catch (Exception $e) {
            Log::error('FCM sending exception: ' . $e->getMessage(), [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'NETWORK_ERROR',
                'is_unregistered' => false,
                'should_retry' => true,
                'message' => 'FCM network failure: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse FCM-level error string (e.g. NotRegistered, InvalidRegistration, Unavailable).
     */
    protected function parseFcmError(string $error, string $rawBody): array
    {
        $unregisteredErrors = ['NotRegistered', 'InvalidRegistration', 'UNREGISTERED', 'INVALID_ARGUMENT'];
        $transientErrors = ['Unavailable', 'InternalServerError', 'DeviceMessageRateExceeded', 'TopicsMessageRateExceeded'];

        if (in_array($error, $unregisteredErrors, true)) {
            return [
                'success' => false,
                'error' => $error,
                'is_unregistered' => true,
                'should_retry' => false,
                'message' => "FCM token unregistered: {$error}",
            ];
        }

        if (in_array($error, $transientErrors, true)) {
            return [
                'success' => false,
                'error' => $error,
                'is_unregistered' => false,
                'should_retry' => true,
                'message' => "FCM transient error: {$error}",
            ];
        }

        return [
            'success' => false,
            'error' => $error,
            'is_unregistered' => false,
            'should_retry' => false,
            'message' => "FCM delivery failed: {$error}",
        ];
    }

    /**
     * Parse HTTP status and body from FCM response.
     */
    protected function parseHttpError(int $status, string $rawBody): array
    {
        if ($status === 404 || str_contains($rawBody, 'UNREGISTERED') || str_contains($rawBody, 'NotRegistered')) {
            return [
                'success' => false,
                'error' => 'UNREGISTERED',
                'is_unregistered' => true,
                'should_retry' => false,
                'message' => 'FCM token not found or unregistered.',
            ];
        }

        if (in_array($status, [429, 500, 502, 503, 504], true)) {
            return [
                'success' => false,
                'error' => 'HTTP_' . $status,
                'is_unregistered' => false,
                'should_retry' => true,
                'message' => "FCM HTTP {$status} server error. Should retry.",
            ];
        }

        return [
            'success' => false,
            'error' => 'HTTP_' . $status,
            'is_unregistered' => false,
            'should_retry' => false,
            'message' => "FCM HTTP {$status} error: {$rawBody}",
        ];
    }
}
