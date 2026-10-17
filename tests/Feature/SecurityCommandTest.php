<?php

namespace Tests\Feature;

use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SecurityCommandType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_command_types_and_statuses_are_valid(): void
    {
        $admin = Admin::create([
            'name' => 'Command Admin',
            'email' => 'cmd@sata.security',
            'password' => 'secret',
        ]);

        $user = User::create([
            'name' => 'Device Owner',
            'phone' => '+15554443333',
            'status' => UserStatus::ACTIVE,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'DEV-TEST-001',
            'os_type' => 'ANDROID',
            'status' => DeviceStatus::ACTIVE,
        ]);

        // Test all command types
        foreach (SecurityCommandType::cases() as $type) {
            $cmd = SecurityCommand::create([
                'command_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'device_id' => $device->id,
                'command_type' => $type,
                'requested_by_admin_id' => $admin->id,
                'status' => CommandStatus::PENDING,
                'reason' => 'Test command execution',
            ]);

            $this->assertEquals($type, $cmd->command_type);
            $this->assertEquals(CommandStatus::PENDING, $cmd->status);
        }

        // Test all command statuses
        foreach (CommandStatus::cases() as $status) {
            $cmd = SecurityCommand::create([
                'command_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'device_id' => $device->id,
                'command_type' => SecurityCommandType::KIOSK_LOCK,
                'requested_by_admin_id' => $admin->id,
                'status' => $status,
                'reason' => 'Status cycle test',
            ]);

            $this->assertEquals($status, $cmd->status);

            // Verify logging
            $log = SecurityCommandLog::create([
                'security_command_id' => $cmd->id,
                'status_from' => 'PENDING',
                'status_to' => $status->value,
                'message' => "Transitioned to {$status->value}",
            ]);

            $this->assertEquals($cmd->id, $log->security_command_id);
        }
    }
}
