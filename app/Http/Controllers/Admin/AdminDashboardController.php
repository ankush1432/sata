<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\SecurityCommand;
use App\Models\SecurityEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Master endpoint returning full dashboard payload:
     * - Metric cards (Users, Subscriptions, Devices, Security)
     * - Charts (Registrations, Subscriptions, Security Events, Device Activity)
     * - Recent Activity (Intruder events, Commands, Registrations, Activations)
     * - Filter metadata and granted permissions
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        // Parse date range filter
        [$dateFrom, $dateTo, $preset] = $this->resolveDateRange($request);

        // Permission evaluation
        $permissions = [
            'users_view' => $admin->hasPermission(AdminPermission::USERS_VIEW),
            'subscriptions_view' => $admin->hasPermission(AdminPermission::SUBSCRIPTIONS_VIEW),
            'devices_view' => $admin->hasPermission(AdminPermission::DEVICES_VIEW),
            'security_events_view' => $admin->hasPermission(AdminPermission::SECURITY_EVENTS_VIEW),
            'security_commands_view' => $admin->hasPermission(AdminPermission::SECURITY_COMMANDS_VIEW),
            'audit_logs_view' => $admin->hasPermission(AdminPermission::AUDIT_LOGS_VIEW),
        ];

        // 1. Metric Cards (Permission-aware)
        $cards = $this->computeCards($permissions, $dateFrom, $dateTo);

        // 2. Charts (Permission-aware server-side aggregations)
        $charts = $this->computeCharts($permissions, $dateFrom, $dateTo);

        // 3. Recent Activity (Permission-aware and eager loaded to avoid N+1)
        $recentActivity = $this->computeRecentActivity($permissions);

        // Audit Trail
        $this->auditLogService->log(
            $admin,
            'DASHBOARD_VIEWED',
            'DASHBOARD',
            null,
            null,
            [
                'preset' => $preset,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ]
        );

        return $this->successResponse([
            'filters' => [
                'preset' => $preset,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'permissions' => $permissions,
            'cards' => $cards,
            'charts' => $charts,
            'recent_activity' => $recentActivity,
            'generated_at' => now()->toIso8601String(),
        ], 'Admin dashboard metrics retrieved successfully.');
    }

    /**
     * Get standalone stats/cards.
     */
    public function stats(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        [$dateFrom, $dateTo, $preset] = $this->resolveDateRange($request);

        $permissions = [
            'users_view' => $admin->hasPermission(AdminPermission::USERS_VIEW),
            'subscriptions_view' => $admin->hasPermission(AdminPermission::SUBSCRIPTIONS_VIEW),
            'devices_view' => $admin->hasPermission(AdminPermission::DEVICES_VIEW),
            'security_events_view' => $admin->hasPermission(AdminPermission::SECURITY_EVENTS_VIEW),
            'security_commands_view' => $admin->hasPermission(AdminPermission::SECURITY_COMMANDS_VIEW),
        ];

        $cards = $this->computeCards($permissions, $dateFrom, $dateTo);

        return $this->successResponse([
            'cards' => $cards,
            'filters' => [
                'preset' => $preset,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ], 'Dashboard statistics retrieved.');
    }

    /**
     * Get standalone chart metrics.
     */
    public function charts(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        [$dateFrom, $dateTo, $preset] = $this->resolveDateRange($request);

        $permissions = [
            'users_view' => $admin->hasPermission(AdminPermission::USERS_VIEW),
            'subscriptions_view' => $admin->hasPermission(AdminPermission::SUBSCRIPTIONS_VIEW),
            'devices_view' => $admin->hasPermission(AdminPermission::DEVICES_VIEW),
            'security_events_view' => $admin->hasPermission(AdminPermission::SECURITY_EVENTS_VIEW),
            'security_commands_view' => $admin->hasPermission(AdminPermission::SECURITY_COMMANDS_VIEW),
        ];

        $charts = $this->computeCharts($permissions, $dateFrom, $dateTo);

        return $this->successResponse([
            'charts' => $charts,
            'filters' => [
                'preset' => $preset,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ], 'Dashboard chart series retrieved.');
    }

    /**
     * Get standalone recent activity collections (with eager loading).
     */
    public function recentActivity(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $permissions = [
            'users_view' => $admin->hasPermission(AdminPermission::USERS_VIEW),
            'subscriptions_view' => $admin->hasPermission(AdminPermission::SUBSCRIPTIONS_VIEW),
            'devices_view' => $admin->hasPermission(AdminPermission::DEVICES_VIEW),
            'security_events_view' => $admin->hasPermission(AdminPermission::SECURITY_EVENTS_VIEW),
            'security_commands_view' => $admin->hasPermission(AdminPermission::SECURITY_COMMANDS_VIEW),
        ];

        $recentActivity = $this->computeRecentActivity($permissions);

        return $this->successResponse([
            'recent_activity' => $recentActivity,
        ], 'Recent activity retrieved.');
    }

    /**
     * Resolve date range filter from query parameters.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolveDateRange(Request $request): array
    {
        $preset = strtolower((string) $request->input('preset', '30d'));
        $now = Carbon::now();

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->input('date_from'))->startOfDay();
            $to = Carbon::parse($request->input('date_to'))->endOfDay();
            return [$from, $to, 'custom'];
        }

        switch ($preset) {
            case '7d':
                $from = $now->copy()->subDays(7)->startOfDay();
                break;
            case '90d':
                $from = $now->copy()->subDays(90)->startOfDay();
                break;
            case '1y':
            case 'year':
                $from = $now->copy()->subYear()->startOfDay();
                break;
            case 'all':
                $from = Carbon::create(2020, 1, 1)->startOfDay();
                break;
            case '30d':
            default:
                $preset = '30d';
                $from = $now->copy()->subDays(30)->startOfDay();
                break;
        }

        $to = $now->copy()->endOfDay();
        return [$from, $to, $preset];
    }

    /**
     * Compute all metric cards with permission awareness.
     */
    protected function computeCards(array $permissions, Carbon $dateFrom, Carbon $dateTo): array
    {
        $cards = [];

        // Users Cards
        if ($permissions['users_view']) {
            $cards['users'] = [
                'total' => User::count(),
                'active' => User::where('status', UserStatus::ACTIVE->value)->count(),
                'pending_otp' => User::where('status', UserStatus::PENDING->value)->count(),
                'awaiting_activation' => User::where('status', UserStatus::AWAITING_ACTIVATION->value)->count(),
                'disabled' => User::where('status', UserStatus::DISABLED->value)->count(),
                'new_in_period' => User::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            ];
        }

        // Subscriptions Cards
        if ($permissions['subscriptions_view']) {
            $now = Carbon::now();
            $thirtyDays = $now->copy()->addDays(30);

            $cards['subscriptions'] = [
                'active' => Subscription::where('status', SubscriptionStatus::ACTIVE->value)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                    })->count(),
                'expiring' => Subscription::where(function ($q) use ($now, $thirtyDays) {
                    $q->where('status', SubscriptionStatus::EXPIRING->value)
                        ->orWhere(function ($sq) use ($now, $thirtyDays) {
                            $sq->where('status', SubscriptionStatus::ACTIVE->value)
                                ->whereNotNull('expires_at')
                                ->whereBetween('expires_at', [$now, $thirtyDays]);
                        });
                })->count(),
                'expired' => Subscription::where(function ($q) use ($now) {
                    $q->where('status', SubscriptionStatus::EXPIRED->value)
                        ->orWhere(function ($sq) use ($now) {
                            $sq->whereNotNull('expires_at')
                                ->where('expires_at', '<', $now);
                        });
                })->count(),
            ];
        }

        // Devices Cards
        if ($permissions['devices_view']) {
            $yesterday = Carbon::now()->subHours(24);

            $cards['devices'] = [
                'active' => UserDevice::where('status', DeviceStatus::ACTIVE->value)
                    ->where('last_seen_at', '>=', $yesterday)
                    ->count(),
                'offline' => UserDevice::where(function ($q) use ($yesterday) {
                    $q->where('status', DeviceStatus::INACTIVE->value)
                        ->orWhere(function ($sq) use ($yesterday) {
                            $sq->where('status', DeviceStatus::ACTIVE->value)
                                ->where(function ($inq) use ($yesterday) {
                                    $inq->whereNull('last_seen_at')
                                        ->orWhere('last_seen_at', '<', $yesterday);
                                });
                        });
                })->count(),
                'revoked' => UserDevice::where('status', DeviceStatus::REVOKED->value)->count(),
                'total' => UserDevice::count(),
            ];
        }

        // Security Cards
        if ($permissions['security_events_view'] || $permissions['security_commands_view']) {
            $cards['security'] = [
                'total_security_events' => SecurityEvent::count(),
                'intruder_alerts' => SecurityEvent::whereIn('event_type', [
                    'INTRUDER_DETECTED',
                    'INTRUDER_PHOTO',
                    'WRONG_PIN_ATTEMPTS',
                ])->count(),
                'sim_alerts' => SecurityEvent::where('event_type', SecurityEventType::SIM_CHANGED->value)->count(),
                'motion_alerts' => SecurityEvent::where('event_type', SecurityEventType::MOTION_DETECTED->value)->count(),
                'pending_commands' => SecurityCommand::where('status', CommandStatus::PENDING->value)->count(),
                'failed_commands' => SecurityCommand::where('status', CommandStatus::FAILED->value)->count(),
            ];
        }

        return $cards;
    }

    /**
     * Compute chart timeseries data.
     */
    protected function computeCharts(array $permissions, Carbon $dateFrom, Carbon $dateTo): array
    {
        $charts = [];
        $days = max(1, (int) $dateFrom->diffInDays($dateTo));

        // 1. User Registrations Chart
        if ($permissions['users_view']) {
            $regQuery = User::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(created_at) as date_val, COUNT(*) as count')
                ->groupBy('date_val')
                ->orderBy('date_val')
                ->pluck('count', 'date_val')
                ->all();

            $registrationsSeries = [];
            $current = $dateFrom->copy();
            while ($current->lte($dateTo)) {
                $d = $current->toDateString();
                $registrationsSeries[] = [
                    'date' => $d,
                    'count' => (int) ($regQuery[$d] ?? 0),
                ];
                $current->addDay();
            }

            $charts['user_registrations'] = $registrationsSeries;
        }

        // 2. Active Subscriptions Chart
        if ($permissions['subscriptions_view']) {
            $subQuery = Subscription::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->selectRaw('DATE(created_at) as date_val, COUNT(*) as count')
                ->groupBy('date_val')
                ->orderBy('date_val')
                ->pluck('count', 'date_val')
                ->all();

            $subSeries = [];
            $current = $dateFrom->copy();
            $baseline = Subscription::where('created_at', '<', $dateFrom)
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->count();
            $running = $baseline;

            while ($current->lte($dateTo)) {
                $d = $current->toDateString();
                $running += (int) ($subQuery[$d] ?? 0);
                $subSeries[] = [
                    'date' => $d,
                    'active_count' => $running,
                    'new_activations' => (int) ($subQuery[$d] ?? 0),
                ];
                $current->addDay();
            }

            $charts['active_subscriptions'] = $subSeries;
        }

        // 3. Security Events Chart
        if ($permissions['security_events_view']) {
            $eventsQuery = SecurityEvent::whereBetween('occurred_at', [$dateFrom, $dateTo])
                ->selectRaw("DATE(occurred_at) as date_val,
                    COUNT(*) as total,
                    SUM(CASE WHEN event_type IN ('INTRUDER_DETECTED', 'INTRUDER_PHOTO', 'WRONG_PIN_ATTEMPTS') THEN 1 ELSE 0 END) as intruder,
                    SUM(CASE WHEN event_type = 'SIM_CHANGED' THEN 1 ELSE 0 END) as sim,
                    SUM(CASE WHEN event_type = 'MOTION_DETECTED' THEN 1 ELSE 0 END) as motion")
                ->groupBy('date_val')
                ->orderBy('date_val')
                ->get()
                ->keyBy('date_val');

            $eventSeries = [];
            $current = $dateFrom->copy();
            while ($current->lte($dateTo)) {
                $d = $current->toDateString();
                $item = $eventsQuery->get($d);
                $eventSeries[] = [
                    'date' => $d,
                    'total' => (int) ($item->total ?? 0),
                    'intruder' => (int) ($item->intruder ?? 0),
                    'sim' => (int) ($item->sim ?? 0),
                    'motion' => (int) ($item->motion ?? 0),
                ];
                $current->addDay();
            }

            $charts['security_events'] = $eventSeries;
        }

        // 4. Device Activity Chart
        if ($permissions['devices_view']) {
            $deviceActivityQuery = UserDevice::whereBetween('last_seen_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(last_seen_at) as date_val, COUNT(*) as pings')
                ->groupBy('date_val')
                ->orderBy('date_val')
                ->pluck('pings', 'date_val')
                ->all();

            $deviceSeries = [];
            $current = $dateFrom->copy();
            while ($current->lte($dateTo)) {
                $d = $current->toDateString();
                $deviceSeries[] = [
                    'date' => $d,
                    'active_pings' => (int) ($deviceActivityQuery[$d] ?? 0),
                ];
                $current->addDay();
            }

            $charts['device_activity'] = $deviceSeries;
        }

        return $charts;
    }

    /**
     * Compute recent activity with strict eager loading to avoid N+1 queries.
     */
    protected function computeRecentActivity(array $permissions): array
    {
        $activity = [];

        // 1. Latest Intruder Events
        if ($permissions['security_events_view']) {
            $activity['latest_intruder_events'] = SecurityEvent::whereIn('event_type', [
                'INTRUDER_DETECTED',
                'INTRUDER_PHOTO',
                'WRONG_PIN_ATTEMPTS',
            ])
                ->with([
                    'user:id,name,email,phone_number',
                    'device:id,device_uuid,device_name,model,platform',
                    'evidence',
                    'locations',
                ])
                ->latest('occurred_at')
                ->take(6)
                ->get()
                ->map(function ($event) {
                    $location = $event->locations->first();
                    $evidence = $event->evidence->first();

                    return [
                        'id' => $event->id,
                        'event_uuid' => $event->event_uuid,
                        'event_type' => $event->event_type instanceof SecurityEventType ? $event->event_type->value : (string) $event->event_type,
                        'severity' => $event->severity ?? 'HIGH',
                        'occurred_at' => $event->occurred_at?->toIso8601String(),
                        'user' => $event->user ? [
                            'id' => $event->user->id,
                            'name' => $event->user->name,
                            'phone' => $event->user->phone_number,
                            'email' => $event->user->email,
                        ] : null,
                        'device' => $event->device ? [
                            'id' => $event->device->id,
                            'name' => $event->device->device_name,
                            'model' => $event->device->model,
                            'platform' => $event->device->platform,
                        ] : null,
                        'has_evidence' => ! empty($evidence),
                        'evidence_url' => $evidence ? "/admin/evidence/{$evidence->id}/stream" : null,
                        'location' => $location ? [
                            'latitude' => (float) $location->latitude,
                            'longitude' => (float) $location->longitude,
                            'accuracy' => (float) $location->accuracy,
                        ] : null,
                    ];
                });
        }

        // 2. Latest Security Commands
        if ($permissions['security_commands_view']) {
            $activity['latest_security_commands'] = SecurityCommand::with([
                'user:id,name,email,phone_number',
                'device:id,device_uuid,device_name,model',
                'admin:id,name,email',
            ])
                ->latest('created_at')
                ->take(6)
                ->get()
                ->map(function ($cmd) {
                    return [
                        'id' => $cmd->id,
                        'command_uuid' => $cmd->command_uuid,
                        'command_type' => $cmd->command_type instanceof \BackedEnum ? $cmd->command_type->value : (string) $cmd->command_type,
                        'status' => $cmd->status instanceof CommandStatus ? $cmd->status->value : (string) $cmd->status,
                        'created_at' => $cmd->created_at?->toIso8601String(),
                        'executed_at' => $cmd->executed_at?->toIso8601String(),
                        'user' => $cmd->user ? [
                            'id' => $cmd->user->id,
                            'name' => $cmd->user->name,
                            'email' => $cmd->user->email,
                        ] : null,
                        'device' => $cmd->device ? [
                            'id' => $cmd->device->id,
                            'name' => $cmd->device->device_name,
                            'model' => $cmd->device->model,
                        ] : null,
                        'dispatched_by' => $cmd->admin ? [
                            'id' => $cmd->admin->id,
                            'name' => $cmd->admin->name,
                            'email' => $cmd->admin->email,
                        ] : null,
                    ];
                });
        }

        // 3. Latest Registrations
        if ($permissions['users_view']) {
            $activity['latest_registrations'] = User::select([
                'id',
                'name',
                'email',
                'phone_number',
                'status',
                'created_at',
            ])
                ->latest('created_at')
                ->take(6)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone_number,
                        'status' => $user->status instanceof UserStatus ? $user->status->value : (string) $user->status,
                        'registered_at' => $user->created_at?->toIso8601String(),
                    ];
                });
        }

        // 4. Latest Subscription Activations
        if ($permissions['subscriptions_view']) {
            $activity['latest_subscription_activations'] = Subscription::whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::EXPIRING->value,
            ])
                ->with([
                    'user:id,name,email,phone_number',
                    'plan:id,name,code',
                    'device:id,device_name,model',
                ])
                ->latest('activated_at')
                ->take(6)
                ->get()
                ->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'status' => $sub->status instanceof SubscriptionStatus ? $sub->status->value : (string) $sub->status,
                        'plan_name' => $sub->plan?->name ?? 'Standard Sentinel',
                        'starts_at' => $sub->starts_at?->toIso8601String() ?? $sub->activated_at?->toIso8601String(),
                        'expires_at' => $sub->expires_at?->toIso8601String(),
                        'days_remaining' => $sub->daysRemaining(),
                        'user' => $sub->user ? [
                            'id' => $sub->user->id,
                            'name' => $sub->user->name,
                            'email' => $sub->user->email,
                            'phone' => $sub->user->phone_number,
                        ] : null,
                    ];
                });
        }

        return $activity;
    }
}
