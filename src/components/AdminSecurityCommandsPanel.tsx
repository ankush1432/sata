import React, { useState } from 'react';
import {
  ShieldAlert,
  Lock,
  Unlock,
  RotateCcw,
  Trash2,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  HelpCircle,
  Clock,
  KeyRound,
  Smartphone,
  User as UserIcon,
  ShieldCheck,
  ChevronRight,
  RefreshCw,
  Terminal,
  FileText
} from 'lucide-react';

export type CommandType = 'FACTORY_RESET' | 'KIOSK_LOCK' | 'KIOSK_UNLOCK' | 'WIPE_DATA';
export type CommandStatusType = 'PENDING' | 'SENT' | 'ACKNOWLEDGED' | 'EXECUTING' | 'COMPLETED' | 'FAILED' | 'EXPIRED' | 'UNSUPPORTED';

interface TargetDevice {
  id: number;
  device_uuid: string;
  device_name: string;
  platform: 'android' | 'ios';
  device_management_mode: 'DEVICE_OWNER' | 'DEVICE_ADMIN' | 'STANDARD_APP' | 'SUPERVISED_MDM';
  is_protection_enabled: boolean;
  status: 'ACTIVE' | 'REVOKED';
  user_id: number;
  user_name: string;
  user_email: string;
  subscription_status: 'ACTIVE' | 'EXPIRED';
}

interface CommandHistoryItem {
  id: number;
  command_uuid: string;
  command_type: CommandType;
  status: CommandStatusType;
  device_id: number;
  device_name: string;
  target_user_email: string;
  reason: string;
  admin_email: string;
  created_at: string;
  failure_reason?: string;
  device_management_mode: string;
  reauth_verified?: boolean;
}

const MOCK_DEVICES: TargetDevice[] = [
  {
    id: 101,
    device_uuid: 'dev-do-pixel8-001',
    device_name: 'Google Pixel 8 Pro (Enterprise Kiosk)',
    platform: 'android',
    device_management_mode: 'DEVICE_OWNER',
    is_protection_enabled: true,
    status: 'ACTIVE',
    user_id: 1,
    user_name: 'John Doe',
    user_email: 'john.doe@company.org',
    subscription_status: 'ACTIVE'
  },
  {
    id: 102,
    device_uuid: 'dev-std-galaxy-002',
    device_name: 'Samsung Galaxy S24 (Standard User App)',
    platform: 'android',
    device_management_mode: 'STANDARD_APP',
    is_protection_enabled: true,
    status: 'ACTIVE',
    user_id: 2,
    user_name: 'Sarah Connor',
    user_email: 'sarah.c@techcorp.io',
    subscription_status: 'ACTIVE'
  },
  {
    id: 103,
    device_uuid: 'dev-mdm-iphone15-003',
    device_name: 'Apple iPhone 15 Pro (Supervised MDM)',
    platform: 'ios',
    device_management_mode: 'SUPERVISED_MDM',
    is_protection_enabled: true,
    status: 'ACTIVE',
    user_id: 3,
    user_name: 'Marcus Wright',
    user_email: 'm.wright@executive.com',
    subscription_status: 'ACTIVE'
  },
  {
    id: 104,
    device_uuid: 'dev-revoked-oneplus-004',
    device_name: 'OnePlus 12 (Revoked Hardware)',
    platform: 'android',
    device_management_mode: 'DEVICE_OWNER',
    is_protection_enabled: false,
    status: 'REVOKED',
    user_id: 4,
    user_name: 'Alex Vance',
    user_email: 'alex.v@lab.org',
    subscription_status: 'ACTIVE'
  },
  {
    id: 105,
    device_uuid: 'dev-expired-sub-005',
    device_name: 'Xiaomi 14 (Expired Subscription)',
    platform: 'android',
    device_management_mode: 'DEVICE_OWNER',
    is_protection_enabled: false,
    status: 'ACTIVE',
    user_id: 5,
    user_name: 'David Mills',
    user_email: 'd.mills@investigations.net',
    subscription_status: 'EXPIRED'
  }
];

export const AdminSecurityCommandsPanel: React.FC = () => {
  const [selectedDevice, setSelectedDevice] = useState<TargetDevice>(MOCK_DEVICES[0]);
  const [activeModalCommand, setActiveModalCommand] = useState<CommandType | null>(null);
  const [reason, setReason] = useState<string>('');
  const [adminPassword, setAdminPassword] = useState<string>('');
  const [adminRole, setAdminRole] = useState<'SUPER_ADMIN' | 'ADMIN' | 'SUPPORT'>('SUPER_ADMIN');
  const [history, setHistory] = useState<CommandHistoryItem[]>([
    {
      id: 991,
      command_uuid: 'cmd-a94f8371-2026',
      command_type: 'KIOSK_LOCK',
      status: 'COMPLETED',
      device_id: 101,
      device_name: 'Google Pixel 8 Pro (Enterprise Kiosk)',
      target_user_email: 'john.doe@company.org',
      reason: 'Physical theft reported on premises',
      admin_email: 'superadmin@sata.security',
      created_at: '2026-09-24 07:15:20',
      device_management_mode: 'DEVICE_OWNER'
    },
    {
      id: 990,
      command_uuid: 'cmd-b11c4492-2026',
      command_type: 'FACTORY_RESET',
      status: 'UNSUPPORTED',
      device_id: 102,
      device_name: 'Samsung Galaxy S24 (Standard User App)',
      target_user_email: 'sarah.c@techcorp.io',
      reason: 'Standard app sandbox cannot wipe hardware partition',
      admin_email: 'superadmin@sata.security',
      created_at: '2026-09-24 06:42:10',
      failure_reason: 'Command FACTORY_RESET is UNSUPPORTED on platform ANDROID with mode STANDARD_APP. Never faked.',
      device_management_mode: 'STANDARD_APP'
    }
  ]);
  const [activeNotification, setActiveNotification] = useState<{
    type: 'success' | 'error' | 'warning';
    title: string;
    message: string;
    details?: string;
  } | null>(null);

  const isDestructive = (type: CommandType): boolean => {
    return type === 'FACTORY_RESET' || type === 'WIPE_DATA';
  };

  const handleOpenCommand = (type: CommandType) => {
    setActiveModalCommand(type);
    setReason('');
    setAdminPassword('');
    setActiveNotification(null);
  };

  const handleExecuteCommand = () => {
    if (!activeModalCommand) return;

    if (!reason.trim() || reason.trim().length < 3) {
      setActiveNotification({
        type: 'error',
        title: 'Validation Error',
        message: 'A detailed reason (min 3 characters) is required for audit compliance.'
      });
      return;
    }

    // Role check: Support admin cannot execute destructive commands
    if (adminRole === 'SUPPORT' && isDestructive(activeModalCommand)) {
      setActiveNotification({
        type: 'error',
        title: 'Authorization Forbidden (403)',
        message: 'SUPPORT role is strictly forbidden from destructive commands (Factory Reset / Wipe Data).',
        details: 'error_code: COMMAND_EXECUTION_FORBIDDEN'
      });
      return;
    }

    // Re-authentication check for destructive commands
    if (isDestructive(activeModalCommand)) {
      if (!adminPassword) {
        setActiveNotification({
          type: 'error',
          title: 'Re-Authentication Required (401)',
          message: 'Admin credentials verification is mandatory before issuing destructive security operations.',
          details: 'error_code: REAUTHENTICATION_REQUIRED'
        });
        return;
      }
      if (adminPassword !== 'Secret123!' && adminPassword !== 'password') {
        setActiveNotification({
          type: 'error',
          title: 'Re-Authentication Failed (401)',
          message: 'Invalid admin credentials provided. Audit alert dispatched.',
          details: 'error_code: INVALID_REAUTHENTICATION_CREDENTIALS'
        });
        return;
      }
    }

    // Revocation check
    if (selectedDevice.status === 'REVOKED') {
      setActiveNotification({
        type: 'error',
        title: 'Device Revoked (403)',
        message: 'Cannot dispatch commands to revoked or decommissioned hardware.',
        details: 'error_code: DEVICE_REVOKED'
      });
      return;
    }

    // Subscription check
    if (selectedDevice.subscription_status === 'EXPIRED') {
      setActiveNotification({
        type: 'error',
        title: 'Subscription Expired (403)',
        message: 'Target user protection plan has expired. Protection commands are halted.',
        details: 'error_code: SUBSCRIPTION_EXPIRED'
      });
      return;
    }

    // Duplicate in-flight check
    const inFlight = history.find(
      (h) => h.device_id === selectedDevice.id &&
             h.command_type === activeModalCommand &&
             ['PENDING', 'SENT', 'ACKNOWLEDGED', 'EXECUTING'].includes(h.status)
    );
    if (inFlight) {
      setActiveNotification({
        type: 'warning',
        title: 'Duplicate Command In-Flight (409)',
        message: `A ${activeModalCommand} command (${inFlight.command_uuid}) is currently in progress for this device.`,
        details: 'error_code: DUPLICATE_COMMAND_REQUEST'
      });
      return;
    }

    // Capability check: Standard apps without Device Owner or Supervised MDM
    let isSupported = false;
    let failureReason = '';

    if (selectedDevice.platform === 'android') {
      if (selectedDevice.device_management_mode === 'DEVICE_OWNER') {
        isSupported = true; // All supported in DO mode
      } else if (selectedDevice.device_management_mode === 'DEVICE_ADMIN') {
        if (activeModalCommand === 'FACTORY_RESET' || activeModalCommand === 'WIPE_DATA') {
          isSupported = true;
        } else {
          isSupported = false;
          failureReason = 'Screen pinning (KIOSK_LOCK/UNLOCK) requires Android Device Owner, not supported in legacy Device Admin.';
        }
      } else {
        // STANDARD_APP
        isSupported = false;
        failureReason = `Command ${activeModalCommand} is UNSUPPORTED on Android without Device Owner provisioning. Never faked.`;
      }
    } else {
      // iOS
      if (selectedDevice.device_management_mode === 'SUPERVISED_MDM') {
        if (activeModalCommand === 'KIOSK_LOCK' || activeModalCommand === 'KIOSK_UNLOCK') {
          isSupported = true;
        } else {
          isSupported = false;
          failureReason = 'Apple iOS sandbox prohibits in-app wipe or factory reset without external MDM APNs command.';
        }
      } else {
        isSupported = false;
        failureReason = `Command ${activeModalCommand} is UNSUPPORTED on iOS standard user-space application. Never faked.`;
      }
    }

    const newStatus: CommandStatusType = isSupported ? 'SENT' : 'UNSUPPORTED';
    const newUuid = `cmd-${Math.random().toString(36).substring(2, 10)}-2026`;

    const newCommand: CommandHistoryItem = {
      id: Date.now(),
      command_uuid: newUuid,
      command_type: activeModalCommand,
      status: newStatus,
      device_id: selectedDevice.id,
      device_name: selectedDevice.device_name,
      target_user_email: selectedDevice.user_email,
      reason: reason.trim(),
      admin_email: `${adminRole.toLowerCase()}@sata.security`,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
      failure_reason: isSupported ? undefined : failureReason,
      device_management_mode: selectedDevice.device_management_mode,
      reauth_verified: isDestructive(activeModalCommand)
    };

    setHistory([newCommand, ...history]);
    setActiveModalCommand(null);

    if (isSupported) {
      setActiveNotification({
        type: 'success',
        title: 'Command Dispatched (201 Created)',
        message: `Command ${activeModalCommand} created with UUID ${newUuid}. Status is SENT. Secure channel wake-up scheduled.`
      });
    } else {
      setActiveNotification({
        type: 'warning',
        title: 'Status: UNSUPPORTED (Never Faked)',
        message: failureReason,
        details: 'Device management mode does not permit this command. Authoritative status set to UNSUPPORTED.'
      });
    }
  };

  const handleAdvanceStatus = (cmdUuid: string) => {
    setHistory((prev) =>
      prev.map((c) => {
        if (c.command_uuid !== cmdUuid) return c;
        if (c.status === 'SENT') return { ...c, status: 'ACKNOWLEDGED' };
        if (c.status === 'ACKNOWLEDGED') return { ...c, status: 'EXECUTING' };
        if (c.status === 'EXECUTING') return { ...c, status: 'COMPLETED' };
        return c;
      })
    );
  };

  return (
    <div className="space-y-8">
      {/* Header Banner */}
      <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100">
          <div>
            <div className="flex items-center gap-2">
              <ShieldAlert className="w-5 h-5 text-indigo-600" />
              <h1 className="text-lg font-semibold text-slate-900">Admin Security Commands Console</h1>
              <span className="text-xs px-2 py-0.5 rounded-full font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                Phase 12
              </span>
            </div>
            <p className="text-sm text-slate-600 mt-1">
              Authoritative backend command lifecycle: Capability validation, destructive command re-authentication, and zero-falsification enforcement.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <div className="text-xs text-slate-500 flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
              <span className="font-semibold text-slate-700">Simulate Admin Role:</span>
              <select
                value={adminRole}
                onChange={(e) => setAdminRole(e.target.value as any)}
                className="bg-white border border-slate-300 rounded px-2 py-1 text-xs font-medium text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option value="SUPER_ADMIN">SUPER_ADMIN (Full Destructive Permissions)</option>
                <option value="ADMIN">ADMIN (Full Destructive Permissions)</option>
                <option value="SUPPORT">SUPPORT (Forbidden from Destructive Commands)</option>
              </select>
            </div>
          </div>
        </div>

        {/* Global Notifications */}
        {activeNotification && (
          <div
            className={`mt-4 p-4 rounded-lg text-sm border flex items-start justify-between gap-3 ${
              activeNotification.type === 'success'
                ? 'bg-emerald-50 text-emerald-900 border-emerald-200'
                : activeNotification.type === 'warning'
                ? 'bg-amber-50 text-amber-900 border-amber-200'
                : 'bg-rose-50 text-rose-900 border-rose-200'
            }`}
          >
            <div>
              <div className="font-semibold flex items-center gap-1.5">
                {activeNotification.type === 'success' && <CheckCircle2 className="w-4 h-4 text-emerald-600" />}
                {activeNotification.type === 'warning' && <AlertTriangle className="w-4 h-4 text-amber-600" />}
                {activeNotification.type === 'error' && <XCircle className="w-4 h-4 text-rose-600" />}
                <span>{activeNotification.title}</span>
              </div>
              <p className="mt-1">{activeNotification.message}</p>
              {activeNotification.details && (
                <div className="mt-2 font-mono text-xs opacity-80">{activeNotification.details}</div>
              )}
            </div>
            <button
              onClick={() => setActiveNotification(null)}
              className="text-xs px-2 py-1 rounded hover:bg-black/5"
            >
              Dismiss
            </button>
          </div>
        )}

        {/* Target Device & User Selector */}
        <div className="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-1 border-r border-slate-100 pr-0 lg:pr-6">
            <h2 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
              Target Device Selection
            </h2>
            <div className="space-y-2">
              {MOCK_DEVICES.map((d) => {
                const isSelected = selectedDevice.id === d.id;
                return (
                  <button
                    key={d.id}
                    onClick={() => {
                      setSelectedDevice(d);
                      setActiveNotification(null);
                    }}
                    className={`w-full text-left p-3 rounded-lg border transition-all ${
                      isSelected
                        ? 'border-indigo-500 bg-indigo-50/50 shadow-xs'
                        : 'border-slate-200 bg-white hover:border-slate-300'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-xs text-slate-900 truncate">
                        {d.device_name}
                      </span>
                      <span
                        className={`text-[10px] px-1.5 py-0.5 rounded font-mono font-medium ${
                          d.status === 'REVOKED'
                            ? 'bg-rose-100 text-rose-700'
                            : d.subscription_status === 'EXPIRED'
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-emerald-100 text-emerald-700'
                        }`}
                      >
                        {d.status === 'REVOKED'
                          ? 'REVOKED'
                          : d.subscription_status === 'EXPIRED'
                          ? 'SUB EXPIRED'
                          : d.device_management_mode}
                      </span>
                    </div>
                    <div className="text-[11px] text-slate-500 mt-1 flex items-center justify-between">
                      <span>{d.user_name} ({d.user_email})</span>
                      <span className="capitalize">{d.platform}</span>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Device Profile & Capability Details */}
          <div className="lg:col-span-2">
            <div className="bg-slate-50 rounded-lg p-4 border border-slate-200 mb-6">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Smartphone className="w-4 h-4 text-indigo-600" />
                  <span className="font-semibold text-sm text-slate-900">{selectedDevice.device_name}</span>
                </div>
                <span className="font-mono text-xs text-slate-500">{selectedDevice.device_uuid}</span>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div className="bg-white p-2.5 rounded border border-slate-200">
                  <span className="text-slate-500 block text-[11px]">User Account</span>
                  <span className="font-semibold text-slate-800 truncate block">{selectedDevice.user_name}</span>
                  <span className="text-[10px] text-slate-400 truncate block">{selectedDevice.user_email}</span>
                </div>
                <div className="bg-white p-2.5 rounded border border-slate-200">
                  <span className="text-slate-500 block text-[11px]">Platform & OS</span>
                  <span className="font-semibold text-slate-800 uppercase block">{selectedDevice.platform}</span>
                  <span className="text-[10px] text-slate-500 block">{selectedDevice.device_management_mode}</span>
                </div>
                <div className="bg-white p-2.5 rounded border border-slate-200">
                  <span className="text-slate-500 block text-[11px]">Device Status</span>
                  <span
                    className={`font-semibold block ${
                      selectedDevice.status === 'REVOKED' ? 'text-rose-600' : 'text-emerald-600'
                    }`}
                  >
                    {selectedDevice.status}
                  </span>
                  <span className="text-[10px] text-slate-500 block">
                    {selectedDevice.is_protection_enabled ? 'Protection Enabled' : 'Protection Disabled'}
                  </span>
                </div>
                <div className="bg-white p-2.5 rounded border border-slate-200">
                  <span className="text-slate-500 block text-[11px]">Subscription</span>
                  <span
                    className={`font-semibold block ${
                      selectedDevice.subscription_status === 'EXPIRED' ? 'text-rose-600' : 'text-emerald-600'
                    }`}
                  >
                    {selectedDevice.subscription_status}
                  </span>
                  <span className="text-[10px] text-slate-500 block">Authoritative Plan</span>
                </div>
              </div>

              {/* Capability Matrix Warning */}
              <div className="mt-4 p-3 bg-amber-50/80 border border-amber-200 rounded text-xs text-amber-900 flex items-start gap-2">
                <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <div>
                  <span className="font-semibold">Authoritative Platform Capability Guard:</span>
                  <p className="mt-0.5 text-amber-800 leading-relaxed">
                    Normal 3rd-party apps cannot wipe or kiosk-lock Android/iOS devices without privileged enrollment (Android Device Owner or iOS Supervised MDM). If invoked on unmanaged standard apps, SATA returns <span className="font-mono font-bold">UNSUPPORTED</span> and never fakes execution.
                  </p>
                </div>
              </div>
            </div>

            {/* Admin Command Action Buttons */}
            <div>
              <h2 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                Security Command Categories (Phase 12 Specification)
              </h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {/* 1. Factory Reset */}
                <div className="p-4 rounded-lg bg-white border border-rose-200 hover:border-rose-300 transition-all flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-sm text-rose-700 flex items-center gap-1.5">
                        <RotateCcw className="w-4 h-4" />
                        1. FACTORY_RESET
                      </span>
                      <span className="text-[10px] uppercase font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                        Destructive
                      </span>
                    </div>
                    <p className="text-xs text-slate-600 mt-2">
                      Decommissions hardware partition and initiates complete factory data erase. Requires admin re-authentication.
                    </p>
                  </div>
                  <button
                    id="btn-factory-reset"
                    onClick={() => handleOpenCommand('FACTORY_RESET')}
                    className="mt-4 w-full py-2 px-3 bg-rose-600 hover:bg-rose-700 text-white rounded-md text-xs font-semibold shadow-xs transition-colors"
                  >
                    Factory Reset
                  </button>
                </div>

                {/* 2a. Kiosk Lock */}
                <div className="p-4 rounded-lg bg-white border border-indigo-200 hover:border-indigo-300 transition-all flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-sm text-indigo-700 flex items-center gap-1.5">
                        <Lock className="w-4 h-4" />
                        2. KIOSK_LOCK
                      </span>
                      <span className="text-[10px] uppercase font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                        Restricted
                      </span>
                    </div>
                    <p className="text-xs text-slate-600 mt-2">
                      Pins device to dedicated theft lock screen, suppressing home buttons, notifications, and navigation bar.
                    </p>
                  </div>
                  <button
                    id="btn-kiosk-lock"
                    onClick={() => handleOpenCommand('KIOSK_LOCK')}
                    className="mt-4 w-full py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold shadow-xs transition-colors"
                  >
                    Kiosk Lock
                  </button>
                </div>

                {/* 2b. Kiosk Unlock */}
                <div className="p-4 rounded-lg bg-white border border-emerald-200 hover:border-emerald-300 transition-all flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-sm text-emerald-700 flex items-center gap-1.5">
                        <Unlock className="w-4 h-4" />
                        2. KIOSK_UNLOCK
                      </span>
                      <span className="text-[10px] uppercase font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                        Release
                      </span>
                    </div>
                    <p className="text-xs text-slate-600 mt-2">
                      Releases kiosk lock-task single app mode and restores full interactive device access to user.
                    </p>
                  </div>
                  <button
                    id="btn-kiosk-unlock"
                    onClick={() => handleOpenCommand('KIOSK_UNLOCK')}
                    className="mt-4 w-full py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-semibold shadow-xs transition-colors"
                  >
                    Kiosk Unlock
                  </button>
                </div>

                {/* 3. Wipe Data */}
                <div className="p-4 rounded-lg bg-white border border-rose-200 hover:border-rose-300 transition-all flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-sm text-rose-700 flex items-center gap-1.5">
                        <Trash2 className="w-4 h-4" />
                        3. WIPE_DATA
                      </span>
                      <span className="text-[10px] uppercase font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                        Destructive
                      </span>
                    </div>
                    <p className="text-xs text-slate-600 mt-2">
                      Erases external storage, downloads, confidential photos, and sandboxed app credentials. Requires admin re-authentication.
                    </p>
                  </div>
                  <button
                    id="btn-wipe-data"
                    onClick={() => handleOpenCommand('WIPE_DATA')}
                    className="mt-4 w-full py-2 px-3 bg-rose-600 hover:bg-rose-700 text-white rounded-md text-xs font-semibold shadow-xs transition-colors"
                  >
                    Wipe Data
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Confirmation & Re-Authentication Modal */}
      {activeModalCommand && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-xl max-w-lg w-full border border-slate-200 shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div
              className={`p-5 border-b ${
                isDestructive(activeModalCommand)
                  ? 'bg-rose-50 border-rose-100 text-rose-900'
                  : 'bg-indigo-50 border-indigo-100 text-indigo-900'
              } flex items-center justify-between`}
            >
              <div className="flex items-center gap-2 font-semibold">
                {isDestructive(activeModalCommand) ? (
                  <AlertTriangle className="w-5 h-5 text-rose-600" />
                ) : (
                  <ShieldAlert className="w-5 h-5 text-indigo-600" />
                )}
                <span>Confirm Security Command Dispatch</span>
              </div>
              <button
                onClick={() => setActiveModalCommand(null)}
                className="text-slate-400 hover:text-slate-600 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            <div className="p-6 space-y-4">
              {/* Target Details */}
              <div className="bg-slate-50 p-3.5 rounded-lg border border-slate-200 space-y-1.5 text-xs">
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Command:</span>
                  <span className="font-mono font-bold text-slate-900">{activeModalCommand}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Target User:</span>
                  <span className="font-semibold text-slate-800">
                    {selectedDevice.user_name} ({selectedDevice.user_email})
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Target Device:</span>
                  <span className="font-semibold text-slate-800">{selectedDevice.device_name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Platform Mode:</span>
                  <span className="font-mono text-indigo-700 bg-indigo-50 px-1.5 py-0.2 rounded border border-indigo-200">
                    {selectedDevice.platform.toUpperCase()} - {selectedDevice.device_management_mode}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500 font-medium">Acting Admin:</span>
                  <span className="font-mono text-slate-700">{adminRole}</span>
                </div>
              </div>

              {/* Warning for Destructive Commands */}
              {isDestructive(activeModalCommand) && (
                <div className="p-3 bg-rose-50 border border-rose-200 rounded text-xs text-rose-800">
                  <p className="font-semibold">Permanent & Destructive Operation:</p>
                  <p className="mt-0.5">
                    This operation will erase hardware partitions or confidential data. This cannot be undone once executed. Re-authentication password is required.
                  </p>
                </div>
              )}

              {/* Reason input */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Mandatory Audit Reason <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  placeholder="e.g. Authorized emergency wipe requested by verified device owner"
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="w-full text-xs px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 text-slate-800"
                />
              </div>

              {/* Re-Authentication for Destructive Commands */}
              {isDestructive(activeModalCommand) && (
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                    <span>Admin Re-Authentication Password <span className="text-rose-500">*</span></span>
                    <span className="text-[10px] text-slate-400 font-normal">Demo credentials: Secret123!</span>
                  </label>
                  <input
                    type="password"
                    placeholder="Enter your current admin password"
                    value={adminPassword}
                    onChange={(e) => setAdminPassword(e.target.value)}
                    className="w-full text-xs px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-rose-500 text-slate-800"
                  />
                </div>
              )}
            </div>

            <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
              <button
                onClick={() => setActiveModalCommand(null)}
                className="px-4 py-2 border border-slate-300 rounded-md text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
              >
                Cancel
              </button>
              <button
                id="btn-confirm-execute"
                onClick={handleExecuteCommand}
                className={`px-4 py-2 rounded-md text-xs font-semibold text-white shadow-xs transition-colors ${
                  isDestructive(activeModalCommand)
                    ? 'bg-rose-600 hover:bg-rose-700'
                    : 'bg-indigo-600 hover:bg-indigo-700'
                }`}
              >
                {isDestructive(activeModalCommand) ? 'Re-Authenticate & Dispatch' : 'Dispatch Command'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Authoritative Command Lifecycle & Audit Stream */}
      <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <div className="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <FileText className="w-5 h-5 text-slate-700" />
            <h2 className="text-base font-semibold text-slate-900">Authoritative Command Audit & Status Trail</h2>
          </div>
          <span className="text-xs text-slate-500">Live Backend Database Records</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/50 text-slate-500 font-medium">
                <th className="py-2.5 px-3">Command UUID</th>
                <th className="py-2.5 px-3">Type</th>
                <th className="py-2.5 px-3">Target Device & Mode</th>
                <th className="py-2.5 px-3">Status</th>
                <th className="py-2.5 px-3">Audit Reason</th>
                <th className="py-2.5 px-3">Admin</th>
                <th className="py-2.5 px-3">Timestamp</th>
                <th className="py-2.5 px-3 text-right">Lifecycle Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {history.map((item) => (
                <tr key={item.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="py-3 px-3 font-mono text-[11px] text-slate-700 font-semibold">
                    {item.command_uuid}
                  </td>
                  <td className="py-3 px-3">
                    <span
                      className={`font-semibold px-2 py-0.5 rounded text-[11px] font-mono ${
                        isDestructive(item.command_type)
                          ? 'bg-rose-50 text-rose-700 border border-rose-200'
                          : 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                      }`}
                    >
                      {item.command_type}
                    </span>
                  </td>
                  <td className="py-3 px-3">
                    <span className="font-medium text-slate-800 block truncate max-w-[180px]">
                      {item.device_name}
                    </span>
                    <span className="text-[10px] text-slate-400 block font-mono">
                      {item.device_management_mode}
                    </span>
                  </td>
                  <td className="py-3 px-3">
                    <span
                      className={`px-2 py-0.5 rounded-full text-[10px] font-bold font-mono ${
                        item.status === 'COMPLETED'
                          ? 'bg-emerald-100 text-emerald-800'
                          : item.status === 'UNSUPPORTED'
                          ? 'bg-amber-100 text-amber-800 border border-amber-300'
                          : item.status === 'FAILED' || item.status === 'EXPIRED'
                          ? 'bg-rose-100 text-rose-800'
                          : 'bg-indigo-100 text-indigo-800 animate-pulse'
                      }`}
                    >
                      {item.status}
                    </span>
                    {item.failure_reason && (
                      <span className="text-[10px] text-amber-700 block mt-1 max-w-[220px] leading-tight">
                        {item.failure_reason}
                      </span>
                    )}
                  </td>
                  <td className="py-3 px-3 text-slate-600 max-w-[180px] truncate" title={item.reason}>
                    {item.reason}
                  </td>
                  <td className="py-3 px-3 text-slate-500 font-mono text-[11px]">
                    {item.admin_email}
                  </td>
                  <td className="py-3 px-3 text-slate-400 text-[11px]">
                    {item.created_at}
                  </td>
                  <td className="py-3 px-3 text-right">
                    {['SENT', 'ACKNOWLEDGED', 'EXECUTING'].includes(item.status) && (
                      <button
                        onClick={() => handleAdvanceStatus(item.command_uuid)}
                        className="text-[11px] px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded font-medium border border-slate-300 transition-colors"
                      >
                        Advance Lifecycle →
                      </button>
                    )}
                    {item.status === 'COMPLETED' && (
                      <span className="text-[11px] text-emerald-600 font-semibold flex items-center justify-end gap-1">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        Verified Terminal
                      </span>
                    )}
                    {item.status === 'UNSUPPORTED' && (
                      <span className="text-[10px] text-amber-700 font-semibold flex items-center justify-end gap-1">
                        <XCircle className="w-3.5 h-3.5" />
                        Zero Falsification
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
