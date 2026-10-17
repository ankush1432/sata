import React, { useState, useEffect } from 'react';
import {
  ShieldAlert,
  User as UserIcon,
  Smartphone,
  CreditCard,
  MapPin,
  Camera,
  Users,
  Terminal,
  Bell,
  CheckCircle2,
  AlertTriangle,
  Clock,
  ExternalLink,
  Copy,
  Check,
  RotateCcw,
  Lock,
  Unlock,
  Trash2,
  ChevronLeft,
  ChevronRight,
  Eye,
  ShieldCheck,
  Radio,
  FileText,
  KeyRound,
  AlertCircle
} from 'lucide-react';

export type UserStatusType = 'ACTIVE' | 'SUSPENDED' | 'PENDING_ACTIVATION' | 'INACTIVE';
export type SubscriptionStatusType = 'ACTIVE' | 'EXPIRED' | 'CANCELLED' | 'INACTIVE';
export type DeviceStatusType = 'ACTIVE' | 'REVOKED' | 'UNREGISTERED';
export type CommandType = 'FACTORY_RESET' | 'KIOSK_LOCK' | 'KIOSK_UNLOCK' | 'WIPE_DATA';

interface UserDetail {
  id: number;
  uuid: string;
  name: string;
  phone: string;
  email: string;
  status: UserStatusType;
  created_at: string;
}

interface SubscriptionDetail {
  plan: string;
  plan_code?: string;
  status: SubscriptionStatusType;
  activated_at: string;
  expires_at: string;
  is_active: boolean;
}

interface DeviceDetail {
  device_id: string;
  id: number;
  model: string;
  device_name: string;
  platform: 'ANDROID' | 'IOS';
  os_version: string;
  app_version: string;
  device_management_mode: string;
  status: DeviceStatusType;
  last_seen: string;
  is_protection_enabled: boolean;
  all_devices_count?: number;
}

interface LiveLocationDetail {
  latitude: number | null;
  longitude: number | null;
  accuracy: number | null;
  last_updated: string | null;
  map_preview: string | null;
  map_url: string | null;
  open_map_button: string | null;
  short_location_url: string | null;
  short_url: string | null;
  copy_short_url: string | null;
  has_active_session?: boolean;
}

interface LatestIntruderDetail {
  event_id: number;
  event_uuid: string;
  event_type: string;
  event_time: string;
  photo_preview: string | null;
  location: {
    latitude: number | null;
    longitude: number | null;
    accuracy: number | null;
    address: string;
  };
  view_photo: string | null;
  open_location: string | null;
  severity: string;
  status: string;
}

interface TrustedUserDetail {
  id: number;
  name: string;
  phone: string;
  email: string;
  verification_status: 'VERIFIED' | 'PENDING' | 'REJECTED';
  relationship: string;
  is_active: boolean;
  verified_at?: string | null;
}

interface SecurityEventItem {
  id: number;
  event_uuid: string;
  event_type: string;
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
  time: string;
  device: {
    id: number;
    name: string;
    uuid: string;
  } | null;
  location: {
    latitude: number | null;
    longitude: number | null;
    accuracy: number | null;
    address?: string;
    map_link: string | null;
  };
  evidence: Array<{
    id: number;
    type: string;
    preview_url: string;
    view_url: string;
  }>;
  status: string;
  reviewed_state: {
    is_reviewed: boolean;
    reviewed_at: string | null;
    reviewed_by: string | null;
    review_notes?: string | null;
  };
}

interface NotificationItem {
  id: number;
  notification_uuid: string;
  type: string;
  title: string;
  body: string;
  sent: string;
  delivery_status: 'DELIVERED' | 'SENT' | 'FAILED' | 'PENDING';
  status?: string;
  is_read?: boolean;
}

// Pre-seeded multi-user profiles for interactive control center demonstration
const DEMO_USERS: Record<number, {
  user: UserDetail;
  subscription: SubscriptionDetail;
  device: DeviceDetail;
  live_location: LiveLocationDetail;
  latest_intruder: LatestIntruderDetail | null;
  trusted_users: TrustedUserDetail[];
  events: SecurityEventItem[];
  notifications: NotificationItem[];
}> = {
  1: {
    user: {
      id: 1,
      uuid: 'usr-9384-valerie-sterling',
      name: 'Valerie Sterling',
      phone: '+1 (555) 987-6543',
      email: 'valerie.sterling@enterprise.net',
      status: 'ACTIVE',
      created_at: '2025-11-12T09:30:00Z',
    },
    subscription: {
      plan: 'Apex Sentinel Shield',
      plan_code: 'APEX-SENTINEL',
      status: 'ACTIVE',
      activated_at: '2026-01-15T00:00:00Z',
      expires_at: '2027-01-15T23:59:59Z',
      is_active: true,
    },
    device: {
      device_id: 'dev-do-pixel8-001',
      id: 101,
      model: 'Samsung Galaxy S24 Ultra (SM-S928B)',
      device_name: 'Titan Enterprise Phone',
      platform: 'ANDROID',
      os_version: 'Android 14 (OneUI 6.1)',
      app_version: 'v2.4.1 (Build 204)',
      device_management_mode: 'DEVICE_OWNER',
      status: 'ACTIVE',
      last_seen: '2026-09-24T02:04:12Z',
      is_protection_enabled: true,
      all_devices_count: 3,
    },
    live_location: {
      latitude: 37.774929,
      longitude: -122.419416,
      accuracy: 4.2,
      last_updated: '2026-09-24T02:04:10Z',
      map_preview: 'https://maps.google.com/?q=37.774929,-122.419416',
      map_url: 'https://maps.google.com/?q=37.774929,-122.419416',
      open_map_button: 'https://maps.google.com/?q=37.774929,-122.419416',
      short_location_url: 'https://sata.security/loc/v93k82',
      short_url: 'https://sata.security/loc/v93k82',
      copy_short_url: 'https://sata.security/loc/v93k82',
      has_active_session: true,
    },
    latest_intruder: {
      event_id: 801,
      event_uuid: 'evt-intruder-7721',
      event_type: 'INTRUDER_PHOTO',
      event_time: '2026-09-24T01:18:22Z',
      photo_preview: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=500&auto=format&fit=crop&q=80',
      location: {
        latitude: 37.775210,
        longitude: -122.418900,
        accuracy: 5.0,
        address: '540 Market Street, San Francisco, CA 94104',
      },
      view_photo: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=1200&auto=format&fit=crop&q=90',
      open_location: 'https://maps.google.com/?q=37.775210,-122.418900',
      severity: 'HIGH',
      status: 'REPORTED',
    },
    trusted_users: [
      {
        id: 201,
        name: 'Arthur Sterling',
        phone: '+1 (555) 111-2233',
        email: 'arthur.sterling@enterprise.net',
        verification_status: 'VERIFIED',
        relationship: 'Spouse / Emergency Guardian',
        is_active: true,
        verified_at: '2026-01-16T10:14:00Z',
      },
      {
        id: 202,
        name: 'Elena Drake',
        phone: '+1 (555) 444-5566',
        email: 'elena.drake@legalsec.org',
        verification_status: 'PENDING',
        relationship: 'Legal Counsel',
        is_active: true,
        verified_at: null,
      },
    ],
    events: [
      {
        id: 501,
        event_uuid: 'evt-ev-501',
        event_type: 'INTRUDER_PHOTO',
        severity: 'HIGH',
        time: '2026-09-24T01:18:22Z',
        device: { id: 101, name: 'Titan Enterprise Phone', uuid: 'dev-do-pixel8-001' },
        location: { latitude: 37.77521, longitude: -122.4189, accuracy: 5.0, address: '540 Market St, SF', map_link: 'https://maps.google.com/?q=37.77521,-122.4189' },
        evidence: [{ id: 91, type: 'FRONT_PHOTO', preview_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400', view_url: '#' }],
        status: 'INVESTIGATING',
        reviewed_state: { is_reviewed: false, reviewed_at: null, reviewed_by: null },
      },
      {
        id: 502,
        event_uuid: 'evt-ev-502',
        event_type: 'WRONG_PIN_ATTEMPTS',
        severity: 'MEDIUM',
        time: '2026-09-24T01:17:40Z',
        device: { id: 101, name: 'Titan Enterprise Phone', uuid: 'dev-do-pixel8-001' },
        location: { latitude: 37.7752, longitude: -122.4189, accuracy: 6.2, address: '540 Market St, SF', map_link: 'https://maps.google.com/?q=37.7752,-122.4189' },
        evidence: [],
        status: 'REPORTED',
        reviewed_state: { is_reviewed: true, reviewed_at: '2026-09-24T01:25:00Z', reviewed_by: 'Super Admin Alpha' },
      },
      {
        id: 503,
        event_uuid: 'evt-ev-503',
        event_type: 'SIM_CHANGED',
        severity: 'CRITICAL',
        time: '2026-09-23T18:42:10Z',
        device: { id: 101, name: 'Titan Enterprise Phone', uuid: 'dev-do-pixel8-001' },
        location: { latitude: 37.7749, longitude: -122.4194, accuracy: 4.1, address: 'Civic Center Plaza, SF', map_link: 'https://maps.google.com/?q=37.7749,-122.4194' },
        evidence: [],
        status: 'RESOLVED',
        reviewed_state: { is_reviewed: true, reviewed_at: '2026-09-23T19:00:00Z', reviewed_by: 'Super Admin Alpha' },
      },
      {
        id: 504,
        event_uuid: 'evt-ev-504',
        event_type: 'GEOFENCE_BREACH',
        severity: 'LOW',
        time: '2026-09-22T14:10:00Z',
        device: { id: 101, name: 'Titan Enterprise Phone', uuid: 'dev-do-pixel8-001' },
        location: { latitude: 37.7812, longitude: -122.4111, accuracy: 8.5, address: 'Financial District, SF', map_link: 'https://maps.google.com/?q=37.7812,-122.4111' },
        evidence: [],
        status: 'RESOLVED',
        reviewed_state: { is_reviewed: true, reviewed_at: '2026-09-22T15:30:00Z', reviewed_by: 'Security Operator' },
      },
    ],
    notifications: [
      {
        id: 701,
        notification_uuid: 'notif-9182-01',
        type: 'INTRUDER_ALERT',
        title: 'Intruder Snapshot Dispatched',
        body: 'Camera captured front photo after 3 failed unlock PIN attempts.',
        sent: '2026-09-24T01:18:25Z',
        delivery_status: 'DELIVERED',
        is_read: true,
      },
      {
        id: 702,
        notification_uuid: 'notif-9182-02',
        type: 'LOCATION_SESSION_STARTED',
        title: 'Real-time GPS Tracking Active',
        body: 'Live location stream initiated with 15-minute emergency TTL.',
        sent: '2026-09-24T01:19:00Z',
        delivery_status: 'DELIVERED',
        is_read: true,
      },
      {
        id: 703,
        notification_uuid: 'notif-9182-03',
        type: 'TRUSTED_USER_ALERTED',
        title: 'Emergency SMS Dispatched to Arthur Sterling',
        body: 'Alert dispatched to +1 (555) 111-2233 with short location reference.',
        sent: '2026-09-24T01:19:05Z',
        delivery_status: 'DELIVERED',
        is_read: true,
      },
    ],
  },
  2: {
    user: {
      id: 2,
      uuid: 'usr-4412-marcus-wright',
      name: 'Marcus Wright',
      phone: '+1 (555) 777-8899',
      email: 'm.wright@executive.com',
      status: 'ACTIVE',
      created_at: '2026-02-10T14:20:00Z',
    },
    subscription: {
      plan: 'Executive Shield Plus',
      plan_code: 'EXEC-PLUS',
      status: 'ACTIVE',
      activated_at: '2026-02-10T14:20:00Z',
      expires_at: '2027-02-10T23:59:59Z',
      is_active: true,
    },
    device: {
      device_id: 'dev-mdm-iphone15-003',
      id: 103,
      model: 'Apple iPhone 15 Pro',
      device_name: 'Executive iPhone 15',
      platform: 'IOS',
      os_version: 'iOS 17.5.1',
      app_version: 'v2.4.1',
      device_management_mode: 'SUPERVISED_MDM',
      status: 'ACTIVE',
      last_seen: '2026-09-24T01:50:00Z',
      is_protection_enabled: true,
      all_devices_count: 1,
    },
    live_location: {
      latitude: 40.712776,
      longitude: -74.005974,
      accuracy: 3.5,
      last_updated: '2026-09-24T01:50:00Z',
      map_preview: 'https://maps.google.com/?q=40.712776,-74.005974',
      map_url: 'https://maps.google.com/?q=40.712776,-74.005974',
      open_map_button: 'https://maps.google.com/?q=40.712776,-74.005974',
      short_location_url: 'https://sata.security/loc/m44w99',
      short_url: 'https://sata.security/loc/m44w99',
      copy_short_url: 'https://sata.security/loc/m44w99',
      has_active_session: false,
    },
    latest_intruder: null,
    trusted_users: [
      {
        id: 203,
        name: 'Sarah Wright',
        phone: '+1 (555) 333-9900',
        email: 'sarah.wright@outlook.com',
        verification_status: 'VERIFIED',
        relationship: 'Family Member',
        is_active: true,
        verified_at: '2026-02-11T09:00:00Z',
      },
    ],
    events: [],
    notifications: [],
  },
};

export const AdminUserControlCenter: React.FC = () => {
  const [selectedUserId, setSelectedUserId] = useState<number>(1);
  const [activeAdmin] = useState({
    id: 1,
    name: 'Root Super Admin',
    email: 'admin@sata-security.internal',
    role: 'SUPER_ADMIN',
    permissions: ['users.view', 'users.manage', 'security_commands.execute', 'security_events.view'],
  });

  const currentData = DEMO_USERS[selectedUserId] || DEMO_USERS[1];
  const { user, subscription, device, live_location, latest_intruder, trusted_users, events, notifications } = currentData;

  // Notification / feedback states
  const [copiedUrl, setCopiedUrl] = useState(false);
  const [viewPhotoModal, setViewPhotoModal] = useState<string | null>(null);

  // Security Command Modal state
  const [pendingCommand, setPendingCommand] = useState<CommandType | null>(null);
  const [commandReason, setCommandReason] = useState('');
  const [reauthPassword, setReauthPassword] = useState('');
  const [commandFeedback, setCommandFeedback] = useState<{
    type: 'success' | 'error' | 'unsupported';
    message: string;
    details?: string;
  } | null>(null);

  // Server-side pagination states for Security Events
  const [eventsPage, setEventsPage] = useState(1);
  const eventsPerPage = 2;
  const totalEvents = events.length;
  const totalEventsPages = Math.max(1, Math.ceil(totalEvents / eventsPerPage));
  const paginatedEvents = events.slice((eventsPage - 1) * eventsPerPage, eventsPage * eventsPerPage);

  // Server-side pagination states for Notifications
  const [notifPage, setNotifPage] = useState(1);
  const notifPerPage = 2;
  const totalNotifs = notifications.length;
  const totalNotifPages = Math.max(1, Math.ceil(totalNotifs / notifPerPage));
  const paginatedNotifs = notifications.slice((notifPage - 1) * notifPerPage, notifPage * notifPerPage);

  const handleCopyShortUrl = () => {
    if (live_location.short_location_url) {
      navigator.clipboard?.writeText(live_location.short_location_url);
      setCopiedUrl(true);
      setTimeout(() => setCopiedUrl(false), 2000);
    }
  };

  const isDestructive = (cmd: CommandType): boolean => {
    return cmd === 'FACTORY_RESET' || cmd === 'WIPE_DATA';
  };

  const handleOpenCommandModal = (cmd: CommandType) => {
    setPendingCommand(cmd);
    setCommandReason(`Administrative security operation for user ${user.email} (${cmd})`);
    setReauthPassword('');
    setCommandFeedback(null);
  };

  const handleExecuteCommand = () => {
    if (!pendingCommand) return;

    if (!commandReason.trim()) {
      setCommandFeedback({
        type: 'error',
        message: 'A mandatory audit reason must be supplied for every security command.',
      });
      return;
    }

    if (isDestructive(pendingCommand) && !reauthPassword.trim()) {
      setCommandFeedback({
        type: 'error',
        message: 'Destructive commands strictly require administrator re-authentication password.',
      });
      return;
    }

    // Check device capability matrix
    const mode = device.device_management_mode;
    const platform = device.platform;

    let isSupported = false;
    if (platform === 'ANDROID') {
      if (mode === 'DEVICE_OWNER') {
        isSupported = true; // All supported
      } else if (mode === 'DEVICE_ADMIN') {
        isSupported = pendingCommand !== 'KIOSK_LOCK' && pendingCommand !== 'KIOSK_UNLOCK';
      } else {
        isSupported = false; // STANDARD_APP cannot do factory reset or kiosk
      }
    } else if (platform === 'IOS') {
      if (mode === 'SUPERVISED_MDM') {
        isSupported = pendingCommand === 'KIOSK_LOCK' || pendingCommand === 'KIOSK_UNLOCK' || pendingCommand === 'WIPE_DATA';
      } else {
        isSupported = false;
      }
    }

    if (!isSupported) {
      setCommandFeedback({
        type: 'unsupported',
        message: `Command UNSUPPORTED: Device is running in ${mode} on ${platform}.`,
        details: 'The backend strictly refuses to fake successful execution on unsupported hardware/privilege tiers.',
      });
      return;
    }

    // Success dispatch
    setCommandFeedback({
      type: 'success',
      message: `Command ${pendingCommand} successfully authenticated and dispatched to ${device.model}.`,
      details: `Audit Record Created: Admin ${activeAdmin.name} → ${pendingCommand} → User ${user.name} → Device ${device.device_id} → SUCCESS → ${new Date().toISOString()}`,
    });

    setTimeout(() => {
      setPendingCommand(null);
    }, 2500);
  };

  return (
    <div id="admin-user-control-center" className="space-y-6 animate-in fade-in duration-200">
      {/* Control Center Header & Route Breadcrumbs */}
      <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs text-slate-500 font-medium">
              <span>Admin Console</span>
              <span aria-hidden="true">/</span>
              <span>Users</span>
              <span aria-hidden="true">/</span>
              <span className="text-slate-900 font-mono font-semibold">/admin/users/{user.id}</span>
            </div>
            <div className="flex items-center gap-3 mt-2">
              <div className="w-10 h-10 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-xs">
                {user.name.split(' ').map((n) => n[0]).join('')}
              </div>
              <div>
                <h1 className="text-xl font-bold text-slate-900 flex items-center gap-2">
                  <span>{user.name}</span>
                  <span className={`text-[11px] font-mono px-2 py-0.5 rounded font-semibold ${
                    user.status === 'ACTIVE'
                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                      : 'bg-rose-50 text-rose-700 border border-rose-200'
                  }`}>
                    {user.status}
                  </span>
                </h1>
                <div className="flex items-center gap-3 text-xs text-slate-500 mt-0.5">
                  <span className="font-mono">{user.uuid}</span>
                  <span aria-hidden="true">·</span>
                  <span>Member since {new Date(user.created_at).toLocaleDateString()}</span>
                </div>
              </div>
            </div>
          </div>

          {/* User Profile Switcher & Admin Auth Badge */}
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex items-center gap-1.5 bg-slate-100 p-1 rounded-lg border border-slate-200 text-xs">
              <span className="text-slate-500 px-2 font-medium">Target User:</span>
              <button
                onClick={() => { setSelectedUserId(1); setEventsPage(1); setNotifPage(1); }}
                className={`px-3 py-1 rounded font-medium transition-colors ${
                  selectedUserId === 1 ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Valerie Sterling (Android Kiosk)
              </button>
              <button
                onClick={() => { setSelectedUserId(2); setEventsPage(1); setNotifPage(1); }}
                className={`px-3 py-1 rounded font-medium transition-colors ${
                  selectedUserId === 2 ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Marcus Wright (iOS MDM)
              </button>
            </div>

            <div className="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-right">
              <span className="text-[10px] text-slate-400 block font-medium">Authenticated Admin</span>
              <span className="text-xs font-semibold text-slate-800 flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                {activeAdmin.role}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* Top Grid: Sections 1 (USER), 2 (SUBSCRIPTION), 3 (DEVICE) */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* 1. USER SECTION */}
        <section id="section-user" className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <UserIcon className="w-4 h-4 text-indigo-600" />
                <h2 className="text-sm font-semibold text-slate-900">1. User Identity</h2>
              </div>
              <span className="text-[11px] font-mono text-slate-400">ID: #{user.id}</span>
            </div>

            <div className="mt-4 space-y-2.5 text-xs">
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Full Name</span>
                <span className="font-semibold text-slate-900">{user.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Phone Number</span>
                <span className="font-mono text-slate-800">{user.phone}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Email Address</span>
                <span className="font-medium text-slate-800">{user.email}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Account Status</span>
                <span className="font-bold text-emerald-700">{user.status}</span>
              </div>
            </div>
          </div>
          <div className="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Server Authorization</span>
            <span className="font-mono text-emerald-600 font-medium">Verified Active</span>
          </div>
        </section>

        {/* 2. SUBSCRIPTION SECTION */}
        <section id="section-subscription" className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <CreditCard className="w-4 h-4 text-indigo-600" />
                <h2 className="text-sm font-semibold text-slate-900">2. Subscription Plan</h2>
              </div>
              <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded ${
                subscription.status === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
              }`}>
                {subscription.status}
              </span>
            </div>

            <div className="mt-4 space-y-2.5 text-xs">
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Protection Plan</span>
                <span className="font-semibold text-slate-900">{subscription.plan}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Plan Status</span>
                <span className="font-bold text-emerald-700">{subscription.status}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Activated At</span>
                <span className="text-slate-700">{new Date(subscription.activated_at).toLocaleDateString()}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Expires At</span>
                <span className="font-mono text-slate-800">{new Date(subscription.expires_at).toLocaleDateString()}</span>
              </div>
            </div>
          </div>
          <div className="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500 flex items-center justify-between">
            <span>Access Guard</span>
            <span className="font-medium text-slate-700">Account Never Deleted On Expiry</span>
          </div>
        </section>

        {/* 3. DEVICE SECTION */}
        <section id="section-device" className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <Smartphone className="w-4 h-4 text-indigo-600" />
                <h2 className="text-sm font-semibold text-slate-900">3. Primary Hardware Device</h2>
              </div>
              <span className="text-[11px] font-mono text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200">
                {device.platform}
              </span>
            </div>

            <div className="mt-4 space-y-2.5 text-xs">
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Device ID</span>
                <span className="font-mono text-slate-700 text-[11px] truncate max-w-[150px]" title={device.device_id}>
                  {device.device_id}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Model</span>
                <span className="font-semibold text-slate-900 truncate max-w-[160px]">{device.model}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">OS Version</span>
                <span className="text-slate-800">{device.os_version}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">App Version</span>
                <span className="font-mono text-slate-800">{device.app_version}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Device Status</span>
                <span className="font-bold text-emerald-700">{device.status}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500 font-medium">Last Seen</span>
                <span className="text-slate-600 font-mono text-[11px]">
                  {new Date(device.last_seen).toLocaleTimeString()}
                </span>
              </div>
            </div>
          </div>
          <div className="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500 flex items-center justify-between">
            <span>Privilege Mode</span>
            <span className="font-mono text-indigo-600 font-medium">{device.device_management_mode}</span>
          </div>
        </section>
      </div>

      {/* Middle Grid: Sections 4 (LIVE LOCATION) & 5 (LATEST INTRUDER) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* 4. LIVE LOCATION SECTION */}
        <section id="section-live-location" className="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100">
            <div className="flex items-center gap-2">
              <MapPin className="w-5 h-5 text-indigo-600" />
              <div>
                <h2 className="text-base font-semibold text-slate-900">4. Live GPS Telemetry & Short Link</h2>
                <p className="text-xs text-slate-500">Real-time coordinates refreshed from device location subsystem</p>
              </div>
            </div>
            {live_location.has_active_session && (
              <span className="flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                <Radio className="w-3 h-3 animate-pulse" /> Live Tracking Active
              </span>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-5">
            {/* Coordinates & Accuracy */}
            <div className="space-y-3 text-xs">
              <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-2">
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Latitude:</span>
                  <span className="font-mono font-bold text-slate-900">{live_location.latitude ?? 'N/A'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Longitude:</span>
                  <span className="font-mono font-bold text-slate-900">{live_location.longitude ?? 'N/A'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Accuracy:</span>
                  <span className="font-semibold text-slate-800">±{live_location.accuracy ?? 0} meters</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Last Updated:</span>
                  <span className="font-mono text-slate-700">
                    {live_location.last_updated ? new Date(live_location.last_updated).toLocaleTimeString() : 'N/A'}
                  </span>
                </div>
              </div>

              {/* Short Location URL & Copy */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Short Location Reference URL (Public/Guardian Access):
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="text"
                    readOnly
                    value={live_location.short_location_url ?? 'No active short link'}
                    className="flex-1 text-xs px-3 py-2 bg-slate-50 border border-slate-300 rounded-md font-mono text-slate-800 select-all"
                  />
                  <button
                    onClick={handleCopyShortUrl}
                    className="flex items-center gap-1 px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-md text-xs font-semibold transition-colors"
                  >
                    {copiedUrl ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                    <span>{copiedUrl ? 'Copied' : 'Copy'}</span>
                  </button>
                </div>
              </div>

              {/* Open Map Button */}
              {live_location.map_url && (
                <a
                  href={live_location.map_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full inline-flex items-center justify-center gap-1.5 py-2 px-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-md text-xs font-semibold transition-colors"
                >
                  <ExternalLink className="w-3.5 h-3.5" />
                  <span>Open Full Map (Google Maps)</span>
                </a>
              )}
            </div>

            {/* Map Preview Container */}
            <div className="rounded-lg border border-slate-200 overflow-hidden bg-slate-100 relative min-h-[190px] flex flex-col items-center justify-center p-4 text-center">
              <div className="absolute inset-0 opacity-40 bg-[radial-gradient(#4f46e5_1px,transparent_1px)] [background-size:16px_16px]" />
              <div className="relative z-10 space-y-2">
                <div className="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center mx-auto shadow-md animate-bounce">
                  <MapPin className="w-5 h-5" />
                </div>
                <div className="bg-white/90 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-slate-200 shadow-xs inline-block">
                  <p className="text-xs font-bold text-slate-900">
                    {live_location.latitude?.toFixed(4)}, {live_location.longitude?.toFixed(4)}
                  </p>
                  <p className="text-[10px] text-slate-500">Radius precision: {live_location.accuracy}m</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* 5. LATEST INTRUDER SECTION */}
        <section id="section-latest-intruder" className="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <Camera className="w-5 h-5 text-rose-600" />
                <div>
                  <h2 className="text-base font-semibold text-slate-900">5. Latest Intruder Snapshot</h2>
                  <p className="text-xs text-slate-500">Biometric evidence captured upon trigger</p>
                </div>
              </div>
              {latest_intruder && (
                <span className="text-[10px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 uppercase">
                  {latest_intruder.severity}
                </span>
              )}
            </div>

            {latest_intruder ? (
              <div className="mt-4 space-y-4">
                <div className="flex gap-4 items-start">
                  {/* Photo Preview Thumbnail */}
                  <div className="relative rounded-lg overflow-hidden border border-slate-200 w-28 h-28 bg-slate-900 flex-shrink-0 group">
                    {latest_intruder.photo_preview ? (
                      <img
                        src={latest_intruder.photo_preview}
                        alt="Intruder capture"
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center text-slate-400 text-[10px]">
                        No preview
                      </div>
                    )}
                    <button
                      onClick={() => setViewPhotoModal(latest_intruder.view_photo)}
                      className="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                    >
                      <Eye className="w-5 h-5" />
                    </button>
                  </div>

                  {/* Metadata */}
                  <div className="space-y-1.5 text-xs">
                    <div>
                      <span className="text-slate-500 font-medium block">Incident Time:</span>
                      <span className="font-mono text-slate-900 font-semibold">
                        {new Date(latest_intruder.event_time).toLocaleString()}
                      </span>
                    </div>
                    <div>
                      <span className="text-slate-500 font-medium block">Geocoded Location:</span>
                      <span className="text-slate-700 leading-snug">{latest_intruder.location.address}</span>
                    </div>
                  </div>
                </div>

                {/* View Photo & Open Location Buttons */}
                <div className="grid grid-cols-2 gap-2 pt-2">
                  <button
                    onClick={() => setViewPhotoModal(latest_intruder.view_photo)}
                    className="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-md text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
                  >
                    <Eye className="w-3.5 h-3.5" />
                    <span>View Photo</span>
                  </button>
                  {latest_intruder.open_location && (
                    <a
                      href={latest_intruder.open_location}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="py-2 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-md text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
                    >
                      <ExternalLink className="w-3.5 h-3.5" />
                      <span>Open Location</span>
                    </a>
                  )}
                </div>
              </div>
            ) : (
              <div className="py-8 text-center text-slate-400 text-xs">
                <CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                <span>No intruder events recorded for this device.</span>
              </div>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Storage Disk</span>
            <span className="font-mono text-slate-600">private_evidence (Encrypted)</span>
          </div>
        </section>
      </div>

      {/* Middle Grid: Sections 6 (TRUSTED USERS) & 7 (SECURITY COMMANDS) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* 6. TRUSTED USERS SECTION (MAXIMUM 2) */}
        <section id="section-trusted-users" className="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <Users className="w-5 h-5 text-indigo-600" />
                <div>
                  <h2 className="text-base font-semibold text-slate-900">6. Trusted Users</h2>
                  <p className="text-xs text-slate-500">Emergency alert contacts (Maximum 2 enforced)</p>
                </div>
              </div>
              <span className="text-[11px] font-mono font-bold bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded">
                {trusted_users.length} / 2 Slots
              </span>
            </div>

            <div className="mt-3 p-2.5 bg-amber-50/70 border border-amber-200 rounded-lg text-[11px] text-amber-900 flex items-start gap-2">
              <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div>
                <strong>Alert Delivery Policy:</strong> Front-camera photos & live GPS links are emailed exclusively to trusted contacts. Primary user only receives OTP.
              </div>
            </div>

            <div className="mt-4 space-y-3">
              {trusted_users.map((tu, index) => (
                <div key={tu.id} className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1.5">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-slate-900">
                      Slot {index + 1}: {tu.name}
                    </span>
                    <span
                      className={`text-[10px] font-semibold px-2 py-0.5 rounded font-mono ${
                        tu.verification_status === 'VERIFIED'
                          ? 'bg-emerald-100 text-emerald-800'
                          : 'bg-amber-100 text-amber-800'
                      }`}
                    >
                      {tu.verification_status}
                    </span>
                  </div>
                  <div className="flex justify-between text-slate-600">
                    <span className="text-[11px] text-slate-400">Mobile (Mandatory):</span>
                    <span className="font-mono font-medium">{tu.phone}</span>
                  </div>
                  <div className="flex justify-between items-center text-slate-600">
                    <span className="text-[11px] text-slate-400">Email (Mandatory):</span>
                    <span className="font-mono font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded text-[11px] truncate max-w-[200px]">
                      {tu.email}
                    </span>
                  </div>
                  <div className="flex justify-between text-slate-500">
                    <span className="text-[11px] text-slate-400">Relationship:</span>
                    <span>{tu.relationship}</span>
                  </div>
                </div>
              ))}

              {trusted_users.length === 0 && (
                <p className="text-xs text-slate-400 py-4 text-center">No trusted users configured.</p>
              )}
            </div>
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Quota Enforcement</span>
            <span className="font-medium text-slate-700">Strict Max 2 Constraint</span>
          </div>
        </section>

        {/* 7. SECURITY COMMANDS SECTION */}
        <section id="section-security-commands" className="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100">
            <div className="flex items-center gap-2">
              <Terminal className="w-5 h-5 text-indigo-600" />
              <div>
                <h2 className="text-base font-semibold text-slate-900">7. Security Commands Hub</h2>
                <p className="text-xs text-slate-500">
                  Target Device: {device.device_name} ({device.platform} · {device.device_management_mode})
                </p>
              </div>
            </div>
            <span className="text-xs text-slate-500 font-mono">Role: {activeAdmin.role}</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">
            {/* Factory Reset */}
            <div className="p-3.5 rounded-lg border border-rose-200 bg-rose-50/30 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-rose-800 flex items-center gap-1">
                    <RotateCcw className="w-3.5 h-3.5" />
                    Factory Reset
                  </span>
                  <span className="text-[10px] uppercase font-bold text-rose-600 bg-rose-100 px-1.5 py-0.2 rounded">
                    Destructive
                  </span>
                </div>
                <p className="text-[11px] text-slate-600 mt-1">
                  Complete device wipe. Reboots hardware into factory initial state.
                </p>
              </div>
              <button
                onClick={() => handleOpenCommandModal('FACTORY_RESET')}
                className="mt-3 w-full py-1.5 px-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-semibold transition-colors"
              >
                Factory Reset
              </button>
            </div>

            {/* Kiosk Lock */}
            <div className="p-3.5 rounded-lg border border-indigo-200 bg-indigo-50/30 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-indigo-900 flex items-center gap-1">
                    <Lock className="w-3.5 h-3.5" />
                    Kiosk Lock
                  </span>
                  <span className="text-[10px] uppercase font-bold text-indigo-700 bg-indigo-100 px-1.5 py-0.2 rounded">
                    Lockdown
                  </span>
                </div>
                <p className="text-[11px] text-slate-600 mt-1">
                  Pins security app to foreground, disabling navigation and power menu.
                </p>
              </div>
              <button
                onClick={() => handleOpenCommandModal('KIOSK_LOCK')}
                className="mt-3 w-full py-1.5 px-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold transition-colors"
              >
                Kiosk Lock
              </button>
            </div>

            {/* Kiosk Unlock */}
            <div className="p-3.5 rounded-lg border border-slate-200 bg-slate-50 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-800 flex items-center gap-1">
                    <Unlock className="w-3.5 h-3.5" />
                    Kiosk Unlock
                  </span>
                  <span className="text-[10px] uppercase font-bold text-slate-600 bg-slate-200 px-1.5 py-0.2 rounded">
                    Release
                  </span>
                </div>
                <p className="text-[11px] text-slate-600 mt-1">
                  Releases pinned screen and restores full system navigation.
                </p>
              </div>
              <button
                onClick={() => handleOpenCommandModal('KIOSK_UNLOCK')}
                className="mt-3 w-full py-1.5 px-2 bg-slate-800 hover:bg-slate-900 text-white rounded text-xs font-semibold transition-colors"
              >
                Kiosk Unlock
              </button>
            </div>

            {/* Wipe Data */}
            <div className="p-3.5 rounded-lg border border-rose-200 bg-rose-50/30 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-rose-800 flex items-center gap-1">
                    <Trash2 className="w-3.5 h-3.5" />
                    Wipe Data
                  </span>
                  <span className="text-[10px] uppercase font-bold text-rose-600 bg-rose-100 px-1.5 py-0.2 rounded">
                    Destructive
                  </span>
                </div>
                <p className="text-[11px] text-slate-600 mt-1">
                  Erases downloads, photos, and sandboxed enterprise credentials.
                </p>
              </div>
              <button
                onClick={() => handleOpenCommandModal('WIPE_DATA')}
                className="mt-3 w-full py-1.5 px-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-semibold transition-colors"
              >
                Wipe Data
              </button>
            </div>
          </div>
        </section>
      </div>

      {/* 8. SECURITY EVENTS SECTION (SERVER-SIDE PAGINATION) */}
      <section id="section-security-events" className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-100">
          <div>
            <h2 className="text-base font-semibold text-slate-900">8. Security Event History</h2>
            <p className="text-xs text-slate-500">Server-side paginated audit trail of physical and digital security events</p>
          </div>
          <span className="text-xs text-slate-500 font-mono">
            Showing {paginatedEvents.length} of {totalEvents} events
          </span>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/60 text-slate-500 font-medium">
                <th className="py-2.5 px-3">Event Type</th>
                <th className="py-2.5 px-3">Severity</th>
                <th className="py-2.5 px-3">Time</th>
                <th className="py-2.5 px-3">Device</th>
                <th className="py-2.5 px-3">Location</th>
                <th className="py-2.5 px-3">Evidence</th>
                <th className="py-2.5 px-3">Status</th>
                <th className="py-2.5 px-3">Reviewed State</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {paginatedEvents.map((evt) => (
                <tr key={evt.id} className="hover:bg-slate-50/60 transition-colors">
                  <td className="py-3 px-3 font-semibold text-slate-900 font-mono text-[11px]">
                    {evt.event_type}
                  </td>
                  <td className="py-3 px-3">
                    <span
                      className={`font-semibold px-2 py-0.5 rounded text-[10px] font-mono ${
                        evt.severity === 'CRITICAL'
                          ? 'bg-rose-100 text-rose-800 font-bold'
                          : evt.severity === 'HIGH'
                          ? 'bg-orange-100 text-orange-800'
                          : evt.severity === 'MEDIUM'
                          ? 'bg-amber-100 text-amber-800'
                          : 'bg-slate-100 text-slate-700'
                      }`}
                    >
                      {evt.severity}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-slate-600 font-mono text-[11px]">
                    {new Date(evt.time).toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-slate-800">
                    {evt.device?.name ?? 'Unknown Device'}
                  </td>
                  <td className="py-3 px-3 text-slate-600 max-w-[150px] truncate" title={evt.location.address}>
                    {evt.location.address ?? `${evt.location.latitude}, ${evt.location.longitude}`}
                  </td>
                  <td className="py-3 px-3">
                    {evt.evidence.length > 0 ? (
                      <button
                        onClick={() => setViewPhotoModal(evt.evidence[0].preview_url)}
                        className="text-[11px] text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1"
                      >
                        <Eye className="w-3 h-3" />
                        <span>{evt.evidence[0].type}</span>
                      </button>
                    ) : (
                      <span className="text-slate-400 text-[11px]">None</span>
                    )}
                  </td>
                  <td className="py-3 px-3">
                    <span className="font-mono text-[11px] text-slate-700 font-medium">
                      {evt.status}
                    </span>
                  </td>
                  <td className="py-3 px-3">
                    {evt.reviewed_state.is_reviewed ? (
                      <span className="text-emerald-700 font-medium flex items-center gap-1 text-[11px]">
                        <CheckCircle2 className="w-3 h-3" />
                        <span>Reviewed by {evt.reviewed_state.reviewed_by}</span>
                      </span>
                    ) : (
                      <span className="text-amber-700 font-medium text-[11px]">
                        Pending Review
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Server-Side Pagination Bar for Events */}
        <div className="flex items-center justify-between pt-4 mt-2 border-t border-slate-100 text-xs text-slate-500">
          <span>
            Page {eventsPage} of {totalEventsPages}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setEventsPage((p) => Math.max(1, p - 1))}
              disabled={eventsPage === 1}
              className="px-3 py-1.5 border border-slate-200 rounded font-medium disabled:opacity-40 hover:bg-slate-50 flex items-center gap-1"
            >
              <ChevronLeft className="w-3.5 h-3.5" /> Previous
            </button>
            <button
              onClick={() => setEventsPage((p) => Math.min(totalEventsPages, p + 1))}
              disabled={eventsPage === totalEventsPages}
              className="px-3 py-1.5 border border-slate-200 rounded font-medium disabled:opacity-40 hover:bg-slate-50 flex items-center gap-1"
            >
              Next <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </section>

      {/* 9. NOTIFICATIONS SECTION (SERVER-SIDE PAGINATION) */}
      <section id="section-notifications" className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-100">
          <div>
            <h2 className="text-base font-semibold text-slate-900">9. Notification Dispatch History</h2>
            <p className="text-xs text-slate-500">Security alert broadcasts dispatched to devices and verified contacts</p>
          </div>
          <span className="text-xs text-slate-500 font-mono">
            Showing {paginatedNotifs.length} of {totalNotifs} notifications
          </span>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/60 text-slate-500 font-medium">
                <th className="py-2.5 px-3">Notification Type</th>
                <th className="py-2.5 px-3">Message Title & Details</th>
                <th className="py-2.5 px-3">Sent Timestamp</th>
                <th className="py-2.5 px-3 text-right">Delivery Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {paginatedNotifs.map((notif) => (
                <tr key={notif.id} className="hover:bg-slate-50/60 transition-colors">
                  <td className="py-3 px-3 font-mono text-[11px] font-bold text-slate-900">
                    {notif.type}
                  </td>
                  <td className="py-3 px-3">
                    <p className="font-semibold text-slate-800">{notif.title}</p>
                    <p className="text-slate-500 text-[11px] mt-0.5">{notif.body}</p>
                  </td>
                  <td className="py-3 px-3 text-slate-600 font-mono text-[11px]">
                    {new Date(notif.sent).toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-right">
                    <span
                      className={`font-semibold px-2 py-0.5 rounded text-[10px] font-mono ${
                        notif.delivery_status === 'DELIVERED'
                          ? 'bg-emerald-100 text-emerald-800'
                          : notif.delivery_status === 'SENT'
                          ? 'bg-blue-100 text-blue-800'
                          : 'bg-rose-100 text-rose-800'
                      }`}
                    >
                      {notif.delivery_status}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Server-Side Pagination Bar for Notifications */}
        <div className="flex items-center justify-between pt-4 mt-2 border-t border-slate-100 text-xs text-slate-500">
          <span>
            Page {notifPage} of {totalNotifPages}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setNotifPage((p) => Math.max(1, p - 1))}
              disabled={notifPage === 1}
              className="px-3 py-1.5 border border-slate-200 rounded font-medium disabled:opacity-40 hover:bg-slate-50 flex items-center gap-1"
            >
              <ChevronLeft className="w-3.5 h-3.5" /> Previous
            </button>
            <button
              onClick={() => setNotifPage((p) => Math.min(totalNotifPages, p + 1))}
              disabled={notifPage === totalNotifPages}
              className="px-3 py-1.5 border border-slate-200 rounded font-medium disabled:opacity-40 hover:bg-slate-50 flex items-center gap-1"
            >
              Next <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </section>

      {/* Confirmation & Re-Authentication Modal for Destructive Commands */}
      {pendingCommand && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in zoom-in-95 duration-150">
            <div
              className={`p-5 border-b ${
                isDestructive(pendingCommand)
                  ? 'bg-rose-50 border-rose-200 text-rose-900'
                  : 'bg-indigo-50 border-indigo-200 text-indigo-900'
              } flex items-center justify-between`}
            >
              <div className="flex items-center gap-2 font-semibold">
                {isDestructive(pendingCommand) ? (
                  <AlertTriangle className="w-5 h-5 text-rose-600" />
                ) : (
                  <ShieldAlert className="w-5 h-5 text-indigo-600" />
                )}
                <span>Confirm Security Command Dispatch</span>
              </div>
              <button
                onClick={() => setPendingCommand(null)}
                className="text-slate-400 hover:text-slate-600 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            <div className="p-6 space-y-4">
              {/* Command Details */}
              <div className="bg-slate-50 p-3.5 rounded-lg border border-slate-200 space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Command:</span>
                  <span className="font-mono font-bold text-slate-900">{pendingCommand}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Target User:</span>
                  <span className="font-semibold text-slate-800">{user.name} ({user.email})</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Target Hardware:</span>
                  <span className="font-semibold text-slate-800">{device.model}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Management Mode:</span>
                  <span className="font-mono text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200">
                    {device.platform} - {device.device_management_mode}
                  </span>
                </div>
              </div>

              {isDestructive(pendingCommand) && (
                <div className="p-3 bg-rose-50 border border-rose-200 rounded text-xs text-rose-800">
                  <p className="font-semibold">Irreversible Destructive Action:</p>
                  <p className="mt-0.5">
                    This operation will erase user data or reset device partitions. Admin re-authentication is mandatory.
                  </p>
                </div>
              )}

              {/* Audit Reason */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Mandatory Audit Reason <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={commandReason}
                  onChange={(e) => setCommandReason(e.target.value)}
                  className="w-full text-xs px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 text-slate-800"
                />
              </div>

              {/* Re-auth password */}
              {isDestructive(pendingCommand) && (
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                    <span>Admin Re-Authentication Password <span className="text-rose-500">*</span></span>
                    <span className="text-[10px] text-slate-400 font-normal">Demo credentials: Secret123!</span>
                  </label>
                  <input
                    type="password"
                    placeholder="Enter current admin password"
                    value={reauthPassword}
                    onChange={(e) => setReauthPassword(e.target.value)}
                    className="w-full text-xs px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-rose-500 text-slate-800"
                  />
                </div>
              )}

              {/* Feedback banners */}
              {commandFeedback && (
                <div
                  className={`p-3 rounded-md text-xs ${
                    commandFeedback.type === 'success'
                      ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
                      : commandFeedback.type === 'unsupported'
                      ? 'bg-amber-50 border border-amber-200 text-amber-800'
                      : 'bg-rose-50 border border-rose-200 text-rose-800'
                  }`}
                >
                  <p className="font-semibold">{commandFeedback.message}</p>
                  {commandFeedback.details && <p className="mt-1 font-mono text-[11px]">{commandFeedback.details}</p>}
                </div>
              )}
            </div>

            <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
              <button
                onClick={() => setPendingCommand(null)}
                className="px-4 py-2 border border-slate-300 rounded-md text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleExecuteCommand}
                className={`px-4 py-2 rounded-md text-xs font-semibold text-white shadow-xs transition-colors ${
                  isDestructive(pendingCommand) ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700'
                }`}
              >
                {isDestructive(pendingCommand) ? 'Re-Authenticate & Execute' : 'Dispatch Command'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Photo Preview Modal */}
      {viewPhotoModal && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden max-w-2xl w-full shadow-2xl">
            <div className="p-4 border-b border-slate-800 flex items-center justify-between text-white">
              <span className="font-semibold text-sm">Security Evidence Inspection</span>
              <button onClick={() => setViewPhotoModal(null)} className="text-slate-400 hover:text-white text-sm font-bold">
                ✕
              </button>
            </div>
            <div className="p-4 flex items-center justify-center bg-black">
              <img src={viewPhotoModal} alt="Enlarged Evidence" className="max-h-[500px] w-auto object-contain rounded" />
            </div>
            <div className="p-4 bg-slate-900 border-t border-slate-800 flex justify-between items-center text-xs text-slate-400">
              <span>SHA-256 Checksum Verified · Private Storage</span>
              <button
                onClick={() => setViewPhotoModal(null)}
                className="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-semibold"
              >
                Close Viewer
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
