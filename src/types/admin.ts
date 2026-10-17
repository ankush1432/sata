export type UserStatus = 'ACTIVE' | 'PENDING_OTP' | 'AWAITING_ACTIVATION' | 'DISABLED';
export type SubscriptionStatus = 'ACTIVE' | 'EXPIRING' | 'EXPIRED' | 'CANCELLED' | 'NONE';
export type ActivationStatus = 'ACTIVATED' | 'PENDING' | 'EXPIRED';
export type DevicePlatform = 'android' | 'ios';
export type DeviceStatus = 'ACTIVE' | 'REVOKED' | 'OFFLINE';
export type DeviceManagementMode = 'DEVICE_OWNER' | 'DEVICE_ADMIN' | 'STANDARD_APP' | 'SUPERVISED_MDM';

export interface Device {
  id: number;
  device_uuid: string;
  device_name: string;
  platform: DevicePlatform;
  os_version: string;
  app_version: string;
  status: DeviceStatus;
  is_protection_enabled: boolean;
  last_seen: string;
  battery_level?: number;
  is_charging?: boolean;
  network_type?: string;
  device_management_mode?: DeviceManagementMode;
  fcm_token?: string;
}

export interface TrustedContact {
  id: number;
  slot: 1 | 2;
  name: string;
  mobile_number: string;
  email: string; // Mandatory for intruder photo and location alerts
  relationship: string;
  is_verified: boolean;
  created_at: string;
}

export interface DeviceActionLog {
  id: number;
  user_id: number;
  device_id?: number;
  device_name?: string;
  action_type:
    | 'POWER_BUTTON_CLICK'
    | 'POWER_OFF_ATTEMPT'
    | 'SIM_REMOVED'
    | 'SIM_CHANGED'
    | 'WRONG_PIN_ATTEMPT'
    | 'CHARGER_DISCONNECTED'
    | 'POCKET_REMOVAL'
    | 'MOTION_TAMPER'
    | 'COMMAND_EXECUTED'
    | 'KIOSK_PINNED'
    | 'KIOSK_RELEASED'
    | 'LOCATION_PING'
    | 'INTRUDER_PHOTO_CAPTURED';
  title: string;
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW' | 'INFO';
  timestamp: string;
  details: string;
  location_summary?: string;
  ip_address?: string;
  status: 'BLOCKED' | 'FLAGGED' | 'COMPLETED' | 'ACKNOWLEDGED';
}

export interface UserLocation {
  latitude: number;
  longitude: number;
  accuracy: number;
  altitude?: number;
  speed?: number;
  bearing?: number;
  address?: string;
  last_updated: string;
  short_url?: string;
  has_active_session?: boolean;
}

export interface User {
  id: number;
  name: string;
  phone: string;
  email: string;
  avatar?: string;
  status: UserStatus;
  subscription_status: SubscriptionStatus;
  activation_status: ActivationStatus;
  created_at: string;
  updated_at: string;
  devices: Device[];
  trusted_contacts?: TrustedContact[];
  current_subscription?: Subscription;
  location?: UserLocation;
  action_history?: DeviceActionLog[];
  notes?: string;
}

export interface Plan {
  id: number;
  name: string;
  code: string;
  description: string;
  duration_days: number;
  price: number;
  currency: string;
  status: 'ACTIVE' | 'DISABLED';
  features: string[];
  subscribers_count: number;
}

export interface Subscription {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  plan_id: number;
  plan_name: string;
  plan_code: string;
  starts_at: string;
  expires_at: string;
  status: 'ACTIVE' | 'EXPIRING' | 'EXPIRED' | 'CANCELLED';
  price_paid: number;
  payment_reference: string;
  days_remaining: number;
  auto_renew?: boolean;
}

export interface ActivationCode {
  id: number;
  code: string;
  user_id?: number;
  user_name?: string;
  plan_id: number;
  plan_name: string;
  status: 'UNUSED' | 'USED' | 'EXPIRED' | 'REVOKED';
  generated_at: string;
  activated_at?: string;
  expires_at: string;
  created_by: string;
  notes?: string;
  batch_id?: string;
}

export type AlertType =
  | 'INTRUDER_PHOTO_CAPTURED'
  | 'WRONG_PIN_ATTEMPT'
  | 'SIM_CARD_REMOVED'
  | 'POWER_OFF_ATTEMPT'
  | 'MOTION_DETECTED'
  | 'GEOFENCE_BREACH';

export type AlertSeverity = 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';

export interface SecurityAlert {
  id: number;
  event_uuid: string;
  user_id: number;
  user_name: string;
  user_phone: string;
  alert_type: AlertType;
  severity: AlertSeverity;
  device_name: string;
  device_uuid: string;
  platform: DevicePlatform;
  location?: {
    latitude: number;
    longitude: number;
    address: string;
    accuracy: number;
  };
  evidence_url?: string;
  created_at: string;
  status: 'UNRESOLVED' | 'RESOLVED' | 'DISMISSED';
  resolved_at?: string;
  resolved_by?: string;
  notes?: string;
}

export type CommandType =
  | 'FACTORY_RESET'
  | 'KIOSK_LOCK'
  | 'KIOSK_UNLOCK'
  | 'WIPE_DATA'
  | 'SIREN_ALARM'
  | 'TRACK_LOCATION'
  | 'CAPTURE_PHOTO';
export type CommandStatus = 'PENDING' | 'SENT' | 'ACKNOWLEDGED' | 'COMPLETED' | 'FAILED' | 'EXPIRED';

export interface SecurityCommand {
  id: number;
  command_uuid: string;
  user_id: number;
  user_name: string;
  device_id: number;
  device_name: string;
  device_uuid: string;
  platform: DevicePlatform;
  command_type: CommandType;
  status: CommandStatus;
  reason: string;
  admin_name: string;
  created_at: string;
  executed_at?: string;
  failure_reason?: string;
}

export interface SystemLog {
  id: number;
  timestamp: string;
  action: string;
  module: 'Users' | 'Subscriptions' | 'Activation' | 'Security' | 'System' | 'Auth';
  user_name: string;
  ip: string;
  device: string;
  result: 'SUCCESS' | 'FAILED' | 'WARNING';
  details?: string;
}

export interface AdminNotification {
  id: number;
  title: string;
  body: string;
  type: 'ALERT' | 'INFO' | 'SECURITY' | 'SUBSCRIPTION';
  is_read: boolean;
  created_at: string;
  user_id?: number;
  alert_id?: number;
}

export interface SettingsConfig {
  general: {
    app_name: string;
    support_email: string;
    default_timezone: string;
    allow_public_registration: boolean;
    app_version: string;
  };
  security: {
    max_otp_attempts: number;
    otp_ttl_minutes: number;
    command_ttl_hours: number;
    require_reauth_destructive: boolean;
    auto_offline_threshold_minutes: number;
  };
  notifications: {
    fcm_enabled: boolean;
    fcm_project_id: string;
    alert_sound_enabled: boolean;
    emergency_sms_enabled: boolean;
  };
  storage: {
    retention_days: number;
    max_upload_mb: number;
    storage_disk: string;
  };
}
