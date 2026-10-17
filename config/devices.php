<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Registered Devices Per User
    |--------------------------------------------------------------------------
    |
    | Defines the maximum number of active devices a single user can register.
    |
    */
    'max_devices_per_user' => (int) env('MAX_DEVICES_PER_USER', 5),

    /*
    |--------------------------------------------------------------------------
    | Device Offline Threshold (Hours)
    |--------------------------------------------------------------------------
    |
    | Number of hours of inactivity before an active device is marked offline.
    |
    */
    'offline_threshold_hours' => (int) env('DEVICE_OFFLINE_HOURS', 24),
];
