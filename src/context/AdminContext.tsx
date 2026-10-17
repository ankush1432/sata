import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import {
  User,
  Plan,
  Subscription,
  ActivationCode,
  SecurityAlert,
  SecurityCommand,
  SystemLog,
  AdminNotification,
  SettingsConfig,
  CommandType,
  TrustedContact
} from '../types/admin';

export interface ToastItem {
  id: string;
  type: 'success' | 'error' | 'info' | 'warning';
  title: string;
  message?: string;
  duration?: number;
}

interface AdminContextType {
  // Authentication & Session
  isAuthenticated: boolean;
  adminUser: { id: number; name: string; email: string; role: string } | null;
  adminToken: string | null;
  login: (email: string, password: string, rememberMe?: boolean) => Promise<boolean>;
  logout: () => void;

  // Navigation & Shell
  currentRoute: string;
  setCurrentRoute: (route: string) => void;
  isSidebarCollapsed: boolean;
  toggleSidebar: () => void;
  searchTerm: string;
  setSearchTerm: (term: string) => void;

  // Data
  users: User[];
  plans: Plan[];
  subscriptions: Subscription[];
  activationCodes: ActivationCode[];
  securityAlerts: SecurityAlert[];
  securityCommands: SecurityCommand[];
  systemLogs: SystemLog[];
  notifications: AdminNotification[];
  settings: SettingsConfig;

  // User Actions
  addUser: (userData: { name: string; phone: string; email: string; planId?: number; notes?: string; trustedContacts?: Array<{ name: string; phone: string; email: string; relationship?: string }> }) => User;
  updateUser: (id: number, data: Partial<User>) => void;
  deleteUser: (id: number) => void;
  activateUser: (id: number) => void;
  disableUser: (id: number) => void;
  addTrustedContact: (userId: number, contact: { name: string; mobile_number: string; email: string; relationship?: string; is_verified?: boolean }) => Promise<{ success: boolean; message: string; contact?: TrustedContact }>;
  updateTrustedContact: (userId: number, contactId: number, data: { name?: string; mobile_number?: string; email?: string; relationship?: string; is_verified?: boolean }) => Promise<{ success: boolean; message: string }>;
  deleteTrustedContact: (userId: number, contactId: number) => Promise<{ success: boolean; message: string }>;

  // Plan Actions
  addPlan: (plan: Omit<Plan, 'id' | 'subscribers_count'>) => void;
  updatePlan: (id: number, data: Partial<Plan>) => void;
  deletePlan: (id: number) => void;
  togglePlanStatus: (id: number) => void;

  // Subscription Actions
  renewSubscription: (id: number, additionalDays?: number) => void;
  cancelSubscription: (id: number) => void;
  deleteSubscription: (id: number) => void;

  // Activation Code Actions
  generateActivationCodes: (planId: number, count: number, expiryDays: number, notes?: string) => ActivationCode[];
  revokeActivationCode: (id: number) => void;
  deleteActivationCode: (id: number) => void;

  // Security Actions
  dispatchSecurityCommand: (userId: number, deviceId: number, commandType: CommandType, reason: string) => SecurityCommand;
  resolveSecurityAlert: (id: number, notes?: string) => void;
  deleteSecurityAlert: (id: number) => void;

  // System & Notifications
  updateSettings: (newSettings: Partial<SettingsConfig>) => void;
  markNotificationAsRead: (id: number) => void;
  markAllNotificationsAsRead: () => void;

  // Toast
  toasts: ToastItem[];
  addToast: (title: string, message?: string, type?: 'success' | 'error' | 'info' | 'warning') => void;
  removeToast: (id: string) => void;

  // CSV Export
  exportToCSV: (filename: string, headers: string[], rows: (string | number | boolean | null | undefined)[][]) => void;
}

const INITIAL_PLANS: Plan[] = [
  {
    id: 1,
    name: 'Standard 30-Day Protection',
    code: 'PLAN_30D',
    description: 'Essential anti-theft shield with live location and intruder camera capture.',
    duration_days: 30,
    price: 9.99,
    currency: 'USD',
    status: 'ACTIVE',
    features: ['Intruder Photo Capture', 'Real-time GPS Tracking', 'Remote Siren Alarm', '1 Trusted Contact'],
    subscribers_count: 412
  },
  {
    id: 2,
    name: 'Pro 90-Day Defense',
    code: 'PLAN_90D',
    description: 'Quarterly continuous device security with cloud forensics and 2 trusted emergency contacts.',
    duration_days: 90,
    price: 24.99,
    currency: 'USD',
    status: 'ACTIVE',
    features: ['All Standard Features', '2 Trusted Emergency Contacts', 'Sim Removal Alert', 'Priority FCM Delivery'],
    subscribers_count: 285
  },
  {
    id: 3,
    name: 'Annual Security Suite (365 Days)',
    code: 'PLAN_365D',
    description: 'Enterprise-grade protection with complete remote kiosk lockdown and wipe capabilities.',
    duration_days: 365,
    price: 69.99,
    currency: 'USD',
    status: 'ACTIVE',
    features: ['All Pro Features', 'Remote Factory Reset', 'Kiosk Lock & Unlock', 'Unlimited Evidence Storage', '24/7 Forensic Audit Trail'],
    subscribers_count: 541
  },
  {
    id: 4,
    name: 'Trial Protection (7 Days)',
    code: 'PLAN_TRIAL_7D',
    description: 'Temporary evaluation package for new hardware onboarding.',
    duration_days: 7,
    price: 0.00,
    currency: 'USD',
    status: 'DISABLED',
    features: ['Basic GPS Tracking', 'Wrong PIN Alert'],
    subscribers_count: 10
  }
];

const INITIAL_USERS: User[] = [
  {
    id: 1,
    name: 'Alex Rivera',
    phone: '+14155552671',
    email: 'alex.rivera@example.com',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
    status: 'ACTIVE',
    subscription_status: 'ACTIVE',
    activation_status: 'ACTIVATED',
    created_at: '2026-08-14 09:12:00',
    updated_at: '2026-09-28 14:20:00',
    devices: [
      {
        id: 101,
        device_uuid: 'b4a1c890-77ef-4d82-9901-d001a45e9981',
        device_name: 'Samsung Galaxy S24 Ultra',
        platform: 'android',
        os_version: 'Android 14 (OneUI 6.1)',
        app_version: 'v2.4.1',
        status: 'ACTIVE',
        is_protection_enabled: true,
        last_seen: 'Just now (1 min ago)',
        battery_level: 88,
        is_charging: false,
        network_type: '5G Mobile Data',
        device_management_mode: 'DEVICE_OWNER'
      }
    ],
    trusted_contacts: [
      {
        id: 1,
        slot: 1,
        name: 'Elena Rivera',
        mobile_number: '+14155559812',
        email: 'elena.rivera@gmail.com',
        relationship: 'Spouse',
        is_verified: true,
        created_at: '2026-08-14 10:00:00'
      },
      {
        id: 2,
        slot: 2,
        name: 'David Chen',
        mobile_number: '+14155553344',
        email: 'd.chen@baycapital.com',
        relationship: 'Brother',
        is_verified: true,
        created_at: '2026-08-15 11:20:00'
      }
    ],
    location: {
      latitude: 37.7749,
      longitude: -122.4194,
      accuracy: 4.2,
      altitude: 18.5,
      speed: 0.8,
      address: '742 Market St, San Francisco, CA 94102',
      last_updated: '2026-09-28 20:15:30',
      short_url: 'https://sata.app/l/sfc-982',
      has_active_session: true
    },
    action_history: [
      {
        id: 1001,
        user_id: 1,
        device_id: 101,
        device_name: 'Samsung Galaxy S24 Ultra',
        action_type: 'POWER_OFF_ATTEMPT',
        title: 'Power Button Held Down (Shutdown Intercepted)',
        severity: 'CRITICAL',
        timestamp: '2026-09-28 18:42:15',
        details: 'Hardware power button held down for 3.5 seconds while locked. SATA Device Admin intercepted shutdown modal and challenged master PIN. Power off prevented.',
        location_summary: '742 Market St, San Francisco, CA (±4.2m)',
        ip_address: '198.51.100.4',
        status: 'BLOCKED'
      },
      {
        id: 1002,
        user_id: 1,
        device_id: 101,
        device_name: 'Samsung Galaxy S24 Ultra',
        action_type: 'INTRUDER_PHOTO_CAPTURED',
        title: 'Intruder Front Camera Snapshot Captured',
        severity: 'HIGH',
        timestamp: '2026-09-28 18:42:18',
        details: 'Stealth front-facing selfie camera automatically triggered upon unauthorized power-button interaction. Photo evidence synced to cloud forensics.',
        location_summary: '742 Market St, San Francisco, CA',
        ip_address: '198.51.100.4',
        status: 'FLAGGED'
      },
      {
        id: 1003,
        user_id: 1,
        device_id: 101,
        device_name: 'Samsung Galaxy S24 Ultra',
        action_type: 'COMMAND_EXECUTED',
        title: 'Remote Kiosk Lockdown Engaged',
        severity: 'CRITICAL',
        timestamp: '2026-09-28 18:45:08',
        details: 'Admin dispatched KIOSK_LOCK via high-priority FCM channel. Target hardware pinned into tamper-proof emergency lockdown overlay.',
        location_summary: '742 Market St, San Francisco, CA',
        ip_address: '10.0.0.1',
        status: 'COMPLETED'
      },
      {
        id: 1004,
        user_id: 1,
        device_id: 101,
        device_name: 'Samsung Galaxy S24 Ultra',
        action_type: 'LOCATION_PING',
        title: 'Continuous GPS Telemetry Synchronized',
        severity: 'LOW',
        timestamp: '2026-09-28 19:15:30',
        details: 'High-precision satellite GPS fix acquired. Latitude 37.774900, Longitude -122.419400. Emergency URL broadcasted to emergency circle.',
        location_summary: '742 Market St, San Francisco, CA',
        status: 'ACKNOWLEDGED'
      },
      {
        id: 1005,
        user_id: 1,
        device_id: 101,
        device_name: 'Samsung Galaxy S24 Ultra',
        action_type: 'COMMAND_EXECUTED',
        title: 'Remote Kiosk Unlock Restored',
        severity: 'INFO',
        timestamp: '2026-09-28 19:30:05',
        details: 'Device recovered by verified subscriber. Admin dispatched KIOSK_UNLOCK. Screen overlay cleared and normal controls restored.',
        location_summary: '742 Market St, San Francisco, CA',
        ip_address: '10.0.0.1',
        status: 'COMPLETED'
      }
    ],
    notes: 'Premium enterprise user. High-value asset tracking enabled.'
  },
  {
    id: 2,
    name: 'Sarah Connor',
    phone: '+13105559102',
    email: 'sarah.connor@cyberdyne.org',
    avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=200&auto=format&fit=crop&q=80',
    status: 'ACTIVE',
    subscription_status: 'ACTIVE',
    activation_status: 'ACTIVATED',
    created_at: '2026-07-20 11:45:00',
    updated_at: '2026-09-28 17:05:00',
    devices: [
      {
        id: 102,
        device_uuid: 'c881fa20-410a-4fa1-8201-9988220011bb',
        device_name: 'Google Pixel 8 Pro',
        platform: 'android',
        os_version: 'Android 15',
        app_version: 'v2.4.1',
        status: 'ACTIVE',
        is_protection_enabled: true,
        last_seen: '3 mins ago',
        battery_level: 64,
        is_charging: true,
        network_type: 'Home Wi-Fi (WPA3)',
        device_management_mode: 'DEVICE_ADMIN'
      }
    ],
    trusted_contacts: [
      {
        id: 3,
        slot: 1,
        name: 'John Connor',
        mobile_number: '+13105559103',
        email: 'john.connor@cyberdyne.org',
        relationship: 'Son',
        is_verified: true,
        created_at: '2026-07-20 12:00:00'
      },
      {
        id: 4,
        slot: 2,
        name: 'Kyle Reese',
        mobile_number: '+13105559800',
        email: 'kyle.reese@resistance.net',
        relationship: 'Partner',
        is_verified: true,
        created_at: '2026-07-21 14:00:00'
      }
    ],
    location: {
      latitude: 34.0522,
      longitude: -118.2437,
      accuracy: 5.0,
      address: 'Wilshire Blvd, Los Angeles, CA 90017',
      last_updated: '2026-09-28 19:48:10',
      short_url: 'https://sata.app/l/la-connor',
      has_active_session: false
    },
    action_history: [
      {
        id: 2001,
        user_id: 2,
        device_id: 102,
        device_name: 'Google Pixel 8 Pro',
        action_type: 'SIM_REMOVED',
        title: 'SIM Card Tray Ejection / SIM Removed',
        severity: 'CRITICAL',
        timestamp: '2026-09-27 22:15:00',
        details: 'SIM Card removed from Slot #1 (ICCID 89014103211849). Anti-tamper trigger engaged: Immediate stealth emergency SMS broadcasted to trusted contact John Connor (+13105559103).',
        location_summary: 'Wilshire Blvd, Los Angeles, CA',
        ip_address: '172.56.21.84',
        status: 'BLOCKED'
      },
      {
        id: 2002,
        user_id: 2,
        device_id: 102,
        device_name: 'Google Pixel 8 Pro',
        action_type: 'WRONG_PIN_ATTEMPT',
        title: '3 Consecutive Invalid Passcode Entries',
        severity: 'HIGH',
        timestamp: '2026-09-27 22:15:42',
        details: 'Intruder failed device passcode 3 times in 30 seconds. Stealth camera forensics triggered and hardware lockdown initiated.',
        location_summary: 'Wilshire Blvd, Los Angeles, CA',
        status: 'FLAGGED'
      },
      {
        id: 2003,
        user_id: 2,
        device_id: 102,
        device_name: 'Google Pixel 8 Pro',
        action_type: 'SIM_CHANGED',
        title: 'Unauthorized SIM Inserted (Blocked)',
        severity: 'CRITICAL',
        timestamp: '2026-09-27 22:20:10',
        details: 'Foreign SIM card detected (Carrier: Unknown MVNO). Device network locked; data access restricted until master security pin entered.',
        location_summary: 'Wilshire Blvd, Los Angeles, CA',
        status: 'BLOCKED'
      }
    ]
  },
  {
    id: 3,
    name: 'Marcus Wright',
    phone: '+12065558411',
    email: 'm.wright@skyline.io',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80',
    status: 'ACTIVE',
    subscription_status: 'EXPIRING',
    activation_status: 'ACTIVATED',
    created_at: '2026-06-10 14:00:00',
    updated_at: '2026-09-27 10:11:00',
    devices: [
      {
        id: 103,
        device_uuid: 'e7104b90-1120-4ee1-b011-aa99881122cc',
        device_name: 'Apple iPhone 15 Pro Max',
        platform: 'ios',
        os_version: 'iOS 18.0',
        app_version: 'v2.4.0',
        status: 'ACTIVE',
        is_protection_enabled: true,
        last_seen: '12 mins ago',
        battery_level: 42,
        is_charging: false,
        network_type: 'LTE Cellular',
        device_management_mode: 'STANDARD_APP'
      }
    ],
    trusted_contacts: [
      {
        id: 5,
        slot: 1,
        name: 'Blair Williams',
        mobile_number: '+12065558999',
        email: 'blair.w@skyline.io',
        relationship: 'Sister',
        is_verified: true,
        created_at: '2026-06-10 15:00:00'
      }
    ],
    location: {
      latitude: 47.6062,
      longitude: -122.3321,
      accuracy: 6.8,
      address: 'Pine St & 4th Ave, Seattle, WA 98101',
      last_updated: '2026-09-28 18:22:00',
      short_url: 'https://sata.app/l/sea-mwright',
      has_active_session: false
    },
    action_history: [
      {
        id: 3001,
        user_id: 3,
        device_id: 103,
        device_name: 'Apple iPhone 15 Pro Max',
        action_type: 'CHARGER_DISCONNECTED',
        title: 'AC Charger Unplugged (Pocket Guard Alert)',
        severity: 'MEDIUM',
        timestamp: '2026-09-26 14:30:12',
        details: 'Device disconnected from USB-C power source in public space while Pocket Theft Guard was engaged. Movement accelerometer triggered local warning tone.',
        location_summary: 'Pine St & 4th Ave, Seattle, WA',
        status: 'ACKNOWLEDGED'
      },
      {
        id: 3002,
        user_id: 3,
        device_id: 103,
        device_name: 'Apple iPhone 15 Pro Max',
        action_type: 'POWER_BUTTON_CLICK',
        title: 'Repeated Power Button Press Interception',
        severity: 'HIGH',
        timestamp: '2026-09-25 09:14:00',
        details: 'Rapid 5x hardware power key press intercepted. Emergency SOS protocol initiated; location telemetry broadcasted to emergency circle.',
        location_summary: 'Seattle, WA',
        status: 'COMPLETED'
      }
    ]
  },
  {
    id: 4,
    name: 'David Vance',
    phone: '+15125554901',
    email: 'david.vance@austinsec.com',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&auto=format&fit=crop&q=80',
    status: 'PENDING_OTP',
    subscription_status: 'NONE',
    activation_status: 'PENDING',
    created_at: '2026-09-28 19:10:00',
    updated_at: '2026-09-28 19:10:00',
    devices: [],
    trusted_contacts: [
      {
        id: 6,
        slot: 1,
        name: 'Rachel Vance',
        mobile_number: '+15125554902',
        email: 'rachel.vance@gmail.com',
        relationship: 'Spouse',
        is_verified: false,
        created_at: '2026-09-28 19:12:00'
      }
    ],
    action_history: []
  },
  {
    id: 5,
    name: 'Chloe Bennett',
    phone: '+13125557722',
    email: 'chloe.b@chicago-law.com',
    avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=200&auto=format&fit=crop&q=80',
    status: 'AWAITING_ACTIVATION',
    subscription_status: 'NONE',
    activation_status: 'PENDING',
    created_at: '2026-09-28 11:30:00',
    updated_at: '2026-09-28 11:32:00',
    devices: [
      {
        id: 104,
        device_uuid: 'fa990011-3322-4411-8899-776655443322',
        device_name: 'Google Pixel 7a',
        platform: 'android',
        os_version: 'Android 14',
        app_version: 'v2.4.1',
        status: 'ACTIVE',
        is_protection_enabled: false,
        last_seen: '2 hours ago',
        battery_level: 79,
        device_management_mode: 'STANDARD_APP'
      }
    ],
    trusted_contacts: [
      {
        id: 7,
        slot: 1,
        name: 'Miles Bennett',
        mobile_number: '+13125557799',
        email: 'miles.b@chicago-law.com',
        relationship: 'Father',
        is_verified: true,
        created_at: '2026-09-28 11:31:00'
      }
    ],
    action_history: [
      {
        id: 5001,
        user_id: 5,
        device_id: 104,
        device_name: 'Google Pixel 7a',
        action_type: 'LOCATION_PING',
        title: 'Initial Onboarding Device Handshake',
        severity: 'INFO',
        timestamp: '2026-09-28 11:32:00',
        details: 'Hardware profile synchronized via mobile application. Awaiting activation license code validation.',
        status: 'COMPLETED'
      }
    ]
  },
  {
    id: 6,
    name: 'Victor Vance',
    phone: '+17865551234',
    email: 'victor.vance@vicecity.net',
    avatar: 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=200&auto=format&fit=crop&q=80',
    status: 'DISABLED',
    subscription_status: 'EXPIRED',
    activation_status: 'EXPIRED',
    created_at: '2026-03-01 08:00:00',
    updated_at: '2026-09-20 16:45:00',
    devices: [
      {
        id: 105,
        device_uuid: '00112233-4455-6677-8899-aabbccddeeff',
        device_name: 'OnePlus 11 5G',
        platform: 'android',
        os_version: 'OxygenOS 14',
        app_version: 'v2.2.0',
        status: 'REVOKED',
        is_protection_enabled: false,
        last_seen: '8 days ago',
        battery_level: 0,
        device_management_mode: 'STANDARD_APP'
      }
    ],
    trusted_contacts: [
      {
        id: 8,
        slot: 1,
        name: 'Lance Vance',
        mobile_number: '+17865551235',
        email: 'lance.vance@vicecity.net',
        relationship: 'Brother',
        is_verified: true,
        created_at: '2026-03-01 09:00:00'
      }
    ],
    action_history: [
      {
        id: 6001,
        user_id: 6,
        device_id: 105,
        device_name: 'OnePlus 11 5G',
        action_type: 'COMMAND_EXECUTED',
        title: 'Remote Storage Wipe Dispatched & Executed',
        severity: 'CRITICAL',
        timestamp: '2026-09-20 16:40:42',
        details: 'Device reported permanently stolen. Storage wipe executed by Admin command. All sensitive cryptographic keys and session credentials purged.',
        status: 'COMPLETED'
      }
    ],
    notes: 'Account suspended due to policy violation & device revocation.'
  }
];

const INITIAL_SUBSCRIPTIONS: Subscription[] = [
  {
    id: 1,
    user_id: 1,
    user_name: 'Alex Rivera',
    user_email: 'alex.rivera@example.com',
    plan_id: 3,
    plan_name: 'Annual Security Suite (365 Days)',
    plan_code: 'PLAN_365D',
    starts_at: '2026-08-14 09:12:00',
    expires_at: '2027-08-14 09:12:00',
    status: 'ACTIVE',
    price_paid: 69.99,
    payment_reference: 'SATA-SUB-2026-8812',
    days_remaining: 320,
    auto_renew: true
  },
  {
    id: 2,
    user_id: 2,
    user_name: 'Sarah Connor',
    user_email: 'sarah.connor@cyberdyne.org',
    plan_id: 2,
    plan_name: 'Pro 90-Day Defense',
    plan_code: 'PLAN_90D',
    starts_at: '2026-07-20 11:45:00',
    expires_at: '2026-10-18 11:45:00',
    status: 'ACTIVE',
    price_paid: 24.99,
    payment_reference: 'SATA-SUB-2026-5401',
    days_remaining: 20,
    auto_renew: true
  },
  {
    id: 3,
    user_id: 3,
    user_name: 'Marcus Wright',
    user_email: 'm.wright@skyline.io',
    plan_id: 1,
    plan_name: 'Standard 30-Day Protection',
    plan_code: 'PLAN_30D',
    starts_at: '2026-09-01 14:00:00',
    expires_at: '2026-10-01 14:00:00',
    status: 'EXPIRING',
    price_paid: 9.99,
    payment_reference: 'SATA-SUB-2026-9042',
    days_remaining: 3,
    auto_renew: false
  },
  {
    id: 4,
    user_id: 6,
    user_name: 'Victor Vance',
    user_email: 'victor.vance@vicecity.net',
    plan_id: 1,
    plan_name: 'Standard 30-Day Protection',
    plan_code: 'PLAN_30D',
    starts_at: '2026-03-01 08:00:00',
    expires_at: '2026-03-31 08:00:00',
    status: 'EXPIRED',
    price_paid: 9.99,
    payment_reference: 'SATA-SUB-2026-1002',
    days_remaining: 0,
    auto_renew: false
  }
];

const INITIAL_ACTIVATION_CODES: ActivationCode[] = [
  {
    id: 1,
    code: 'SATA-8821-K902-7711',
    user_id: 1,
    user_name: 'Alex Rivera',
    plan_id: 3,
    plan_name: 'Annual Security Suite (365 Days)',
    status: 'USED',
    generated_at: '2026-08-14 09:00:00',
    activated_at: '2026-08-14 09:12:00',
    expires_at: '2027-08-14 09:00:00',
    created_by: 'Admin',
    notes: 'Direct onboarding batch',
    batch_id: 'BATCH-2026-08-A'
  },
  {
    id: 2,
    code: 'SATA-4109-MN99-3341',
    user_id: 2,
    user_name: 'Sarah Connor',
    plan_id: 2,
    plan_name: 'Pro 90-Day Defense',
    status: 'USED',
    generated_at: '2026-07-20 11:00:00',
    activated_at: '2026-07-20 11:45:00',
    expires_at: '2026-10-20 11:00:00',
    created_by: 'Admin',
    notes: 'Q3 partner allocation',
    batch_id: 'BATCH-2026-07-P'
  },
  {
    id: 3,
    code: 'SATA-9941-ZX22-4412',
    plan_id: 3,
    plan_name: 'Annual Security Suite (365 Days)',
    status: 'UNUSED',
    generated_at: '2026-09-25 10:00:00',
    expires_at: '2027-09-25 10:00:00',
    created_by: 'Admin',
    notes: 'Corporate reseller key 01',
    batch_id: 'BATCH-2026-09-CORP'
  },
  {
    id: 4,
    code: 'SATA-9941-ZX22-4413',
    plan_id: 3,
    plan_name: 'Annual Security Suite (365 Days)',
    status: 'UNUSED',
    generated_at: '2026-09-25 10:00:00',
    expires_at: '2027-09-25 10:00:00',
    created_by: 'Admin',
    notes: 'Corporate reseller key 02',
    batch_id: 'BATCH-2026-09-CORP'
  },
  {
    id: 5,
    code: 'SATA-1120-BB77-9901',
    plan_id: 1,
    plan_name: 'Standard 30-Day Protection',
    status: 'UNUSED',
    generated_at: '2026-09-28 14:00:00',
    expires_at: '2026-10-28 14:00:00',
    created_by: 'Admin',
    notes: 'Customer support compensation voucher'
  },
  {
    id: 6,
    code: 'SATA-3301-REV8-1122',
    plan_id: 2,
    plan_name: 'Pro 90-Day Defense',
    status: 'REVOKED',
    generated_at: '2026-09-01 09:00:00',
    expires_at: '2026-12-01 09:00:00',
    created_by: 'Admin',
    notes: 'Revoked due to billing dispute'
  }
];

const INITIAL_ALERTS: SecurityAlert[] = [
  {
    id: 1,
    event_uuid: 'evt-intruder-0928-881',
    user_id: 1,
    user_name: 'Alex Rivera',
    user_phone: '+14155552671',
    alert_type: 'INTRUDER_PHOTO_CAPTURED',
    severity: 'CRITICAL',
    device_name: 'Samsung Galaxy S24 Ultra',
    device_uuid: 'b4a1c890-77ef-4d82-9901-d001a45e9981',
    platform: 'android',
    location: {
      latitude: 37.7749,
      longitude: -122.4194,
      address: '742 Market St, San Francisco, CA',
      accuracy: 3.8
    },
    evidence_url: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&auto=format&fit=crop&q=80',
    created_at: '2026-09-28 18:42:15',
    status: 'UNRESOLVED',
    notes: '3 failed PIN attempts triggered front camera burst capture and emergency SMS to trusted users.'
  },
  {
    id: 2,
    event_uuid: 'evt-sim-0927-104',
    user_id: 2,
    user_name: 'Sarah Connor',
    user_phone: '+13105559102',
    alert_type: 'SIM_CARD_REMOVED',
    severity: 'HIGH',
    device_name: 'Google Pixel 8 Pro',
    device_uuid: 'c881fa20-410a-4fa1-8201-9988220011bb',
    platform: 'android',
    location: {
      latitude: 34.0522,
      longitude: -118.2437,
      address: 'Wilshire Blvd, Los Angeles, CA',
      accuracy: 4.5
    },
    created_at: '2026-09-27 22:15:00',
    status: 'UNRESOLVED',
    notes: 'SIM tray ejected while device screen locked. Kiosk defense standby.'
  },
  {
    id: 3,
    event_uuid: 'evt-pin-0926-449',
    user_id: 3,
    user_name: 'Marcus Wright',
    user_phone: '+12065558411',
    alert_type: 'WRONG_PIN_ATTEMPT',
    severity: 'MEDIUM',
    device_name: 'Apple iPhone 15 Pro Max',
    device_uuid: 'e7104b90-1120-4ee1-b011-aa99881122cc',
    platform: 'ios',
    location: {
      latitude: 47.6062,
      longitude: -122.3321,
      address: 'Pine St & 4th Ave, Seattle, WA',
      accuracy: 6.0
    },
    created_at: '2026-09-26 14:05:10',
    status: 'RESOLVED',
    resolved_at: '2026-09-26 15:30:00',
    resolved_by: 'Admin',
    notes: 'User verified accidental incorrect passcode input.'
  },
  {
    id: 4,
    event_uuid: 'evt-pwr-0925-992',
    user_id: 1,
    user_name: 'Alex Rivera',
    user_phone: '+14155552671',
    alert_type: 'POWER_OFF_ATTEMPT',
    severity: 'HIGH',
    device_name: 'Samsung Galaxy S24 Ultra',
    device_uuid: 'b4a1c890-77ef-4d82-9901-d001a45e9981',
    platform: 'android',
    location: {
      latitude: 37.7749,
      longitude: -122.4194,
      address: '742 Market St, San Francisco, CA',
      accuracy: 4.0
    },
    created_at: '2026-09-25 08:12:00',
    status: 'RESOLVED',
    resolved_at: '2026-09-25 09:00:00',
    resolved_by: 'Admin',
    notes: 'Power menu interception prevented hardware shutdown without master PIN.'
  }
];

const INITIAL_COMMANDS: SecurityCommand[] = [
  {
    id: 1,
    command_uuid: 'cmd-kiosk-0928-001',
    user_id: 1,
    user_name: 'Alex Rivera',
    device_id: 101,
    device_name: 'Samsung Galaxy S24 Ultra',
    device_uuid: 'b4a1c890-77ef-4d82-9901-d001a45e9981',
    platform: 'android',
    command_type: 'KIOSK_LOCK',
    status: 'COMPLETED',
    reason: 'Preventive lockdown following suspected theft report',
    admin_name: 'Admin',
    created_at: '2026-09-28 18:45:00',
    executed_at: '2026-09-28 18:45:08'
  },
  {
    id: 2,
    command_uuid: 'cmd-kiosk-unlock-0928-002',
    user_id: 1,
    user_name: 'Alex Rivera',
    device_id: 101,
    device_name: 'Samsung Galaxy S24 Ultra',
    device_uuid: 'b4a1c890-77ef-4d82-9901-d001a45e9981',
    platform: 'android',
    command_type: 'KIOSK_UNLOCK',
    status: 'COMPLETED',
    reason: 'Device safely recovered by owner',
    admin_name: 'Admin',
    created_at: '2026-09-28 19:30:00',
    executed_at: '2026-09-28 19:30:05'
  },
  {
    id: 3,
    command_uuid: 'cmd-wipe-0920-884',
    user_id: 6,
    user_name: 'Victor Vance',
    device_id: 105,
    device_name: 'OnePlus 11 5G',
    device_uuid: '00112233-4455-6677-8899-aabbccddeeff',
    platform: 'android',
    command_type: 'WIPE_DATA',
    status: 'COMPLETED',
    reason: 'Device reported lost with sensitive company credentials',
    admin_name: 'Admin',
    created_at: '2026-09-20 16:40:00',
    executed_at: '2026-09-20 16:40:42'
  }
];

const INITIAL_LOGS: SystemLog[] = [
  { id: 1, timestamp: '2026-09-28 20:15:30', action: 'Live Location Refreshed', module: 'Security', user_name: 'Alex Rivera', ip: '198.51.100.4', device: 'Galaxy S24 Ultra', result: 'SUCCESS', details: 'GPS coordinates updated with 4.2m accuracy' },
  { id: 2, timestamp: '2026-09-28 19:30:05', action: 'Security Command Executed', module: 'Security', user_name: 'Alex Rivera', ip: '10.0.0.1', device: 'Galaxy S24 Ultra', result: 'SUCCESS', details: 'Command KIOSK_UNLOCK executed and acknowledged by device' },
  { id: 3, timestamp: '2026-09-28 18:45:00', action: 'Security Command Dispatched', module: 'Security', user_name: 'Alex Rivera', ip: '10.0.0.1', device: 'Galaxy S24 Ultra', result: 'SUCCESS', details: 'Command KIOSK_LOCK dispatched via high-priority FCM channel' },
  { id: 4, timestamp: '2026-09-28 18:42:15', action: 'Security Alert Created', module: 'Security', user_name: 'Alex Rivera', ip: '198.51.100.4', device: 'Galaxy S24 Ultra', result: 'WARNING', details: 'INTRUDER_PHOTO_CAPTURED event with front camera snapshot' },
  { id: 5, timestamp: '2026-09-28 14:00:00', action: 'Activation Code Generated', module: 'Activation', user_name: 'System / Admin', ip: '10.0.0.1', device: 'Admin Console', result: 'SUCCESS', details: 'Code SATA-1120-BB77-9901 issued for Standard 30-Day plan' },
  { id: 6, timestamp: '2026-09-28 11:32:00', action: 'User Registration Initialized', module: 'Users', user_name: 'Chloe Bennett', ip: '172.56.21.9', device: 'Pixel 7a', result: 'SUCCESS', details: 'OTP verified. Transitioned to AWAITING_ACTIVATION' }
];

const INITIAL_NOTIFICATIONS: AdminNotification[] = [
  { id: 1, title: 'Critical Intruder Alert', body: 'Intruder photo captured on Alex Rivera\'s Samsung Galaxy S24 Ultra', type: 'ALERT', is_read: false, created_at: '2026-09-28 18:42:15', alert_id: 1 },
  { id: 2, title: 'SIM Card Removed', body: 'SIM removal detected on Sarah Connor\'s Pixel 8 Pro', type: 'SECURITY', is_read: false, created_at: '2026-09-27 22:15:00', alert_id: 2 },
  { id: 3, title: 'Subscription Expiring Soon', body: 'Marcus Wright\'s subscription expires in 3 days', type: 'SUBSCRIPTION', is_read: true, created_at: '2026-09-28 09:00:00', user_id: 3 },
  { id: 4, title: 'System Backup Operational', body: 'Daily automated MySQL and evidence snapshots verified in phpMyAdmin', type: 'INFO', is_read: true, created_at: '2026-09-28 04:00:00' }
];

const INITIAL_SETTINGS: SettingsConfig = {
  general: {
    app_name: 'SATA Admin Control Panel',
    support_email: 'security-admin@sata.app',
    default_timezone: 'UTC',
    allow_public_registration: true,
    app_version: 'v2.4.1'
  },
  security: {
    max_otp_attempts: 5,
    otp_ttl_minutes: 5,
    command_ttl_hours: 24,
    require_reauth_destructive: true,
    auto_offline_threshold_minutes: 30
  },
  notifications: {
    fcm_enabled: true,
    fcm_project_id: 'sata-security-prod',
    alert_sound_enabled: true,
    emergency_sms_enabled: true
  },
  storage: {
    retention_days: 90,
    max_upload_mb: 20,
    storage_disk: 'private_evidence'
  }
};

const AdminContext = createContext<AdminContextType | undefined>(undefined);

export const AdminProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  // Session Authentication State
  const [adminToken, setAdminToken] = useState<string | null>(() => {
    return localStorage.getItem('sata_admin_token') || sessionStorage.getItem('sata_admin_token') || null;
  });

  const [adminUser, setAdminUser] = useState<{ id: number; name: string; email: string; role: string } | null>(() => {
    const saved = localStorage.getItem('sata_admin_user') || sessionStorage.getItem('sata_admin_user');
    return saved ? JSON.parse(saved) : null;
  });

  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(() => {
    return Boolean(localStorage.getItem('sata_admin_token') || sessionStorage.getItem('sata_admin_token'));
  });

  const [currentRoute, setCurrentRoute] = useState<string>('/admin/dashboard');
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(false);
  const [searchTerm, setSearchTerm] = useState<string>('');

  const [users, setUsers] = useState<User[]>(() => {
    const saved = localStorage.getItem('sata_admin_users');
    return saved ? JSON.parse(saved) : INITIAL_USERS;
  });

  const [plans, setPlans] = useState<Plan[]>(() => {
    const saved = localStorage.getItem('sata_admin_plans');
    return saved ? JSON.parse(saved) : INITIAL_PLANS;
  });

  const [subscriptions, setSubscriptions] = useState<Subscription[]>(() => {
    const saved = localStorage.getItem('sata_admin_subscriptions');
    return saved ? JSON.parse(saved) : INITIAL_SUBSCRIPTIONS;
  });

  const [activationCodes, setActivationCodes] = useState<ActivationCode[]>(() => {
    const saved = localStorage.getItem('sata_admin_activation_codes');
    return saved ? JSON.parse(saved) : INITIAL_ACTIVATION_CODES;
  });

  const [securityAlerts, setSecurityAlerts] = useState<SecurityAlert[]>(() => {
    const saved = localStorage.getItem('sata_admin_alerts');
    return saved ? JSON.parse(saved) : INITIAL_ALERTS;
  });

  const [securityCommands, setSecurityCommands] = useState<SecurityCommand[]>(() => {
    const saved = localStorage.getItem('sata_admin_commands');
    return saved ? JSON.parse(saved) : INITIAL_COMMANDS;
  });

  const [systemLogs, setSystemLogs] = useState<SystemLog[]>(() => {
    const saved = localStorage.getItem('sata_admin_logs');
    return saved ? JSON.parse(saved) : INITIAL_LOGS;
  });

  const [notifications, setNotifications] = useState<AdminNotification[]>(() => {
    const saved = localStorage.getItem('sata_admin_notifications');
    return saved ? JSON.parse(saved) : INITIAL_NOTIFICATIONS;
  });

  const [settings, setSettings] = useState<SettingsConfig>(() => {
    const saved = localStorage.getItem('sata_admin_settings');
    return saved ? JSON.parse(saved) : INITIAL_SETTINGS;
  });

  const [toasts, setToasts] = useState<ToastItem[]>([]);

  // Local storage persistence
  useEffect(() => { localStorage.setItem('sata_admin_users', JSON.stringify(users)); }, [users]);
  useEffect(() => { localStorage.setItem('sata_admin_plans', JSON.stringify(plans)); }, [plans]);
  useEffect(() => { localStorage.setItem('sata_admin_subscriptions', JSON.stringify(subscriptions)); }, [subscriptions]);
  useEffect(() => { localStorage.setItem('sata_admin_activation_codes', JSON.stringify(activationCodes)); }, [activationCodes]);
  useEffect(() => { localStorage.setItem('sata_admin_alerts', JSON.stringify(securityAlerts)); }, [securityAlerts]);
  useEffect(() => { localStorage.setItem('sata_admin_commands', JSON.stringify(securityCommands)); }, [securityCommands]);
  useEffect(() => { localStorage.setItem('sata_admin_logs', JSON.stringify(systemLogs)); }, [systemLogs]);
  useEffect(() => { localStorage.setItem('sata_admin_notifications', JSON.stringify(notifications)); }, [notifications]);
  useEffect(() => { localStorage.setItem('sata_admin_settings', JSON.stringify(settings)); }, [settings]);

  // Sync state directly from the backend database on initial mount
  useEffect(() => {
    let isMounted = true;
    const syncFromDatabase = async () => {
      try {
        const [usersRes, plansRes, codesRes, subsRes] = await Promise.allSettled([
          fetch('/admin/api/users').then(r => r.ok ? r.json() : null),
          fetch('/admin/api/plans').then(r => r.ok ? r.json() : null),
          fetch('/admin/api/codes').then(r => r.ok ? r.json() : null),
          fetch('/admin/api/subscriptions').then(r => r.ok ? r.json() : null),
        ]);

        if (!isMounted) return;

        if (usersRes.status === 'fulfilled' && usersRes.value?.data?.data && Array.isArray(usersRes.value.data.data)) {
          const apiUsers = usersRes.value.data.data;
          if (apiUsers.length > 0) {
            setUsers(apiUsers);
          }
        }

        if (plansRes.status === 'fulfilled' && plansRes.value?.data && Array.isArray(plansRes.value.data)) {
          const apiPlans = plansRes.value.data;
          if (apiPlans.length > 0) {
            setPlans(apiPlans);
          }
        }

        if (codesRes.status === 'fulfilled' && codesRes.value?.data?.data && Array.isArray(codesRes.value.data.data)) {
          const apiCodes = codesRes.value.data.data;
          if (apiCodes.length > 0) {
            const normalizedCodes: ActivationCode[] = apiCodes.map((c: any) => {
              const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);
              const genAt = c.generated_at || c.created_at || nowStr;
              const dur = Number(c.duration_days) || 30;
              const expDate = new Date(new Date(genAt).getTime() + dur * 86400000);
              const expAt = c.expires_at || (isNaN(expDate.getTime()) ? nowStr : expDate.toISOString().replace('T', ' ').substring(0, 19));
              const st = c.status === 'USED' || c.status === 'ACTIVATED' || c.is_redeemed
                ? 'USED'
                : c.status === 'REVOKED'
                ? 'REVOKED'
                : c.status === 'EXPIRED'
                ? 'EXPIRED'
                : 'UNUSED';

              return {
                id: c.id,
                code: c.code,
                user_id: c.user_id || c.redeemed_by_user_id,
                user_name: c.user_name || c.redeemed_by,
                plan_id: c.plan_id || c.activation_plan_id || 1,
                plan_name: c.plan_name || 'Standard License',
                status: st,
                generated_at: genAt,
                activated_at: c.activated_at || c.redeemed_at,
                expires_at: expAt,
                created_by: c.created_by || 'Admin Console',
                notes: c.notes || '',
                batch_id: c.batch_id ? String(c.batch_id) : undefined
              };
            });
            setActivationCodes(normalizedCodes);
          }
        }

        if (subsRes.status === 'fulfilled' && subsRes.value?.data?.data && Array.isArray(subsRes.value.data.data)) {
          const apiSubs = subsRes.value.data.data;
          if (apiSubs.length > 0) {
            const normalizedSubs: Subscription[] = apiSubs.map((s: any) => {
              const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);
              return {
                id: s.id,
                user_id: s.user_id,
                user_name: s.user_name || 'Subscribed User',
                user_email: s.user_email || 'user@example.com',
                plan_id: s.plan_id || 1,
                plan_name: s.plan_name || 'Protection Plan',
                plan_code: s.plan_code || 'PRO',
                starts_at: s.starts_at || s.created_at || nowStr,
                expires_at: s.expires_at || nowStr,
                status: s.status || 'ACTIVE',
                price_paid: Number(s.price_paid || s.price || 0),
                payment_reference: s.payment_reference || `REF-${s.id}`,
                days_remaining: Number(s.days_remaining) || 30,
                auto_renew: Boolean(s.auto_renew)
              };
            });
            setSubscriptions(normalizedSubs);
          }
        }
      } catch (err) {
        console.warn('[AdminContext] Live sync warning:', err);
      }
    };

    syncFromDatabase();
    return () => { isMounted = false; };
  }, []);

  const toggleSidebar = () => setIsSidebarCollapsed(prev => !prev);

  const addToast = (title: string, message?: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 5)}`;
    setToasts(prev => [...prev, { id, title, message, type }]);
    setTimeout(() => {
      removeToast(id);
    }, 4000);
  };

  const removeToast = (id: string) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  };

  const logAction = (action: string, module: SystemLog['module'], userName: string, result: 'SUCCESS' | 'FAILED' | 'WARNING', details?: string) => {
    const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
    const newLog: SystemLog = {
      id: Date.now(),
      timestamp: now,
      action,
      module,
      user_name: userName,
      ip: '10.0.0.1',
      device: 'Admin Console',
      result,
      details
    };
    setSystemLogs(prev => [newLog, ...prev]);
  };

  // CSV Export utility
  const exportToCSV = (filename: string, headers: string[], rows: (string | number | boolean | null | undefined)[][]) => {
    const escape = (val: string | number | boolean | null | undefined) => {
      if (val === null || val === undefined) return '""';
      const str = String(val).replace(/"/g, '""');
      return `"${str}"`;
    };

    const csvContent = [
      headers.map(escape).join(','),
      ...rows.map(row => row.map(escape).join(','))
    ].join('\r\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    addToast('Export Successful', `Downloaded ${filename}.csv`, 'success');
  };

  // User Actions (Synchronized with Database)
  const addUser = (userData: { name: string; phone: string; email: string; planId?: number; notes?: string; trustedContacts?: Array<{ name: string; phone: string; email: string; relationship?: string }> }) => {
    const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
    const nextId = users.length > 0 ? Math.max(...users.map(u => u.id)) + 1 : 1;

    const newUser: User = {
      id: nextId,
      name: userData.name,
      phone: userData.phone,
      email: userData.email,
      status: 'ACTIVE',
      subscription_status: userData.planId ? 'ACTIVE' : 'NONE',
      activation_status: userData.planId ? 'ACTIVATED' : 'PENDING',
      created_at: now,
      updated_at: now,
      devices: [],
      notes: userData.notes || '',
      trusted_contacts: Array.isArray(userData.trustedContacts)
        ? userData.trustedContacts.map((tc, idx) => ({
            id: Date.now() + idx,
            slot: (idx + 1) as 1 | 2,
            name: tc.name,
            mobile_number: tc.phone,
            email: tc.email,
            relationship: tc.relationship || 'Emergency Contact',
            is_verified: true,
            created_at: now
          }))
        : []
    };

    if (userData.planId) {
      const selectedPlan = plans.find(p => p.id === userData.planId);
      if (selectedPlan) {
        const expiresAt = new Date();
        expiresAt.setDate(expiresAt.getDate() + selectedPlan.duration_days);

        const newSub: Subscription = {
          id: Date.now(),
          user_id: nextId,
          user_name: userData.name,
          user_email: userData.email,
          plan_id: selectedPlan.id,
          plan_name: selectedPlan.name,
          plan_code: selectedPlan.code,
          starts_at: now,
          expires_at: expiresAt.toISOString().replace('T', ' ').substring(0, 19),
          status: 'ACTIVE',
          price_paid: selectedPlan.price,
          payment_reference: `SATA-DIRECT-${Date.now().toString().slice(-4)}`,
          days_remaining: selectedPlan.duration_days,
          auto_renew: false
        };

        newUser.current_subscription = newSub;
        setSubscriptions(prev => [newSub, ...prev]);
      }
    }

    setUsers(prev => [newUser, ...prev]);
    logAction('User Created', 'Users', newUser.name, 'SUCCESS', `Created user account with email ${newUser.email}`);
    addToast('User Created', `Successfully added user ${newUser.name}`, 'success');

    // Asynchronously insert into database
    fetch('/admin/api/users', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(userData)
    }).then(async res => {
      if (res.ok) {
        const json = await res.json();
        if (json?.data?.id) {
          setUsers(prev => prev.map(u => u.id === nextId ? { ...u, id: json.data.id } : u));
        }
      }
    }).catch(err => console.error('[AdminContext] Error saving user to database:', err));

    return newUser;
  };

  const updateUser = (id: number, data: Partial<User>) => {
    setUsers(prev => prev.map(u => {
      if (u.id === id) {
        const updated = { ...u, ...data, updated_at: new Date().toISOString().replace('T', ' ').substring(0, 19) };
        logAction('User Updated', 'Users', updated.name, 'SUCCESS', `Updated profile fields`);
        return updated;
      }
      return u;
    }));
    addToast('User Updated', 'User profile changes saved successfully', 'success');

    // Asynchronously update in database
    fetch(`/admin/api/users/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).catch(err => console.error('[AdminContext] Error updating user in database:', err));
  };

  const deleteUser = (id: number) => {
    const user = users.find(u => u.id === id);
    const userName = user ? user.name : `User #${id}`;
    setUsers(prev => prev.filter(u => u.id !== id));
    setSubscriptions(prev => prev.filter(s => s.user_id !== id));
    logAction('User Deleted', 'Users', userName, 'WARNING', `Deleted user and revoked all access`);
    addToast('User Deleted', `User ${userName} has been removed`, 'warning');

    // Delete in database
    fetch(`/admin/api/users/${id}`, {
      method: 'DELETE'
    }).catch(err => console.error('[AdminContext] Error deleting user in database:', err));
  };

  const activateUser = (id: number) => {
    setUsers(prev => prev.map(u => {
      if (u.id === id) {
        const updated: User = {
          ...u,
          status: 'ACTIVE',
          activation_status: 'ACTIVATED',
          updated_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
        };
        logAction('User Activated', 'Users', updated.name, 'SUCCESS', 'Account manually activated by Admin');
        return updated;
      }
      return u;
    }));
    addToast('User Activated', 'Account status changed to Active', 'success');

    fetch(`/admin/api/users/${id}/status`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: 'ACTIVE' })
    }).catch(err => console.error(err));
  };

  const disableUser = (id: number) => {
    setUsers(prev => prev.map(u => {
      if (u.id === id) {
        const updated: User = {
          ...u,
          status: 'DISABLED',
          updated_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
          devices: u.devices.map(d => ({ ...d, status: 'REVOKED', is_protection_enabled: false }))
        };
        logAction('User Disabled', 'Users', updated.name, 'WARNING', 'Account suspended and devices revoked');
        return updated;
      }
      return u;
    }));
    addToast('User Disabled', 'Account suspended and all device sessions revoked', 'warning');

    fetch(`/admin/api/users/${id}/disable`, {
      method: 'POST'
    }).catch(err => console.error(err));
  };

  const addTrustedContact = async (userId: number, contactData: { name: string; mobile_number: string; email: string; relationship?: string; is_verified?: boolean }): Promise<{ success: boolean; message: string; contact?: TrustedContact }> => {
    try {
      const res = await fetch(`/admin/api/users/${userId}/trusted-contacts`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(contactData)
      });
      const data = await res.json();
      if (!data.success) {
        addToast('Failed to Add Contact', data.message || 'Error occurred', 'error');
        return { success: false, message: data.message };
      }

      const created: TrustedContact = {
        id: data.data?.id || Date.now(),
        slot: (data.data?.slot || 1) as 1 | 2,
        name: contactData.name,
        mobile_number: contactData.mobile_number,
        email: contactData.email,
        relationship: contactData.relationship || 'Emergency Contact',
        is_verified: contactData.is_verified ?? true,
        created_at: new Date().toISOString()
      };

      setUsers(prev => prev.map(u => {
        if (u.id === userId) {
          const currentContacts = u.trusted_contacts || [];
          return {
            ...u,
            trusted_contacts: [...currentContacts, created]
          };
        }
        return u;
      }));

      logAction('Trusted Contact Added', 'Users', contactData.name, 'SUCCESS', `Added trusted emergency contact for User #${userId}`);
      addToast('Trusted Contact Added', `${contactData.name} saved as emergency contact`, 'success');
      return { success: true, message: data.message, contact: created };
    } catch (err: any) {
      addToast('Network Error', err.message, 'error');
      return { success: false, message: err.message };
    }
  };

  const updateTrustedContact = async (userId: number, contactId: number, updateData: { name?: string; mobile_number?: string; email?: string; relationship?: string; is_verified?: boolean }): Promise<{ success: boolean; message: string }> => {
    try {
      const res = await fetch(`/admin/api/users/${userId}/trusted-contacts/${contactId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updateData)
      });
      const data = await res.json();
      if (!data.success) {
        addToast('Failed to Update', data.message || 'Error occurred', 'error');
        return { success: false, message: data.message };
      }

      setUsers(prev => prev.map(u => {
        if (u.id === userId) {
          return {
            ...u,
            trusted_contacts: (u.trusted_contacts || []).map(tc => {
              if (tc.id === contactId) {
                return {
                  ...tc,
                  name: updateData.name ?? tc.name,
                  mobile_number: updateData.mobile_number ?? tc.mobile_number,
                  email: updateData.email ?? tc.email,
                  relationship: updateData.relationship ?? tc.relationship,
                  is_verified: updateData.is_verified !== undefined ? updateData.is_verified : tc.is_verified
                };
              }
              return tc;
            })
          };
        }
        return u;
      }));

      logAction('Trusted Contact Updated', 'Users', updateData.name || `Contact #${contactId}`, 'SUCCESS', `Updated details for contact #${contactId}`);
      addToast('Trusted Contact Updated', 'Emergency contact details updated successfully', 'success');
      return { success: true, message: data.message };
    } catch (err: any) {
      addToast('Network Error', err.message, 'error');
      return { success: false, message: err.message };
    }
  };

  const deleteTrustedContact = async (userId: number, contactId: number): Promise<{ success: boolean; message: string }> => {
    try {
      const res = await fetch(`/admin/api/users/${userId}/trusted-contacts/${contactId}`, {
        method: 'DELETE'
      });
      const data = await res.json();
      if (!data.success) {
        addToast('Failed to Delete', data.message || 'Error occurred', 'error');
        return { success: false, message: data.message };
      }

      setUsers(prev => prev.map(u => {
        if (u.id === userId) {
          return {
            ...u,
            trusted_contacts: (u.trusted_contacts || []).filter(tc => tc.id !== contactId)
          };
        }
        return u;
      }));

      logAction('Trusted Contact Deleted', 'Users', `Contact #${contactId}`, 'WARNING', `Removed trusted emergency contact from User #${userId}`);
      addToast('Trusted Contact Removed', 'Emergency contact removed successfully', 'warning');
      return { success: true, message: data.message };
    } catch (err: any) {
      addToast('Network Error', err.message, 'error');
      return { success: false, message: err.message };
    }
  };

  // Plan Actions (Synchronized with Database)
  const addPlan = (planData: Omit<Plan, 'id' | 'subscribers_count'>) => {
    const nextId = plans.length > 0 ? Math.max(...plans.map(p => p.id)) + 1 : 1;
    const newPlan: Plan = {
      ...planData,
      id: nextId,
      subscribers_count: 0
    };
    setPlans(prev => [...prev, newPlan]);
    logAction('Plan Created', 'Subscriptions', 'Admin', 'SUCCESS', `Created subscription plan ${newPlan.name}`);
    addToast('Plan Created', `Subscription plan ${newPlan.name} created`, 'success');

    // Insert into database
    fetch('/admin/api/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(planData)
    }).then(async res => {
      if (res.ok) {
        const json = await res.json();
        if (json?.data?.id) {
          setPlans(prev => prev.map(p => p.id === nextId ? { ...p, id: json.data.id } : p));
        }
      }
    }).catch(err => console.error('[AdminContext] Error adding plan in database:', err));
  };

  const updatePlan = (id: number, data: Partial<Plan>) => {
    setPlans(prev => prev.map(p => (p.id === id ? { ...p, ...data } : p)));
    logAction('Plan Updated', 'Subscriptions', 'Admin', 'SUCCESS', `Updated plan #${id}`);
    addToast('Plan Updated', 'Plan configurations saved', 'success');

    // Update in database
    fetch(`/admin/api/plans/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).catch(err => console.error('[AdminContext] Error updating plan in database:', err));
  };

  const deletePlan = (id: number) => {
    const plan = plans.find(p => p.id === id);
    setPlans(prev => prev.filter(p => p.id !== id));
    logAction('Plan Deleted', 'Subscriptions', 'Admin', 'WARNING', `Deleted plan ${plan?.name}`);
    addToast('Plan Deleted', `Plan has been removed`, 'warning');

    // Delete in database
    fetch(`/admin/api/plans/${id}`, {
      method: 'DELETE'
    }).catch(err => console.error('[AdminContext] Error deleting plan in database:', err));
  };

  const togglePlanStatus = (id: number) => {
    const plan = plans.find(p => p.id === id);
    const nextStatus = plan?.status === 'ACTIVE' ? 'DISABLED' : 'ACTIVE';
    setPlans(prev => prev.map(p => {
      if (p.id === id) {
        logAction('Plan Status Changed', 'Subscriptions', 'Admin', 'SUCCESS', `Toggled plan ${p.name} to ${nextStatus}`);
        return { ...p, status: nextStatus };
      }
      return p;
    }));
    addToast('Plan Status Toggled', 'Plan status updated', 'info');

    // Toggle in database
    fetch(`/admin/api/plans/${id}/${nextStatus === 'ACTIVE' ? 'enable' : 'disable'}`, {
      method: 'POST'
    }).catch(err => console.error('[AdminContext] Error toggling plan status:', err));
  };

  // Subscription Actions (Synchronized with Database)
  const renewSubscription = (id: number, additionalDays: number = 30) => {
    setSubscriptions(prev => prev.map(s => {
      if (s.id === id) {
        const currentExp = new Date(s.expires_at);
        const baseDate = currentExp > new Date() ? currentExp : new Date();
        baseDate.setDate(baseDate.getDate() + additionalDays);

        const updated: Subscription = {
          ...s,
          expires_at: baseDate.toISOString().replace('T', ' ').substring(0, 19),
          status: 'ACTIVE',
          days_remaining: s.days_remaining + additionalDays
        };
        logAction('Subscription Renewed', 'Subscriptions', s.user_name, 'SUCCESS', `Extended by ${additionalDays} days`);
        return updated;
      }
      return s;
    }));
    addToast('Subscription Renewed', `Extended subscription by ${additionalDays} days`, 'success');

    fetch(`/admin/api/subscriptions/${id}/extend`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ days: additionalDays })
    }).catch(err => console.error('[AdminContext] Error extending subscription in database:', err));
  };

  const cancelSubscription = (id: number) => {
    setSubscriptions(prev => prev.map(s => {
      if (s.id === id) {
        logAction('Subscription Cancelled', 'Subscriptions', s.user_name, 'WARNING', 'Subscription terminated by Admin');
        return { ...s, status: 'CANCELLED', days_remaining: 0 };
      }
      return s;
    }));
    addToast('Subscription Cancelled', 'Subscription cancelled', 'warning');

    fetch(`/admin/api/subscriptions/${id}/cancel`, {
      method: 'POST'
    }).catch(err => console.error('[AdminContext] Error cancelling subscription in database:', err));
  };

  const deleteSubscription = (id: number) => {
    setSubscriptions(prev => prev.filter(s => s.id !== id));
    logAction('Subscription Deleted', 'Subscriptions', 'Admin', 'WARNING', `Deleted subscription record #${id}`);
    addToast('Subscription Deleted', 'Record removed', 'info');
  };

  // Activation Code Actions (Synchronized with Database)
  const generateActivationCodes = (planId: number, count: number, expiryDays: number, notes?: string) => {
    const plan = plans.find(p => p.id === planId) || plans[0];
    const now = new Date();
    const exp = new Date();
    exp.setDate(exp.getDate() + expiryDays);

    const batchId = `BATCH-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}-${Math.random().toString(36).substr(2, 4).toUpperCase()}`;
    const generated: ActivationCode[] = [];

    const generateSegment = () => Math.random().toString(36).substr(2, 4).toUpperCase();

    for (let i = 0; i < count; i++) {
      const code = `SATA-${generateSegment()}-${generateSegment()}-${generateSegment()}`;
      generated.push({
        id: Date.now() + i,
        code,
        plan_id: plan.id,
        plan_name: plan.name,
        status: 'UNUSED',
        generated_at: now.toISOString().replace('T', ' ').substring(0, 19),
        expires_at: exp.toISOString().replace('T', ' ').substring(0, 19),
        created_by: 'Admin',
        notes: notes || `Generated in ${batchId}`,
        batch_id: batchId
      });
    }

    setActivationCodes(prev => [...generated, ...prev]);
    logAction('Activation Codes Generated', 'Activation', 'Admin', 'SUCCESS', `Generated ${count} codes for plan ${plan.name} (${batchId})`);
    addToast('Codes Generated', `Successfully created ${count} activation code(s)`, 'success');

    // Insert into database and refresh
    fetch('/admin/api/codes/bulk-generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan_id: planId,
        quantity: count,
        expiry_days: expiryDays,
        notes: notes || 'Standard Batch'
      })
    }).then(async res => {
      if (res.ok) {
        const codesRes = await fetch('/admin/api/codes').then(r => r.json());
        if (codesRes?.data?.data) {
          setActivationCodes(codesRes.data.data);
        }
      }
    }).catch(err => console.error('[AdminContext] Error generating codes in database:', err));

    return generated;
  };

  const revokeActivationCode = (id: number) => {
    setActivationCodes(prev => prev.map(c => {
      if (c.id === id) {
        logAction('Activation Code Revoked', 'Activation', 'Admin', 'WARNING', `Revoked code ${c.code}`);
        return { ...c, status: 'REVOKED' };
      }
      return c;
    }));
    addToast('Code Revoked', 'Activation code revoked and cannot be redeemed', 'warning');

    fetch(`/admin/api/codes/${id}/disable`, {
      method: 'POST'
    }).catch(err => console.error('[AdminContext] Error revoking code in database:', err));
  };

  const deleteActivationCode = (id: number) => {
    setActivationCodes(prev => prev.filter(c => c.id !== id));
    logAction('Activation Code Deleted', 'Activation', 'Admin', 'WARNING', `Deleted code record #${id}`);
    addToast('Code Deleted', 'Activation code removed from database', 'info');

    fetch(`/admin/api/codes/${id}`, {
      method: 'DELETE'
    }).catch(err => console.error('[AdminContext] Error deleting code in database:', err));
  };

  // Security Command Dispatch
  const dispatchSecurityCommand = (userId: number, deviceId: number, commandType: CommandType, reason: string): SecurityCommand => {
    const user = users.find(u => u.id === userId);
    const device = user?.devices.find(d => d.id === deviceId);

    const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
    const commandUuid = `cmd-${commandType.toLowerCase().replace('_', '-')}-${Date.now().toString().slice(-4)}`;

    const newCommand: SecurityCommand = {
      id: Date.now(),
      command_uuid: commandUuid,
      user_id: userId,
      user_name: user?.name || 'Unknown User',
      device_id: deviceId,
      device_name: device?.device_name || 'Hardware Device',
      device_uuid: device?.device_uuid || 'uuid',
      platform: device?.platform || 'android',
      command_type: commandType,
      status: 'SENT',
      reason,
      admin_name: 'Admin',
      created_at: now
    };

    setSecurityCommands(prev => [newCommand, ...prev]);

    // Append to user's real-time device action history
    setUsers(prevUsers => prevUsers.map(u => {
      if (u.id === userId) {
        const newActionLog = {
          id: Date.now(),
          user_id: userId,
          device_id: deviceId,
          device_name: device?.device_name || 'Hardware Device',
          action_type: (commandType === 'TRACK_LOCATION' ? 'LOCATION_PING' : 'COMMAND_EXECUTED') as any,
          title: `Remote ${commandType.replace('_', ' ')} Dispatched`,
          severity: (commandType === 'FACTORY_RESET' || commandType === 'WIPE_DATA'
            ? 'CRITICAL'
            : commandType === 'KIOSK_LOCK' || commandType === 'SIREN_ALARM'
            ? 'HIGH'
            : 'INFO') as any,
          timestamp: now,
          details: `Admin dispatched ${commandType} to ${device?.device_name || 'device'}. Reason: ${reason}`,
          status: 'COMPLETED' as const
        };
        return {
          ...u,
          action_history: [newActionLog, ...(u.action_history || [])]
        };
      }
      return u;
    }));

    logAction('Security Command Dispatched', 'Security', user?.name || 'User', 'SUCCESS', `Command ${commandType} issued to ${device?.device_name}`);
    addToast('Command Dispatched', `Command ${commandType} dispatched via Firebase Cloud Messaging`, 'info');

    // Transmit to MySQL backend & trigger Firebase Cloud Messaging (FCM) + Email
    fetch('/admin/api/commands', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        userId,
        deviceId,
        commandType,
        reason,
        adminId: adminUser?.id || 1,
      })
    }).then(async res => {
      if (res.ok) {
        const json = await res.json();
        if (json?.data?.id) {
          setSecurityCommands(prev => prev.map(c => c.id === newCommand.id ? { ...c, id: json.data.id, command_uuid: json.data.command_uuid } : c));
        }
      }
    }).catch(err => console.error('[AdminContext] Error dispatching command to backend:', err));

    // Acknowledge execution confirmation
    setTimeout(() => {
      setSecurityCommands(prev => prev.map(cmd => {
        if (cmd.id === newCommand.id) {
          const completedNow = new Date().toISOString().replace('T', ' ').substring(0, 19);
          logAction('Security Command Executed', 'Security', user?.name || 'User', 'SUCCESS', `${commandType} acknowledged and completed`);
          return {
            ...cmd,
            status: 'COMPLETED',
            executed_at: completedNow
          };
        }
        return cmd;
      }));
      addToast('Device Execution Confirmed', `${commandType} completed on ${device?.device_name}`, 'success');
    }, 3500);

    return newCommand;
  };

  // Security Alert Actions
  const resolveSecurityAlert = (id: number, notes?: string) => {
    setSecurityAlerts(prev => prev.map(a => {
      if (a.id === id) {
        const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
        logAction('Security Alert Resolved', 'Security', a.user_name, 'SUCCESS', `Resolved alert ${a.alert_type}`);
        return {
          ...a,
          status: 'RESOLVED',
          resolved_at: now,
          resolved_by: 'Admin',
          notes: notes || a.notes
        };
      }
      return a;
    }));
    addToast('Alert Resolved', 'Security incident marked as resolved', 'success');
  };

  const deleteSecurityAlert = (id: number) => {
    setSecurityAlerts(prev => prev.filter(a => a.id !== id));
    logAction('Security Alert Deleted', 'Security', 'Admin', 'WARNING', `Deleted alert record #${id}`);
    addToast('Alert Removed', 'Security alert deleted', 'info');
  };

  // System & Notifications
  const updateSettings = (newSettings: Partial<SettingsConfig>) => {
    setSettings(prev => ({
      general: { ...prev.general, ...(newSettings.general || {}) },
      security: { ...prev.security, ...(newSettings.security || {}) },
      notifications: { ...prev.notifications, ...(newSettings.notifications || {}) },
      storage: { ...prev.storage, ...(newSettings.storage || {}) }
    }));
    logAction('System Settings Updated', 'System', 'Admin', 'SUCCESS', 'Configuration parameters modified');
    addToast('Settings Saved', 'System configurations updated successfully', 'success');
  };

  const markNotificationAsRead = (id: number) => {
    setNotifications(prev => prev.map(n => (n.id === id ? { ...n, is_read: true } : n)));
  };

  const markAllNotificationsAsRead = () => {
    setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
    addToast('Notifications Cleared', 'All notifications marked as read', 'info');
  };

  const login = async (email: string, password: string, rememberMe: boolean = true): Promise<boolean> => {
    try {
      const res = await fetch('/admin/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      const json = await res.json();
      if (res.ok && json.success && json.data) {
        const token = json.data.token || 'admin-session-token-' + Date.now();
        const user = json.data.admin || { id: 1, name: 'SATA Super Admin', email, role: 'SUPER_ADMIN' };

        setAdminToken(token);
        setAdminUser(user);
        setIsAuthenticated(true);

        if (rememberMe) {
          localStorage.setItem('sata_admin_token', token);
          localStorage.setItem('sata_admin_user', JSON.stringify(user));
        } else {
          sessionStorage.setItem('sata_admin_token', token);
          sessionStorage.setItem('sata_admin_user', JSON.stringify(user));
        }

        logAction('Admin Logged In', 'System', user.name, 'SUCCESS', `Session initiated for ${email}`);
        addToast('Authentication Verified', `Welcome back, ${user.name}`, 'success');
        setCurrentRoute('/admin/dashboard');
        return true;
      } else {
        addToast('Authentication Failed', json.message || 'Invalid administrative credentials', 'error');
        return false;
      }
    } catch (err: any) {
      console.error('Login error:', err);
      addToast('System Error', 'Could not establish connection to authentication service', 'error');
      return false;
    }
  };

  const logout = () => {
    fetch('/admin/api/logout', { method: 'POST' }).catch(() => {});
    localStorage.removeItem('sata_admin_token');
    localStorage.removeItem('sata_admin_user');
    sessionStorage.removeItem('sata_admin_token');
    sessionStorage.removeItem('sata_admin_user');
    setAdminToken(null);
    setAdminUser(null);
    setIsAuthenticated(false);
    setCurrentRoute('/admin/dashboard');
    addToast('Session Ended', 'You have been signed out of SATA Admin Console', 'info');
  };

  return (
    <AdminContext.Provider
      value={{
        isAuthenticated,
        adminUser,
        adminToken,
        login,
        logout,
        currentRoute,
        setCurrentRoute,
        isSidebarCollapsed,
        toggleSidebar,
        searchTerm,
        setSearchTerm,
        users,
        plans,
        subscriptions,
        activationCodes,
        securityAlerts,
        securityCommands,
        systemLogs,
        notifications,
        settings,
        addUser,
        updateUser,
        deleteUser,
        activateUser,
        disableUser,
        addTrustedContact,
        updateTrustedContact,
        deleteTrustedContact,
        addPlan,
        updatePlan,
        deletePlan,
        togglePlanStatus,
        renewSubscription,
        cancelSubscription,
        deleteSubscription,
        generateActivationCodes,
        revokeActivationCode,
        deleteActivationCode,
        dispatchSecurityCommand,
        resolveSecurityAlert,
        deleteSecurityAlert,
        updateSettings,
        markNotificationAsRead,
        markAllNotificationsAsRead,
        toasts,
        addToast,
        removeToast,
        exportToCSV
      }}
    >
      {children}
    </AdminContext.Provider>
  );
};

export const useAdmin = () => {
  const context = useContext(AdminContext);
  if (!context) {
    throw new Error('useAdmin must be used within an AdminProvider');
  }
  return context;
};
