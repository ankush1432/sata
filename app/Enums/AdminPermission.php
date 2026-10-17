<?php

namespace App\Enums;

enum AdminPermission: string
{
    // Users
    case USERS_VIEW = 'users.view';
    case USERS_MANAGE = 'users.manage';
    case USERS_DISABLE = 'users.disable';

    // Activation Plans
    case ACTIVATION_PLANS_VIEW = 'activation_plans.view';
    case ACTIVATION_PLANS_MANAGE = 'activation_plans.manage';

    // Activation Codes
    case ACTIVATION_CODES_VIEW = 'activation_codes.view';
    case ACTIVATION_CODES_GENERATE = 'activation_codes.generate';
    case ACTIVATION_CODES_DISABLE = 'activation_codes.disable';

    // Subscriptions
    case SUBSCRIPTIONS_VIEW = 'subscriptions.view';
    case SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';

    // Devices
    case DEVICES_VIEW = 'devices.view';
    case DEVICES_REVOKE = 'devices.revoke';

    // Trusted Users
    case TRUSTED_USERS_VIEW = 'trusted_users.view';

    // Security Events & Evidence
    case SECURITY_EVENTS_VIEW = 'security_events.view';
    case SECURITY_EVIDENCE_VIEW = 'security_evidence.view';

    // Security Commands
    case SECURITY_COMMANDS_VIEW = 'security_commands.view';
    case SECURITY_COMMANDS_EXECUTE = 'security_commands.execute';

    // Notifications
    case NOTIFICATIONS_VIEW = 'notifications.view';

    // Admins
    case ADMINS_VIEW = 'admins.view';
    case ADMINS_MANAGE = 'admins.manage';

    // Audit Logs
    case AUDIT_LOGS_VIEW = 'audit_logs.view';

    /**
     * Get all permission values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a permission value is valid.
     */
    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::values(), true);
    }
}
