<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivationCodeBatch;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\SecurityEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPanelWebController extends Controller
{
    public function dashboard(Request $request): View
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', UserStatus::ACTIVE)->count(),
            'total_devices' => UserDevice::count(),
            'active_subscriptions' => Subscription::where('status', SubscriptionStatus::ACTIVE)->count(),
            'total_events' => SecurityEvent::count(),
            'total_batches' => ActivationCodeBatch::count(),
        ];

        $recentEvents = SecurityEvent::with(['user:id,name,phone_number', 'device:id,device_name,model'])
            ->latest()
            ->limit(10)
            ->get();

        $plans = ActivationPlan::withCount(['activationCodes', 'subscriptions'])->get();

        $recentBatches = ActivationCodeBatch::with('plan')
            ->withCount(['codes', 'codes as redeemed_count' => function ($q) {
                $q->where('is_redeemed', true);
            }])
            ->latest()
            ->limit(5)
            ->get();

        $recentAuditLogs = AdminAuditLog::with('admin:id,name,role')->latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentEvents', 'plans', 'recentBatches', 'recentAuditLogs'));
    }
}
