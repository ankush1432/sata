<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\SecurityCommand;
use App\Models\Subscription;
use App\Models\UserDevice;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class AdminScheduledJobController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get system status and configuration for scheduled jobs.
     * GET /admin/scheduled-jobs
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();

        $config = [
            'reminder_stages' => config('subscription.reminder_stages', [7, 3, 1]),
            'expiring_threshold_days' => config('subscription.expiring_threshold_days', 7),
            'device_offline_threshold_hours' => config('subscription.device_offline_threshold_hours', 24),
            'command_expiration_hours' => config('subscription.command_expiration_hours', 24),
        ];

        $now = now();
        $offlineCutoff = $now->copy()->subHours($config['device_offline_threshold_hours']);

        $stats = [
            'subscriptions' => [
                'active' => Subscription::where('status', SubscriptionStatus::ACTIVE)->count(),
                'expiring' => Subscription::where('status', SubscriptionStatus::EXPIRING)->count(),
                'expired' => Subscription::where('status', SubscriptionStatus::EXPIRED)->count(),
                'due_for_reminder' => Subscription::whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', $now)
                    ->where('expires_at', '<=', $now->copy()->addDays(7))
                    ->count(),
            ],
            'devices' => [
                'active' => UserDevice::where('status', DeviceStatus::ACTIVE)->count(),
                'inactive' => UserDevice::where('status', DeviceStatus::INACTIVE)->count(),
                'revoked' => UserDevice::where('status', DeviceStatus::REVOKED)->count(),
                'pending_offline' => UserDevice::where('status', DeviceStatus::ACTIVE)
                    ->where(function ($q) use ($offlineCutoff) {
                        $q->where(function ($sub) use ($offlineCutoff) {
                            $sub->whereNotNull('last_seen_at')->where('last_seen_at', '<=', $offlineCutoff);
                        })->orWhere(function ($sub) use ($offlineCutoff) {
                            $sub->whereNull('last_seen_at')->where('created_at', '<=', $offlineCutoff);
                        });
                    })->count(),
            ],
            'security_commands' => [
                'pending' => SecurityCommand::where('status', CommandStatus::PENDING)->count(),
                'sent' => SecurityCommand::where('status', CommandStatus::SENT)->count(),
                'expired' => SecurityCommand::where('status', CommandStatus::EXPIRED)->count(),
                'pending_sweep' => SecurityCommand::whereIn('status', [CommandStatus::PENDING, CommandStatus::SENT])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $now)
                    ->count(),
            ],
        ];

        $jobs = [
            [
                'command' => 'subscriptions:process-expiry',
                'name' => 'Process Subscription Expiry',
                'schedule' => 'Hourly',
                'description' => 'Transitions ACTIVE subscriptions approaching expiry to EXPIRING and past expiry to EXPIRED.',
            ],
            [
                'command' => 'notifications:process-reminders',
                'name' => 'Process Expiry Reminders',
                'schedule' => 'Hourly',
                'description' => 'Dispatches multi-stage notifications at 7, 3, and 1 days before expiration.',
            ],
            [
                'command' => 'devices:mark-offline',
                'name' => 'Mark Devices Offline',
                'schedule' => 'Every 30 minutes',
                'description' => 'Marks active devices as INACTIVE after 24h of inactivity, preserving device history.',
            ],
            [
                'command' => 'security-commands:expire',
                'name' => 'Expire Security Commands',
                'schedule' => 'Every 15 minutes',
                'description' => 'Sweeps pending and sent security commands whose TTL has elapsed, transitioning to EXPIRED.',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $config,
                'stats' => $stats,
                'jobs' => $jobs,
                'timestamp' => $now->toIso8601String(),
            ],
        ]);
    }

    /**
     * Manually trigger a scheduled job on demand.
     * POST /admin/scheduled-jobs/run
     */
    public function run(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->hasPermission(AdminPermission::ADMINS_MANAGE) && ! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'job' => ['required', 'string', 'in:subscriptions:process-expiry,notifications:process-reminders,devices:mark-offline,security-commands:expire,all'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'stages' => ['nullable', 'string'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $job = $request->input('job');
        $executed = [];

        if ($job === 'all' || $job === 'subscriptions:process-expiry') {
            $params = [];
            if ($request->filled('days')) {
                $params['--days'] = (int) $request->input('days');
            }
            Artisan::call('subscriptions:process-expiry', $params);
            $executed['subscriptions:process-expiry'] = trim(Artisan::output());
        }

        if ($job === 'all' || $job === 'notifications:process-reminders') {
            $params = [];
            if ($request->filled('stages')) {
                $params['--stages'] = $request->input('stages');
            }
            Artisan::call('notifications:process-reminders', $params);
            $executed['notifications:process-reminders'] = trim(Artisan::output());
        }

        if ($job === 'all' || $job === 'devices:mark-offline') {
            $params = [];
            if ($request->filled('hours')) {
                $params['--hours'] = (int) $request->input('hours');
            }
            Artisan::call('devices:mark-offline', $params);
            $executed['devices:mark-offline'] = trim(Artisan::output());
        }

        if ($job === 'all' || $job === 'security-commands:expire') {
            Artisan::call('security-commands:expire');
            $executed['security-commands:expire'] = trim(Artisan::output());
        }

        $this->auditLogService->log(
            $admin,
            'SCHEDULED_JOB_MANUALLY_EXECUTED',
            'SYSTEM_AUTOMATION',
            null,
            null,
            [
                'triggered_job' => $job,
                'results' => $executed,
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => "Job '{$job}' executed successfully.",
            'data' => [
                'job' => $job,
                'outputs' => $executed,
                'executed_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
