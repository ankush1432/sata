<?php

namespace App\Models;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'custom_permissions',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'custom_permissions' => 'array',
            'last_login_at' => 'datetime',
            'role' => AdminRole::class,
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }

    public function createdBatches(): HasMany
    {
        return $this->hasMany(ActivationCodeBatch::class, 'created_by_admin_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminRole::SUPER_ADMIN;
    }

    /**
     * Get all effective permissions for this admin.
     *
     * @return string[]
     */
    public function getPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return AdminPermission::values();
        }

        $rolePermissions = $this->role instanceof AdminRole
            ? $this->role->defaultPermissions()
            : [];

        $custom = is_array($this->custom_permissions) ? $this->custom_permissions : [];

        return array_values(array_unique(array_merge($rolePermissions, $custom)));
    }

    /**
     * Check if this admin has the specified permission.
     */
    public function hasPermission(string|AdminPermission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $permValue = $permission instanceof AdminPermission ? $permission->value : $permission;

        return in_array($permValue, $this->getPermissions(), true);
    }

    /**
     * Determine if admin can execute the given security command.
     * SUPPORT role is strictly forbidden from destructive commands (WIPE, LOCK, SIREN, ALARM, FACTORY_RESET)
     * unless explicitly granted via custom permissions.
     */
    public function canExecuteSecurityCommand(string $commandType): bool
    {
        if (! $this->hasPermission(AdminPermission::SECURITY_COMMANDS_EXECUTE)) {
            return false;
        }

        $destructive = in_array(strtoupper($commandType), [
            'WIPE', 'WIPE_DATA', 'FACTORY_RESET', 'LOCK', 'LOCK_DEVICE', 'KIOSK_LOCK',
        ], true);

        if ($destructive && $this->role === AdminRole::SUPPORT) {
            $custom = is_array($this->custom_permissions) ? $this->custom_permissions : [];
            return in_array('security_commands.destructive', $custom, true);
        }

        return true;
    }
}
