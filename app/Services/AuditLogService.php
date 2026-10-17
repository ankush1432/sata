<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Support\SecuritySanitizer;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(
        ?Admin $admin,
        string $action,
        string $module,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'module' => $module,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->getKey(),
            'before_payload' => $before ? SecuritySanitizer::sanitize($before) : null,
            'after_payload' => $after ? SecuritySanitizer::sanitize($after) : null,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
        ]);
    }
}
