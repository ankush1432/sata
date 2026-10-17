<?php

namespace App\Enums;

enum AdminRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case SUPPORT = 'SUPPORT';
    case SUPPORT_AGENT = 'SUPPORT_AGENT';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get default permissions granted to this role.
     *
     * @return string[]
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => AdminPermission::values(),

            self::ADMIN => [
                AdminPermission::USERS_VIEW->value,
                AdminPermission::USERS_MANAGE->value,
                AdminPermission::USERS_DISABLE->value,
                AdminPermission::ACTIVATION_PLANS_VIEW->value,
                AdminPermission::ACTIVATION_PLANS_MANAGE->value,
                AdminPermission::ACTIVATION_CODES_VIEW->value,
                AdminPermission::ACTIVATION_CODES_GENERATE->value,
                AdminPermission::ACTIVATION_CODES_DISABLE->value,
                AdminPermission::SUBSCRIPTIONS_VIEW->value,
                AdminPermission::SUBSCRIPTIONS_MANAGE->value,
                AdminPermission::DEVICES_VIEW->value,
                AdminPermission::DEVICES_REVOKE->value,
                AdminPermission::TRUSTED_USERS_VIEW->value,
                AdminPermission::SECURITY_EVENTS_VIEW->value,
                AdminPermission::SECURITY_EVIDENCE_VIEW->value,
                AdminPermission::SECURITY_COMMANDS_VIEW->value,
                AdminPermission::SECURITY_COMMANDS_EXECUTE->value,
                AdminPermission::NOTIFICATIONS_VIEW->value,
                AdminPermission::ADMINS_VIEW->value,
                AdminPermission::AUDIT_LOGS_VIEW->value,
            ],

            self::SUPPORT_AGENT => [],

            self::SUPPORT => [
                AdminPermission::USERS_VIEW->value,
                AdminPermission::ACTIVATION_PLANS_VIEW->value,
                AdminPermission::ACTIVATION_CODES_VIEW->value,
                AdminPermission::SUBSCRIPTIONS_VIEW->value,
                AdminPermission::DEVICES_VIEW->value,
                AdminPermission::TRUSTED_USERS_VIEW->value,
                AdminPermission::SECURITY_EVENTS_VIEW->value,
                AdminPermission::SECURITY_EVIDENCE_VIEW->value,
                AdminPermission::SECURITY_COMMANDS_VIEW->value,
                AdminPermission::SECURITY_COMMANDS_EXECUTE->value,
                AdminPermission::NOTIFICATIONS_VIEW->value,
            ],
        };
    }
}
