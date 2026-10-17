<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SATA — Smart Anti-Theft Application Admin Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <!-- Top Header -->
    <header class="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-display font-bold text-lg tracking-tight text-white">SATA</span>
                    <span class="text-xs text-slate-400 ml-2 px-2 py-0.5 rounded bg-slate-800 border border-slate-700">Phase 1 Backend</span>
                </div>
            </div>
            <div class="flex items-center space-x-4 text-sm">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span>
                    API & DB Online
                </span>
                <span class="text-slate-400 text-xs hidden sm:inline">Authority: Backend authoritative</span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Stats Grid -->
        <section class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Total Users</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total_users'] }}</p>
                <p class="text-xs text-emerald-400 mt-1">{{ $stats['active_users'] }} Active</p>
            </div>
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Active Subs</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['active_subscriptions'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Paid Accounts</p>
            </div>
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Devices</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total_devices'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Registered</p>
            </div>
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Security Events</p>
                <p class="text-2xl font-bold text-rose-400 mt-1">{{ $stats['total_events'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Immutable Log</p>
            </div>
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Code Batches</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total_batches'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Generated</p>
            </div>
            <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Evidence Disk</p>
                <p class="text-sm font-semibold text-emerald-400 mt-2">Private Secure</p>
                <p class="text-xs text-slate-400 mt-1">Non-Public</p>
            </div>
        </section>

        <!-- Plans and Batches Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Activation Plans -->
            <section class="rounded-xl bg-slate-800/50 border border-slate-700/60 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="font-display font-semibold text-white">Activation Plans (Foundation)</h2>
                    <span class="text-xs text-slate-400">{{ count($plans) }} Plans</span>
                </div>
                <div class="p-5 divide-y divide-slate-700/40">
                    @forelse($plans as $plan)
                        <div class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="font-medium text-white">{{ $plan->name }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-slate-700 text-slate-300 font-mono">{{ $plan->code }}</span>
                                    @if($plan->status === 'ACTIVE')
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                                    @else
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Disabled</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $plan->duration_days }} Days Duration &bull; ${{ number_format($plan->price, 2) }} {{ $plan->currency }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-medium text-emerald-400">{{ $plan->subscriptions_count }} subs</span>
                                <p class="text-xs text-slate-400">{{ $plan->activation_codes_count }} codes total</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 py-4 text-center">No plans seeded yet.</p>
                    @endforelse
                </div>
            </section>

            <!-- Code Batches -->
            <section class="rounded-xl bg-slate-800/50 border border-slate-700/60 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="font-display font-semibold text-white">Recent Activation Batches</h2>
                    <span class="text-xs text-slate-400">Inventory</span>
                </div>
                <div class="p-5 divide-y divide-slate-700/40">
                    @forelse($recentBatches as $batch)
                        <div class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between">
                            <div>
                                <span class="font-mono text-sm text-sky-400 font-medium">{{ $batch->batch_number }}</span>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $batch->plan?->name }} &bull; {{ $batch->notes ?? 'Standard Generation' }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-semibold text-white">{{ $batch->redeemed_count }} / {{ $batch->codes_count }} Redeemed</span>
                                <p class="text-xs text-slate-400">{{ $batch->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 py-4 text-center">No batches generated yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <!-- Recent Security Events -->
        <section class="rounded-xl bg-slate-800/50 border border-slate-700/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/60 flex items-center justify-between">
                <div>
                    <h2 class="font-display font-semibold text-white">Security Events Log</h2>
                    <p class="text-xs text-slate-400">Immutable audit log stream</p>
                </div>
                <span class="text-xs text-rose-400 px-2 py-0.5 rounded bg-rose-500/10 border border-rose-500/20">Audit Protected</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-700/60">
                        <tr>
                            <th class="px-5 py-3">Timestamp</th>
                            <th class="px-5 py-3">Event Type</th>
                            <th class="px-5 py-3">Severity</th>
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Device</th>
                            <th class="px-5 py-3">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($recentEvents as $event)
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="px-5 py-3 whitespace-nowrap text-slate-400 font-mono">{{ $event->triggered_at->format('M d H:i:s') }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20 font-mono font-medium">
                                        {{ $event->event_type->value }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap font-medium {{ $event->severity === 'CRITICAL' ? 'text-rose-400' : 'text-amber-400' }}">
                                    {{ $event->severity }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-white font-medium">{{ $event->user?->name ?? $event->user?->phone_number ?? 'User #'.$event->user_id }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-slate-400">{{ $event->device?->device_name ?? $event->device?->model ?? 'Device #'.$event->device_id }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $event->description ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-slate-400">No security events recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Admin Audit Logs -->
        <section class="rounded-xl bg-slate-800/50 border border-slate-700/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/60">
                <h2 class="font-display font-semibold text-white">Admin Activity Audit Logs</h2>
                <p class="text-xs text-slate-400">Chronological record of administrative operations</p>
            </div>
            <div class="p-5 space-y-2.5">
                @forelse($recentAuditLogs as $log)
                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-700/30 last:border-0">
                        <div class="flex items-center space-x-3">
                            <span class="font-mono text-emerald-400">{{ $log->action }}</span>
                            <span class="text-slate-400">in [{{ $log->module }}]</span>
                            <span class="text-slate-500">by {{ $log->admin?->name ?? 'System' }} ({{ $log->admin?->role->value ?? 'CLI' }})</span>
                        </div>
                        <span class="text-slate-400 font-mono">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 text-center py-2">No admin actions logged yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
