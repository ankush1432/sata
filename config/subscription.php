<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Reminder Stages (in days before expiry)
    |--------------------------------------------------------------------------
    |
    | Defines the configurable reminder stages (in days before expiration)
    | when automated reminder notifications must be dispatched to users.
    | Default stages: 7 days, 3 days, 1 day before expiry.
    |
    */
    'reminder_stages' => array_values(array_filter(array_map('intval', explode(',', env('SUBSCRIPTION_REMINDER_STAGES', '7,3,1'))))),

    /*
    |--------------------------------------------------------------------------
    | Expiring Status Threshold (in days)
    |--------------------------------------------------------------------------
    |
    | When remaining days before expiration is less than or equal to this
    | threshold, ACTIVE subscriptions transition to EXPIRING status.
    | Default: 7 days.
    |
    */
    'expiring_threshold_days' => (int) env('SUBSCRIPTION_EXPIRING_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Device Offline Threshold (in hours)
    |--------------------------------------------------------------------------
    |
    | Active devices with no heartbeat/last_seen_at for longer than this
    | threshold will be marked as INACTIVE (offline) by the scheduled job.
    | Default: 24 hours.
    |
    */
    'device_offline_threshold_hours' => (int) env('DEVICE_OFFLINE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Security Command TTL / Expiration Threshold (in hours)
    |--------------------------------------------------------------------------
    |
    | Default time-to-live for pending security commands before being expired
    | by the scheduled job. Default: 24 hours.
    |
    */
    'command_expiration_hours' => (int) env('SECURITY_COMMAND_TTL_HOURS', 24),
];
