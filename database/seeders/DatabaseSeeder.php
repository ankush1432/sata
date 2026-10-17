<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Enums\PlanDuration;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ActivationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Super Admin
        $superAdmin = Admin::updateOrCreate(
            ['email' => 'admin@sata.security'],
            [
                'name' => 'SATA Super Admin',
                'password' => Hash::make('password123'),
                'role' => AdminRole::SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        // 2. Create Activation Plans
        $plansData = [
            [
                'name' => 'Monthly Shield',
                'code' => 'MONTHLY',
                'duration_days' => PlanDuration::MONTHLY->days(),
                'price' => 9.99,
                'currency' => 'USD',
                'description' => 'Full security coverage for 30 days.',
                'status' => 'ACTIVE',
                'is_active' => true,
            ],
            [
                'name' => 'Quarterly Sentinel',
                'code' => 'QUARTERLY',
                'duration_days' => PlanDuration::QUARTERLY->days(),
                'price' => 24.99,
                'currency' => 'USD',
                'description' => 'Extended anti-theft protection for 90 days.',
                'status' => 'ACTIVE',
                'is_active' => true,
            ],
            [
                'name' => 'Half-Yearly Aegis',
                'code' => 'HALF_YEARLY',
                'duration_days' => PlanDuration::HALF_YEARLY->days(),
                'price' => 44.99,
                'currency' => 'USD',
                'description' => 'Comprehensive monitoring for 180 days.',
                'status' => 'ACTIVE',
                'is_active' => true,
            ],
            [
                'name' => 'Yearly Fortress',
                'code' => 'YEARLY',
                'duration_days' => PlanDuration::YEARLY->days(),
                'price' => 79.99,
                'currency' => 'USD',
                'description' => 'Maximum continuous protection with annual renewal.',
                'status' => 'ACTIVE',
                'is_active' => true,
            ],
        ];

        $createdPlans = [];
        foreach ($plansData as $planData) {
            $createdPlans[$planData['code']] = ActivationPlan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }

        // 3. Generate initial batch of activation codes
        $activationService = app(ActivationService::class);
        $yearlyBatch = $activationService->generateBatch(
            $createdPlans['YEARLY'],
            5,
            $superAdmin,
            'Initial production test batch'
        );

        // 4. Create demo user and device
        $demoUser = User::firstOrCreate(
            ['phone_number' => '+15550192834'],
            [
                'name' => 'Alex Vance',
                'email' => 'alex.vance@example.com',
                'status' => UserStatus::PENDING,
            ]
        );

        $demoDevice = UserDevice::firstOrCreate(
            ['device_uuid' => 'demo-device-uuid-991823'],
            [
                'user_id' => $demoUser->id,
                'device_name' => 'Pixel 9 Pro',
                'model' => 'Google Pixel 9 Pro',
                'platform' => 'android',
                'os_version' => 'Android 15',
                'app_version' => '1.0.0-phase1',
                'status' => DeviceStatus::ACTIVE,
                'is_protection_enabled' => true,
                'last_seen_at' => now(),
            ]
        );

        $demoUser->update(['current_device_id' => $demoDevice->id]);

        // Redeem one code from batch for demo user
        $firstCode = $yearlyBatch->codes()->first();
        if ($firstCode) {
            $activationService->redeemCode($firstCode->code, $demoUser);
        }

        // 5. Create initial audit log
        AdminAuditLog::create([
            'admin_id' => $superAdmin->id,
            'action' => 'SYSTEM_INITIALIZED',
            'module' => 'SYSTEM',
            'target_type' => Admin::class,
            'target_id' => $superAdmin->id,
            'before_payload' => null,
            'after_payload' => ['plans_seeded' => count($plansData)],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SATA Seed Runner',
        ]);
    }
}
