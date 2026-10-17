import React, { useState, useMemo } from 'react';
import {
  Users,
  Shield,
  Smartphone,
  AlertTriangle,
  Radio,
  Clock,
  CheckCircle2,
  XCircle,
  AlertOctagon,
  Calendar,
  RefreshCw,
  TrendingUp,
  Camera,
  Cpu,
  Key,
  Flame,
  ArrowUpRight,
  Filter,
  ExternalLink,
  Lock,
  Unlock,
  Eye,
  Info
} from 'lucide-react';

interface MetricCards {
  users?: {
    total: number;
    active: number;
    pending_otp: number;
    awaiting_activation: number;
    disabled: number;
    new_in_period?: number;
  };
  subscriptions?: {
    active: number;
    expiring: number;
    expired: number;
  };
  devices?: {
    active: number;
    offline: number;
    revoked: number;
    total: number;
  };
  security?: {
    total_security_events: number;
    intruder_alerts: number;
    sim_alerts: number;
    motion_alerts: number;
    pending_commands: number;
    failed_commands: number;
  };
}

interface ChartItem {
  date: string;
  count?: number;
  active_count?: number;
  new_activations?: number;
  total?: number;
  intruder?: number;
  sim?: number;
  motion?: number;
  active_pings?: number;
}

interface RecentIntruderEvent {
  id: number;
  event_uuid: string;
  event_type: string;
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
  occurred_at: string;
  user: { id: number; name: string; phone: string; email: string } | null;
  device: { id: number; name: string; model: string; platform: string } | null;
  has_evidence: boolean;
  evidence_url?: string;
  location?: { latitude: number; longitude: number; accuracy: number } | null;
}

interface RecentSecurityCommand {
  id: number;
  command_uuid: string;
  command_type: string;
  status: 'PENDING' | 'SENT' | 'EXECUTING' | 'COMPLETED' | 'FAILED' | 'EXPIRED';
  created_at: string;
  executed_at?: string;
  user: { id: number; name: string; email: string } | null;
  device: { id: number; name: string; model: string } | null;
  dispatched_by: { id: number; name: string; email: string } | null;
}

interface RecentRegistration {
  id: number;
  name: string;
  email: string;
  phone: string;
  status: string;
  registered_at: string;
}

interface RecentActivation {
  id: number;
  status: string;
  plan_name: string;
  starts_at: string;
  expires_at: string;
  days_remaining: number;
  user: { id: number; name: string; email: string; phone: string } | null;
}

export function AdminDashboard() {
  // Preset filter state
  const [preset, setPreset] = useState<'7d' | '30d' | '90d' | '1y' | 'all'>('30d');
  const [customFrom, setCustomFrom] = useState<string>('2026-08-25');
  const [customTo, setCustomTo] = useState<string>('2026-09-24');
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState<'all' | 'intruders' | 'commands' | 'registrations' | 'subscriptions'>('all');
  const [selectedEvidenceModal, setSelectedEvidenceModal] = useState<RecentIntruderEvent | null>(null);

  // Simulated permission toggles for testing and inspection
  const [roleMode, setRoleMode] = useState<'SUPER_ADMIN' | 'SUPPORT_AGENT' | 'SECURITY_OFFICER'>('SUPER_ADMIN');

  // Realistic mock data reflecting the backend server response
  const rawData = useMemo(() => {
    return {
      cards: {
        users: {
          total: 1248,
          active: 986,
          pending_otp: 142,
          awaiting_activation: 88,
          disabled: 32,
          new_in_period: 184
        },
        subscriptions: {
          active: 914,
          expiring: 49,
          expired: 285
        },
        devices: {
          active: 1102,
          offline: 114,
          revoked: 32,
          total: 1248
        },
        security: {
          total_security_events: 341,
          intruder_alerts: 89,
          sim_alerts: 42,
          motion_alerts: 210,
          pending_commands: 14,
          failed_commands: 3
        }
      },
      charts: {
        user_registrations: [
          { date: '09-18', count: 18 },
          { date: '09-19', count: 24 },
          { date: '09-20', count: 31 },
          { date: '09-21', count: 28 },
          { date: '09-22', count: 42 },
          { date: '09-23', count: 39 },
          { date: '09-24', count: 46 }
        ],
        active_subscriptions: [
          { date: '09-18', active_count: 875, new_activations: 12 },
          { date: '09-19', active_count: 884, new_activations: 15 },
          { date: '09-20', active_count: 892, new_activations: 14 },
          { date: '09-21', active_count: 899, new_activations: 11 },
          { date: '09-22', active_count: 906, new_activations: 16 },
          { date: '09-23', active_count: 910, new_activations: 9 },
          { date: '09-24', active_count: 914, new_activations: 18 }
        ],
        security_events: [
          { date: '09-18', total: 32, intruder: 8, sim: 4, motion: 20 },
          { date: '09-19', total: 44, intruder: 12, sim: 6, motion: 26 },
          { date: '09-20', total: 29, intruder: 7, sim: 2, motion: 20 },
          { date: '09-21', total: 38, intruder: 11, sim: 5, motion: 22 },
          { date: '09-22', total: 56, intruder: 18, sim: 8, motion: 30 },
          { date: '09-23', total: 47, intruder: 14, sim: 7, motion: 26 },
          { date: '09-24', total: 52, intruder: 19, sim: 10, motion: 23 }
        ],
        device_activity: [
          { date: '09-18', active_pings: 1240 },
          { date: '09-19', active_pings: 1310 },
          { date: '09-20', active_pings: 1280 },
          { date: '09-21', active_pings: 1345 },
          { date: '09-22', active_pings: 1420 },
          { date: '09-23', active_pings: 1390 },
          { date: '09-24', active_pings: 1465 }
        ]
      },
      recent_activity: {
        latest_intruder_events: [
          {
            id: 849,
            event_uuid: 'evt-sec-0924-a1',
            event_type: 'INTRUDER_PHOTO',
            severity: 'CRITICAL',
            occurred_at: '2026-09-24T02:04:12Z',
            user: { id: 101, name: 'Valerie Sterling', phone: '+15559876543', email: 'valerie.sterling@enterprise.net' },
            device: { id: 201, name: 'Titan Ultra S24', model: 'Samsung Galaxy S24 Ultra', platform: 'Android 14' },
            has_evidence: true,
            evidence_url: '/admin/evidence/849/stream',
            location: { latitude: 37.7749, longitude: -122.4194, accuracy: 4.1 }
          },
          {
            id: 848,
            event_uuid: 'evt-sec-0924-a2',
            event_type: 'WRONG_PIN_ATTEMPTS',
            severity: 'HIGH',
            occurred_at: '2026-09-24T01:42:08Z',
            user: { id: 104, name: 'Marcus Sterling', phone: '+15553334444', email: 'marcus.s@shield.org' },
            device: { id: 205, name: 'Pixel 8 Pro MDM', model: 'Google Pixel 8 Pro', platform: 'Android 14' },
            has_evidence: true,
            evidence_url: '/admin/evidence/848/stream',
            location: { latitude: 37.7833, longitude: -122.4167, accuracy: 5.4 }
          },
          {
            id: 845,
            event_uuid: 'evt-sec-0923-b9',
            event_type: 'INTRUDER_DETECTED',
            severity: 'HIGH',
            occurred_at: '2026-09-23T23:19:44Z',
            user: { id: 109, name: 'Sarah Connor', phone: '+15557778888', email: 'sarah.c@cyberdyne.corp' },
            device: { id: 211, name: 'Apex Sentinel Phone', model: 'Motorola ThinkPhone', platform: 'Android 13' },
            has_evidence: true,
            evidence_url: '/admin/evidence/845/stream',
            location: { latitude: 34.0522, longitude: -118.2437, accuracy: 3.8 }
          }
        ] as RecentIntruderEvent[],
        latest_security_commands: [
          {
            id: 301,
            command_uuid: 'cmd-mdm-0924-f1',
            command_type: 'FACTORY_RESET',
            status: 'COMPLETED',
            created_at: '2026-09-24T01:58:30Z',
            executed_at: '2026-09-24T01:58:42Z',
            user: { id: 115, name: 'Arthur Pendelton', email: 'arthur.p@vault.net' },
            device: { id: 218, name: 'Field Agent Pixel', model: 'Google Pixel 7' },
            dispatched_by: { id: 1, name: 'Sovereign Admin', email: 'root@sata.security' }
          },
          {
            id: 300,
            command_uuid: 'cmd-mdm-0924-k2',
            command_type: 'KIOSK_LOCK',
            status: 'PENDING',
            created_at: '2026-09-24T02:08:15Z',
            user: { id: 101, name: 'Valerie Sterling', email: 'valerie.sterling@enterprise.net' },
            device: { id: 201, name: 'Titan Ultra S24', model: 'Samsung Galaxy S24 Ultra' },
            dispatched_by: { id: 2, name: 'Security Director', email: 'director@sata.security' }
          },
          {
            id: 298,
            command_uuid: 'cmd-mdm-0923-w9',
            command_type: 'WIPE_DATA',
            status: 'FAILED',
            created_at: '2026-09-23T22:11:00Z',
            user: { id: 130, name: 'Dmitri Volkov', email: 'dmitri@nordic.se' },
            device: { id: 231, name: 'Supervised iPhone', model: 'iPhone 14' },
            dispatched_by: { id: 1, name: 'Sovereign Admin', email: 'root@sata.security' }
          }
        ] as RecentSecurityCommand[],
        latest_registrations: [
          { id: 1248, name: 'Elena Rostova', email: 'elena.rostova@shield.net', phone: '+14155550101', status: 'ACTIVE', registered_at: '2026-09-24T02:01:10Z' },
          { id: 1247, name: 'Tariq Al-Mansoor', email: 'tariq@gulf.ae', phone: '+971501234567', status: 'AWAITING_ACTIVATION', registered_at: '2026-09-24T01:34:22Z' },
          { id: 1246, name: 'Chloe Dubois', email: 'chloe.dubois@paris.fr', phone: '+33612345678', status: 'PENDING', registered_at: '2026-09-24T00:52:19Z' },
          { id: 1245, name: 'Klaus Richter', email: 'klaus.richter@berlin.de', phone: '+491701234567', status: 'ACTIVE', registered_at: '2026-09-23T23:18:40Z' }
        ] as RecentRegistration[],
        latest_subscription_activations: [
          { id: 914, plan_name: 'Apex Sentinel Armor (Annual)', status: 'ACTIVE', starts_at: '2026-09-24T02:02:00Z', expires_at: '2027-09-24T23:59:59Z', days_remaining: 365, user: { id: 1248, name: 'Elena Rostova', email: 'elena.rostova@shield.net', phone: '+14155550101' } },
          { id: 913, plan_name: 'Titan Fleet Defense (2-Year)', status: 'ACTIVE', starts_at: '2026-09-24T00:15:30Z', expires_at: '2028-09-24T23:59:59Z', days_remaining: 730, user: { id: 1239, name: 'Liam Henderson', email: 'liam@apex.io', phone: '+12125550199' } },
          { id: 912, plan_name: 'Apex Sentinel Armor (Monthly)', status: 'EXPIRING', starts_at: '2026-08-25T10:00:00Z', expires_at: '2026-09-25T10:00:00Z', days_remaining: 1, user: { id: 1210, name: 'Daria Thorne', email: 'daria.thorne@shield.net', phone: '+14155550103' } }
        ] as RecentActivation[]
      }
    };
  }, []);

  // Filter permission based on simulated roleMode
  const visibleCards = useMemo(() => {
    const cards: MetricCards = {};
    if (roleMode === 'SUPER_ADMIN' || roleMode === 'SUPPORT_AGENT') {
      cards.users = rawData.cards.users;
    }
    if (roleMode === 'SUPER_ADMIN') {
      cards.subscriptions = rawData.cards.subscriptions;
      cards.devices = rawData.cards.devices;
    }
    if (roleMode === 'SUPER_ADMIN' || roleMode === 'SECURITY_OFFICER') {
      cards.security = rawData.cards.security;
      if (roleMode === 'SECURITY_OFFICER') {
        cards.devices = rawData.cards.devices;
      }
    }
    return cards;
  }, [roleMode, rawData]);

  const handleRefresh = () => {
    setIsRefreshing(true);
    setTimeout(() => {
      setIsRefreshing(false);
    }, 600);
  };

  return (
    <div className="space-y-8">
      {/* 1. Header & Live Telemetry Controls */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-xl relative overflow-hidden">
        <div className="absolute top-0 right-0 w-96 h-96 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none" />
        
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 relative z-10">
          <div>
            <div className="flex items-center gap-3">
              <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                TELEMETRY LIVE
              </span>
              <span className="text-xs text-slate-400 font-mono">NODE: SATA-APEX-CENTRAL-01</span>
            </div>
            <h1 className="text-2xl font-bold text-white mt-2 tracking-tight">Executive Security Command Dashboard</h1>
            <p className="text-sm text-slate-400 mt-1">
              Consolidated enterprise monitoring, user lifecycle auditing, hardware telemetry, and automated security incident intelligence.
            </p>
          </div>

          {/* Date Range & Role Controls */}
          <div className="flex flex-wrap items-center gap-3">
            {/* Permission Role Simulator */}
            <div className="flex items-center gap-2 bg-slate-800/80 border border-slate-700 rounded-lg p-1">
              <span className="text-xs text-slate-400 pl-2 font-medium">Role:</span>
              <select
                value={roleMode}
                onChange={(e) => setRoleMode(e.target.value as any)}
                className="bg-slate-900 text-slate-200 text-xs font-semibold rounded px-2.5 py-1.5 border border-slate-700 focus:outline-none focus:border-indigo-500"
              >
                <option value="SUPER_ADMIN">Super Administrator (All Access)</option>
                <option value="SUPPORT_AGENT">Support Agent (Users View Only)</option>
                <option value="SECURITY_OFFICER">Security Officer (Security & Devices)</option>
              </select>
            </div>

            {/* Quick Presets */}
            <div className="flex items-center bg-slate-800/80 border border-slate-700 rounded-lg p-1">
              {(['7d', '30d', '90d', '1y', 'all'] as const).map((p) => (
                <button
                  key={p}
                  onClick={() => setPreset(p)}
                  className={`px-3 py-1.5 text-xs font-medium rounded transition-all ${
                    preset === p
                      ? 'bg-indigo-600 text-white shadow-sm font-semibold'
                      : 'text-slate-400 hover:text-white hover:bg-slate-700/50'
                  }`}
                >
                  {p.toUpperCase()}
                </button>
              ))}
            </div>

            {/* Refresh Button */}
            <button
              onClick={handleRefresh}
              disabled={isRefreshing}
              className="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-xs font-semibold flex items-center gap-2 transition-colors disabled:opacity-50"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin text-indigo-400' : ''}`} />
              <span>Refresh</span>
            </button>
          </div>
        </div>

        {/* Date Filter Bar */}
        <div className="mt-5 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-4 text-xs">
          <div className="flex items-center gap-3 text-slate-400">
            <Calendar className="w-4 h-4 text-indigo-400" />
            <span>Active Reporting Window:</span>
            <span className="font-mono text-slate-200 font-semibold bg-slate-800/70 px-2 py-0.5 rounded border border-slate-700">
              {preset === '7d' ? 'Last 7 Days' : preset === '30d' ? 'Last 30 Days' : preset === '90d' ? 'Last 90 Days' : preset === '1y' ? 'Past Year' : 'All Historical Data'}
            </span>
          </div>

          <div className="flex items-center gap-3">
            <span className="text-slate-400">Custom Range:</span>
            <input
              type="date"
              value={customFrom}
              onChange={(e) => setCustomFrom(e.target.value)}
              className="bg-slate-950 border border-slate-800 text-slate-300 px-2 py-1 rounded text-xs focus:outline-none focus:border-indigo-500"
            />
            <span className="text-slate-500">to</span>
            <input
              type="date"
              value={customTo}
              onChange={(e) => setCustomTo(e.target.value)}
              className="bg-slate-950 border border-slate-800 text-slate-300 px-2 py-1 rounded text-xs focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>
      </div>

      {/* 2. Primary Metric Cards Grid */}
      <div className="space-y-6">
        {/* USERS FLEET CARDS */}
        {visibleCards.users && (
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Users className="w-4 h-4 text-indigo-400" />
                <h2 className="text-sm font-bold text-slate-200 uppercase tracking-wider">User Identity Fleet (5 Metrics)</h2>
              </div>
              <span className="text-xs text-slate-400">Server Query: `users.view`</span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
              {/* Total Users */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm hover:border-slate-700 transition-all">
                <div className="flex items-center justify-between text-slate-400 mb-2">
                  <span className="text-xs font-semibold uppercase tracking-wider">Total Users</span>
                  <div className="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400">
                    <Users className="w-4 h-4" />
                  </div>
                </div>
                <div className="text-2xl font-bold text-white tracking-tight">{visibleCards.users.total.toLocaleString()}</div>
                <div className="flex items-center gap-1.5 mt-2 text-xs text-emerald-400">
                  <TrendingUp className="w-3.5 h-3.5" />
                  <span>+{visibleCards.users.new_in_period} in selected window</span>
                </div>
              </div>

              {/* Active Users */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm hover:border-emerald-500/30 transition-all">
                <div className="flex items-center justify-between text-slate-400 mb-2">
                  <span className="text-xs font-semibold uppercase tracking-wider text-emerald-400">Active Users</span>
                  <div className="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                    <CheckCircle2 className="w-4 h-4" />
                  </div>
                </div>
                <div className="text-2xl font-bold text-emerald-400 tracking-tight">{visibleCards.users.active.toLocaleString()}</div>
                <p className="text-xs text-slate-400 mt-2">Verified and operational</p>
              </div>

              {/* Pending OTP */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm hover:border-amber-500/30 transition-all">
                <div className="flex items-center justify-between text-slate-400 mb-2">
                  <span className="text-xs font-semibold uppercase tracking-wider text-amber-400">Pending OTP</span>
                  <div className="p-1.5 rounded-lg bg-amber-500/10 text-amber-400">
                    <Clock className="w-4 h-4" />
                  </div>
                </div>
                <div className="text-2xl font-bold text-amber-400 tracking-tight">{visibleCards.users.pending_otp.toLocaleString()}</div>
                <p className="text-xs text-slate-400 mt-2">Awaiting phone SMS verification</p>
              </div>

              {/* Awaiting Activation */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm hover:border-blue-500/30 transition-all">
                <div className="flex items-center justify-between text-slate-400 mb-2">
                  <span className="text-xs font-semibold uppercase tracking-wider text-blue-400">Awaiting Activation</span>
                  <div className="p-1.5 rounded-lg bg-blue-500/10 text-blue-400">
                    <Key className="w-4 h-4" />
                  </div>
                </div>
                <div className="text-2xl font-bold text-blue-400 tracking-tight">{visibleCards.users.awaiting_activation.toLocaleString()}</div>
                <p className="text-xs text-slate-400 mt-2">Verified phone; awaiting card code</p>
              </div>

              {/* Disabled Users */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm hover:border-rose-500/30 transition-all">
                <div className="flex items-center justify-between text-slate-400 mb-2">
                  <span className="text-xs font-semibold uppercase tracking-wider text-rose-400">Disabled Users</span>
                  <div className="p-1.5 rounded-lg bg-rose-500/10 text-rose-400">
                    <XCircle className="w-4 h-4" />
                  </div>
                </div>
                <div className="text-2xl font-bold text-rose-400 tracking-tight">{visibleCards.users.disabled.toLocaleString()}</div>
                <p className="text-xs text-slate-400 mt-2">Administratively suspended</p>
              </div>
            </div>
          </div>
        )}

        {/* SUBSCRIPTIONS & DEVICES ROW */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* SUBSCRIPTIONS (3 Metrics) */}
          {visibleCards.subscriptions && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Shield className="w-4 h-4 text-emerald-400" />
                  <h3 className="text-sm font-bold text-slate-200 uppercase tracking-wider">Subscriptions (3 Metrics)</h3>
                </div>
                <span className="text-xs text-slate-400">Server Query: `subscriptions.view`</span>
              </div>

              <div className="grid grid-cols-3 gap-3">
                {/* Active */}
                <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3.5 text-center">
                  <span className="text-xs font-semibold text-emerald-400 uppercase tracking-wide">Active</span>
                  <div className="text-2xl font-bold text-emerald-400 mt-1">{visibleCards.subscriptions.active}</div>
                  <span className="text-[11px] text-slate-400 mt-1 block">Valid license key</span>
                </div>

                {/* Expiring */}
                <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3.5 text-center">
                  <span className="text-xs font-semibold text-amber-400 uppercase tracking-wide">Expiring</span>
                  <div className="text-2xl font-bold text-amber-400 mt-1">{visibleCards.subscriptions.expiring}</div>
                  <span className="text-[11px] text-slate-400 mt-1 block">Within ≤ 30 days</span>
                </div>

                {/* Expired */}
                <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3.5 text-center">
                  <span className="text-xs font-semibold text-slate-400 uppercase tracking-wide">Expired</span>
                  <div className="text-2xl font-bold text-slate-300 mt-1">{visibleCards.subscriptions.expired}</div>
                  <span className="text-[11px] text-slate-500 mt-1 block">Lapsed protection</span>
                </div>
              </div>
            </div>
          )}

          {/* DEVICES (3 Metrics) */}
          {visibleCards.devices && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Smartphone className="w-4 h-4 text-cyan-400" />
                  <h3 className="text-sm font-bold text-slate-200 uppercase tracking-wider">Hardware Devices (3 Metrics)</h3>
                </div>
                <span className="text-xs text-slate-400">Total: {visibleCards.devices.total}</span>
              </div>

              <div className="grid grid-cols-3 gap-3">
                {/* Active */}
                <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3.5 text-center">
                  <span className="text-xs font-semibold text-cyan-400 uppercase tracking-wide flex items-center justify-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-ping" />
                    Active
                  </span>
                  <div className="text-2xl font-bold text-cyan-400 mt-1">{visibleCards.devices.active}</div>
                  <span className="text-[11px] text-slate-400 mt-1 block">Online &lt; 24h</span>
                </div>

                {/* Offline */}
                <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3.5 text-center">
                  <span className="text-xs font-semibold text-amber-400 uppercase tracking-wide">Offline</span>
                  <div className="text-2xl font-bold text-amber-400 mt-1">{visibleCards.devices.offline}</div>
                  <span className="text-[11px] text-slate-400 mt-1 block">No ping &gt; 24h</span>
                </div>

                {/* Revoked */}
                <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3.5 text-center">
                  <span className="text-xs font-semibold text-rose-400 uppercase tracking-wide">Revoked</span>
                  <div className="text-2xl font-bold text-rose-400 mt-1">{visibleCards.devices.revoked}</div>
                  <span className="text-[11px] text-slate-500 mt-1 block">Severed & decommissioned</span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* SECURITY CARDS (6 Metrics) */}
        {visibleCards.security && (
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-rose-400" />
                <h2 className="text-sm font-bold text-slate-200 uppercase tracking-wider">Security Telemetry & Command Queue (6 Metrics)</h2>
              </div>
              <span className="text-xs text-slate-400">Server Query: `security_events.view` + `security_commands.view`</span>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
              {/* Total Security Events */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5">
                <span className="text-xs font-semibold text-slate-400 uppercase block truncate">Total Security Events</span>
                <div className="text-2xl font-bold text-white mt-1">{visibleCards.security.total_security_events}</div>
                <span className="text-[11px] text-slate-500 mt-1 block">Total incident log</span>
              </div>

              {/* Intruder Alerts */}
              <div className="bg-slate-900 border border-rose-900/40 rounded-xl p-3.5 bg-rose-950/10">
                <span className="text-xs font-semibold text-rose-400 uppercase block truncate flex items-center gap-1.5">
                  <Camera className="w-3.5 h-3.5" />
                  Intruder Alerts
                </span>
                <div className="text-2xl font-bold text-rose-400 mt-1">{visibleCards.security.intruder_alerts}</div>
                <span className="text-[11px] text-rose-300/70 mt-1 block">Front cam captures</span>
              </div>

              {/* SIM Alerts */}
              <div className="bg-slate-900 border border-amber-900/40 rounded-xl p-3.5 bg-amber-950/10">
                <span className="text-xs font-semibold text-amber-400 uppercase block truncate flex items-center gap-1.5">
                  <Radio className="w-3.5 h-3.5" />
                  SIM Alerts
                </span>
                <div className="text-2xl font-bold text-amber-400 mt-1">{visibleCards.security.sim_alerts}</div>
                <span className="text-[11px] text-amber-300/70 mt-1 block">Unauthorized swap</span>
              </div>

              {/* Motion Alerts */}
              <div className="bg-slate-900 border border-purple-900/40 rounded-xl p-3.5 bg-purple-950/10">
                <span className="text-xs font-semibold text-purple-400 uppercase block truncate flex items-center gap-1.5">
                  <Flame className="w-3.5 h-3.5" />
                  Motion Alerts
                </span>
                <div className="text-2xl font-bold text-purple-400 mt-1">{visibleCards.security.motion_alerts}</div>
                <span className="text-[11px] text-purple-300/70 mt-1 block">Anti-pickpocket sensor</span>
              </div>

              {/* Pending Commands */}
              <div className="bg-slate-900 border border-blue-900/40 rounded-xl p-3.5 bg-blue-950/10">
                <span className="text-xs font-semibold text-blue-400 uppercase block truncate flex items-center gap-1.5">
                  <Clock className="w-3.5 h-3.5" />
                  Pending Commands
                </span>
                <div className="text-2xl font-bold text-blue-400 mt-1">{visibleCards.security.pending_commands}</div>
                <span className="text-[11px] text-blue-300/70 mt-1 block">MDM queue awaiting ACK</span>
              </div>

              {/* Failed Commands */}
              <div className="bg-slate-900 border border-red-900/40 rounded-xl p-3.5 bg-red-950/10">
                <span className="text-xs font-semibold text-red-400 uppercase block truncate flex items-center gap-1.5">
                  <AlertOctagon className="w-3.5 h-3.5" />
                  Failed Commands
                </span>
                <div className="text-2xl font-bold text-red-400 mt-1">{visibleCards.security.failed_commands}</div>
                <span className="text-[11px] text-red-300/70 mt-1 block">Device severed / error</span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* 3. Charts Section (4 Required Interactive Charts) */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <TrendingUp className="w-4 h-4 text-indigo-400" />
            <h2 className="text-sm font-bold text-slate-200 uppercase tracking-wider">Analytics & Timeseries Trends (4 Core Charts)</h2>
          </div>
          <span className="text-xs text-slate-400">Indexed timeseries aggregation via SQL `GROUP BY date_val`</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Chart 1: User Registrations */}
          <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold text-white">User Registrations Trend</h3>
                <p className="text-xs text-slate-400 mt-0.5">Daily onboarded accounts via mobile OTP</p>
              </div>
              <span className="text-xs font-mono font-bold text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">
                {rawData.charts.user_registrations.reduce((a, b) => a + b.count, 0)} Total
              </span>
            </div>

            {/* Custom SVG Bar Chart */}
            <div className="h-44 flex items-end justify-between gap-3 pt-4 px-2 border-b border-slate-800">
              {rawData.charts.user_registrations.map((item, idx) => {
                const heightPercent = Math.min(100, Math.round((item.count / 50) * 100));
                return (
                  <div key={idx} className="flex-1 flex flex-col items-center gap-2 h-full justify-end group relative">
                    {/* Tooltip */}
                    <div className="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-800 text-white text-[10px] font-mono px-2 py-0.5 rounded shadow pointer-events-none whitespace-nowrap z-20">
                      {item.count} users
                    </div>
                    <div
                      style={{ height: `${heightPercent}%` }}
                      className="w-full max-w-[28px] bg-gradient-to-t from-indigo-600 to-indigo-400 rounded-t-sm group-hover:brightness-125 transition-all"
                    />
                    <span className="text-[10px] font-mono text-slate-400 mt-1">{item.date}</span>
                  </div>
                );
              })}
            </div>
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span>Fast server index: `idx_users_status_created_at`</span>
              <span className="text-indigo-400 font-medium">Avg: ~32/day</span>
            </div>
          </div>

          {/* Chart 2: Active Subscriptions Growth */}
          <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold text-white">Active Subscriptions Growth</h3>
                <p className="text-xs text-slate-400 mt-0.5">Active licensed subscriptions curve</p>
              </div>
              <span className="text-xs font-mono font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                {rawData.cards.subscriptions.active} Active Now
              </span>
            </div>

            {/* Custom SVG Area Sparkline */}
            <div className="h-44 pt-4 px-2 border-b border-slate-800 flex flex-col justify-end">
              <svg viewBox="0 0 400 120" className="w-full h-28 overflow-visible">
                <defs>
                  <linearGradient id="emeraldGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#10b981" stopOpacity="0.4" />
                    <stop offset="100%" stopColor="#10b981" stopOpacity="0.0" />
                  </linearGradient>
                </defs>
                <path
                  d="M 0,90 L 60,82 L 120,74 L 180,68 L 240,55 L 300,45 L 360,25 L 360,120 L 0,120 Z"
                  fill="url(#emeraldGrad)"
                />
                <polyline
                  fill="none"
                  stroke="#10b981"
                  strokeWidth="3"
                  points="0,90 60,82 120,74 180,68 240,55 300,45 360,25"
                />
                {[
                  { x: 0, y: 90 }, { x: 60, y: 82 }, { x: 120, y: 74 },
                  { x: 180, y: 68 }, { x: 240, y: 55 }, { x: 300, y: 45 }, { x: 360, y: 25 }
                ].map((pt, i) => (
                  <circle key={i} cx={pt.x} cy={pt.y} r="4" className="fill-slate-900 stroke-emerald-400 stroke-2" />
                ))}
              </svg>
              <div className="flex justify-between text-[10px] font-mono text-slate-400 mt-2">
                {rawData.charts.active_subscriptions.map((s, i) => (
                  <span key={i}>{s.date}</span>
                ))}
              </div>
            </div>
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span>Eager loaded license plan models without N+1</span>
              <span className="text-emerald-400 font-medium">+39 net growth</span>
            </div>
          </div>

          {/* Chart 3: Security Events Multi-Series */}
          <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold text-white">Security Incident Velocity</h3>
                <p className="text-xs text-slate-400 mt-0.5">Breakdown by Intruder, SIM swap, and Motion trigger</p>
              </div>
              <div className="flex items-center gap-3 text-xs">
                <span className="flex items-center gap-1 text-rose-400"><span className="w-2 h-2 rounded-full bg-rose-500" /> Intruder</span>
                <span className="flex items-center gap-1 text-amber-400"><span className="w-2 h-2 rounded-full bg-amber-500" /> SIM</span>
                <span className="flex items-center gap-1 text-purple-400"><span className="w-2 h-2 rounded-full bg-purple-500" /> Motion</span>
              </div>
            </div>

            {/* Stacked Bars */}
            <div className="h-44 flex items-end justify-between gap-3 pt-4 px-2 border-b border-slate-800">
              {rawData.charts.security_events.map((item, idx) => {
                const totalH = Math.min(100, Math.round((item.total / 60) * 100));
                const intruderPct = Math.round((item.intruder / item.total) * 100);
                const simPct = Math.round((item.sim / item.total) * 100);
                const motionPct = 100 - intruderPct - simPct;

                return (
                  <div key={idx} className="flex-1 flex flex-col items-center gap-1 h-full justify-end group relative">
                    <div className="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-800 text-white text-[10px] font-mono px-2 py-0.5 rounded shadow pointer-events-none whitespace-nowrap z-20">
                      Total: {item.total} (Intruder: {item.intruder})
                    </div>
                    <div style={{ height: `${totalH}%` }} className="w-full max-w-[28px] rounded-t-sm flex flex-col overflow-hidden">
                      <div style={{ height: `${intruderPct}%` }} className="bg-rose-500" />
                      <div style={{ height: `${simPct}%` }} className="bg-amber-500" />
                      <div style={{ height: `${motionPct}%` }} className="bg-purple-500" />
                    </div>
                    <span className="text-[10px] font-mono text-slate-400 mt-1">{item.date}</span>
                  </div>
                );
              })}
            </div>
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span>Server-side aggregated SQL `CASE WHEN`</span>
              <span className="text-rose-400 font-medium">Critical events prioritized</span>
            </div>
          </div>

          {/* Chart 4: Device Activity / Telemetry Pings */}
          <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold text-white">Hardware Device Activity</h3>
                <p className="text-xs text-slate-400 mt-0.5">Encrypted telemetry heartbeats & GPS check-ins</p>
              </div>
              <span className="text-xs font-mono font-bold text-cyan-400 bg-cyan-500/10 px-2 py-0.5 rounded border border-cyan-500/20">
                1,465 Pings Today
              </span>
            </div>

            {/* Custom SVG Line Chart */}
            <div className="h-44 pt-4 px-2 border-b border-slate-800 flex flex-col justify-end">
              <svg viewBox="0 0 400 120" className="w-full h-28 overflow-visible">
                <polyline
                  fill="none"
                  stroke="#06b6d4"
                  strokeWidth="3"
                  strokeDasharray="4 2"
                  points="0,80 60,65 120,70 180,50 240,35 300,42 360,20"
                />
                {[
                  { x: 0, y: 80 }, { x: 60, y: 65 }, { x: 120, y: 70 },
                  { x: 180, y: 50 }, { x: 240, y: 35 }, { x: 300, y: 42 }, { x: 360, y: 20 }
                ].map((pt, i) => (
                  <circle key={i} cx={pt.x} cy={pt.y} r="4" className="fill-cyan-400 stroke-slate-900 stroke-2" />
                ))}
              </svg>
              <div className="flex justify-between text-[10px] font-mono text-slate-400 mt-2">
                {rawData.charts.device_activity.map((d, i) => (
                  <span key={i}>{d.date}</span>
                ))}
              </div>
            </div>
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span>Indexed with `idx_devices_status_last_seen`</span>
              <span className="text-cyan-400 font-medium">99.8% fleet uptime</span>
            </div>
          </div>
        </div>
      </div>

      {/* 4. Recent Activity Feed (4 Streams) */}
      <div className="space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-indigo-400" />
            <h2 className="text-sm font-bold text-slate-200 uppercase tracking-wider">Live System Activity Feed (4 Streams)</h2>
          </div>

          {/* Activity Category Filter Tabs */}
          <div className="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-1 text-xs">
            <button
              onClick={() => setActiveTab('all')}
              className={`px-3 py-1 rounded transition-colors ${activeTab === 'all' ? 'bg-indigo-600 text-white font-medium' : 'text-slate-400 hover:text-white'}`}
            >
              All Streams
            </button>
            <button
              onClick={() => setActiveTab('intruders')}
              className={`px-3 py-1 rounded transition-colors ${activeTab === 'intruders' ? 'bg-indigo-600 text-white font-medium' : 'text-slate-400 hover:text-white'}`}
            >
              Intruders
            </button>
            <button
              onClick={() => setActiveTab('commands')}
              className={`px-3 py-1 rounded transition-colors ${activeTab === 'commands' ? 'bg-indigo-600 text-white font-medium' : 'text-slate-400 hover:text-white'}`}
            >
              Commands
            </button>
            <button
              onClick={() => setActiveTab('registrations')}
              className={`px-3 py-1 rounded transition-colors ${activeTab === 'registrations' ? 'bg-indigo-600 text-white font-medium' : 'text-slate-400 hover:text-white'}`}
            >
              Registrations
            </button>
            <button
              onClick={() => setActiveTab('subscriptions')}
              className={`px-3 py-1 rounded transition-colors ${activeTab === 'subscriptions' ? 'bg-indigo-600 text-white font-medium' : 'text-slate-400 hover:text-white'}`}
            >
              Activations
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Stream 1: Latest Intruder Events */}
          {(activeTab === 'all' || activeTab === 'intruders') && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-3">
              <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                <div className="flex items-center gap-2 text-rose-400 font-semibold text-xs uppercase tracking-wide">
                  <Camera className="w-4 h-4" />
                  <span>Latest Intruder Events</span>
                </div>
                <span className="text-[11px] text-slate-500 font-mono">Eager loaded evidence</span>
              </div>

              <div className="space-y-3">
                {rawData.recent_activity.latest_intruder_events.map((evt) => (
                  <div
                    key={evt.id}
                    className="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between gap-3 hover:border-slate-700 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      {/* Photo Thumbnail */}
                      <button
                        onClick={() => setSelectedEvidenceModal(evt)}
                        className="w-12 h-12 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden relative group flex-shrink-0"
                      >
                        <img
                          src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80"
                          alt="Intruder Snapshot"
                          className="w-full h-full object-cover group-hover:scale-110 transition-transform"
                        />
                        <div className="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                          <Eye className="w-3.5 h-3.5 text-white" />
                        </div>
                      </button>

                      <div>
                        <div className="flex items-center gap-2">
                          <span className="px-2 py-0.5 bg-rose-500/10 text-rose-400 border border-rose-500/20 text-[10px] font-bold rounded">
                            {evt.event_type}
                          </span>
                          <span className="text-xs font-semibold text-slate-200">{evt.user?.name}</span>
                        </div>
                        <div className="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                          <span>{evt.device?.model}</span>
                          <span>•</span>
                          <span className="font-mono text-[10px]">{evt.location?.latitude.toFixed(4)}, {evt.location?.longitude.toFixed(4)}</span>
                        </div>
                      </div>
                    </div>

                    <div className="text-right">
                      <span className="text-[11px] text-slate-400 font-mono block">{new Date(evt.occurred_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                      <button
                        onClick={() => setSelectedEvidenceModal(evt)}
                        className="text-[11px] text-indigo-400 hover:text-indigo-300 font-medium inline-flex items-center gap-1 mt-1"
                      >
                        Inspect <ExternalLink className="w-3 h-3" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Stream 2: Latest Security Commands */}
          {(activeTab === 'all' || activeTab === 'commands') && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-3">
              <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                <div className="flex items-center gap-2 text-blue-400 font-semibold text-xs uppercase tracking-wide">
                  <Key className="w-4 h-4" />
                  <span>Latest Security Commands</span>
                </div>
                <span className="text-[11px] text-slate-500 font-mono">MDM / FCM Push</span>
              </div>

              <div className="space-y-3">
                {rawData.recent_activity.latest_security_commands.map((cmd) => (
                  <div
                    key={cmd.id}
                    className="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between gap-3 hover:border-slate-700 transition-colors"
                  >
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 text-[10px] font-bold rounded font-mono">
                          {cmd.command_type}
                        </span>
                        <span className={`px-2 py-0.5 text-[10px] font-bold rounded ${
                          cmd.status === 'COMPLETED' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                          cmd.status === 'PENDING' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                          'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                        }`}>
                          {cmd.status}
                        </span>
                      </div>
                      <div className="text-[11px] text-slate-400 mt-1">
                        Device: <span className="text-slate-200">{cmd.device?.model}</span> (User: {cmd.user?.name})
                      </div>
                    </div>

                    <div className="text-right">
                      <span className="text-[11px] text-slate-400 font-mono block">
                        {new Date(cmd.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                      <span className="text-[10px] text-slate-500 block mt-0.5">
                        Admin: {cmd.dispatched_by?.name}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Stream 3: Latest Registrations */}
          {(activeTab === 'all' || activeTab === 'registrations') && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-3">
              <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                <div className="flex items-center gap-2 text-indigo-400 font-semibold text-xs uppercase tracking-wide">
                  <Users className="w-4 h-4" />
                  <span>Latest Registrations</span>
                </div>
                <span className="text-[11px] text-slate-500 font-mono">Mobile verified</span>
              </div>

              <div className="space-y-3">
                {rawData.recent_activity.latest_registrations.map((user) => (
                  <div
                    key={user.id}
                    className="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between gap-3 hover:border-slate-700 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-9 h-9 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center font-bold text-indigo-400 text-xs">
                        {user.name.split(' ').map(n => n[0]).join('')}
                      </div>
                      <div>
                        <div className="text-xs font-semibold text-white">{user.name}</div>
                        <div className="text-[11px] text-slate-400 font-mono">{user.phone} • {user.email}</div>
                      </div>
                    </div>

                    <div className="text-right">
                      <span className={`px-2 py-0.5 text-[10px] font-bold rounded ${
                        user.status === 'ACTIVE' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                        user.status === 'AWAITING_ACTIVATION' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' :
                        'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                      }`}>
                        {user.status}
                      </span>
                      <span className="text-[10px] text-slate-500 font-mono block mt-1">
                        {new Date(user.registered_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Stream 4: Latest Subscription Activations */}
          {(activeTab === 'all' || activeTab === 'subscriptions') && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm space-y-3">
              <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                <div className="flex items-center gap-2 text-emerald-400 font-semibold text-xs uppercase tracking-wide">
                  <Shield className="w-4 h-4" />
                  <span>Latest Subscription Activations</span>
                </div>
                <span className="text-[11px] text-slate-500 font-mono">License key verified</span>
              </div>

              <div className="space-y-3">
                {rawData.recent_activity.latest_subscription_activations.map((sub) => (
                  <div
                    key={sub.id}
                    className="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between gap-3 hover:border-slate-700 transition-colors"
                  >
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-white">{sub.plan_name}</span>
                        <span className={`px-2 py-0.5 text-[10px] font-bold rounded ${
                          sub.status === 'ACTIVE' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                        }`}>
                          {sub.status}
                        </span>
                      </div>
                      <div className="text-[11px] text-slate-400 mt-1">
                        User: <span className="text-slate-200">{sub.user?.name}</span> ({sub.user?.phone})
                      </div>
                    </div>

                    <div className="text-right">
                      <span className="text-xs font-bold font-mono text-emerald-400 block">{sub.days_remaining}d remaining</span>
                      <span className="text-[10px] text-slate-500 font-mono block mt-0.5">
                        Expires: {new Date(sub.expires_at).toLocaleDateString()}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* 5. Intruder Evidence Preview Modal */}
      {selectedEvidenceModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2 text-rose-400 font-bold text-sm">
                <Camera className="w-5 h-5" />
                <span>Intruder Photo Evidence #{selectedEvidenceModal.id}</span>
              </div>
              <button
                onClick={() => setSelectedEvidenceModal(null)}
                className="text-slate-400 hover:text-white p-1 rounded-lg"
              >
                <XCircle className="w-5 h-5" />
              </button>
            </div>

            <div className="rounded-xl overflow-hidden border border-slate-800 bg-slate-950 aspect-video relative flex items-center justify-center">
              <img
                src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=800&auto=format&fit=crop&q=80"
                alt="Enlarged Intruder Snapshot"
                className="w-full h-full object-cover"
              />
              <div className="absolute bottom-2 left-2 bg-black/70 backdrop-blur-md px-2.5 py-1 rounded text-[11px] font-mono text-white border border-white/10">
                {selectedEvidenceModal.event_type} • LAT: {selectedEvidenceModal.location?.latitude.toFixed(5)}, LNG: {selectedEvidenceModal.location?.longitude.toFixed(5)}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div className="bg-slate-950 p-3 rounded-lg border border-slate-800">
                <span className="text-slate-400 block text-[11px]">Victim Account</span>
                <span className="font-semibold text-white block mt-0.5">{selectedEvidenceModal.user?.name}</span>
                <span className="text-slate-400 font-mono text-[11px]">{selectedEvidenceModal.user?.phone}</span>
              </div>
              <div className="bg-slate-950 p-3 rounded-lg border border-slate-800">
                <span className="text-slate-400 block text-[11px]">Target Device</span>
                <span className="font-semibold text-white block mt-0.5">{selectedEvidenceModal.device?.model}</span>
                <span className="text-slate-400 font-mono text-[11px]">{selectedEvidenceModal.device?.platform}</span>
              </div>
            </div>

            <div className="pt-2 flex justify-end gap-3">
              <button
                onClick={() => setSelectedEvidenceModal(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-lg text-xs"
              >
                Close Preview
              </button>
              <a
                href={`https://maps.google.com/?q=${selectedEvidenceModal.location?.latitude},${selectedEvidenceModal.location?.longitude}`}
                target="_blank"
                rel="noreferrer"
                className="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-semibold rounded-lg text-xs flex items-center gap-1.5 shadow-lg shadow-rose-600/20"
              >
                <ExternalLink className="w-3.5 h-3.5" />
                Open GPS in Google Maps
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
