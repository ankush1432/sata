import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  ArrowLeft,
  User as UserIcon,
  Phone,
  Mail,
  Smartphone,
  CreditCard,
  KeyRound,
  ShieldCheck,
  ShieldAlert,
  MapPin,
  Camera,
  Terminal,
  History,
  AlertTriangle,
  RotateCcw,
  Lock,
  Unlock,
  Trash2,
  ExternalLink,
  Edit2,
  CheckCircle2,
  Clock,
  Battery,
  Wifi,
  Copy,
  Check,
  Volume2,
  Navigation,
  Power,
  Radio,
  Zap,
  Filter,
  Search,
  Eye,
  Shield,
  Layers,
  AlertCircle,
  Plus,
  X,
  Users
} from 'lucide-react';
import { Badge } from '../../common/Badge';
import { ConfirmationModal } from '../../common/ConfirmationModal';
import { MapModal } from '../../common/MapModal';
import { PhotoModal } from '../../common/PhotoModal';
import { CommandType, SecurityAlert, DeviceActionLog, TrustedContact } from '../../../types/admin';

interface UserDetailViewProps {
  userId: number;
}

type TabType = 'all' | 'device' | 'trusted' | 'subscription' | 'commands' | 'location' | 'intruders' | 'actions';

export const UserDetailView: React.FC<UserDetailViewProps> = ({ userId }) => {
  const {
    users,
    subscriptions,
    securityAlerts,
    securityCommands,
    dispatchSecurityCommand,
    renewSubscription,
    setCurrentRoute,
    addToast,
    addTrustedContact,
    updateTrustedContact,
    deleteTrustedContact
  } = useAdmin();

  const user = users.find(u => u.id === userId);

  // Active Tab View
  const [activeTab, setActiveTab] = useState<TabType>('all');

  // Modals state
  const [selectedPhotoAlert, setSelectedPhotoAlert] = useState<SecurityAlert | null>(null);
  const [isMapModalOpen, setIsMapModalOpen] = useState(false);
  const [copiedUrl, setCopiedUrl] = useState(false);
  const [actionFilter, setActionFilter] = useState<string>('ALL');
  const [actionSearch, setActionSearch] = useState<string>('');

  // Command Execution State
  const [pendingCommand, setPendingCommand] = useState<{
    type: CommandType;
    title: string;
    description: string;
    variant: 'danger' | 'warning' | 'info';
  } | null>(null);

  if (!user) {
    return (
      <div className="bg-white rounded-xl p-12 text-center border border-slate-200 shadow-2xs">
        <UserIcon className="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <h3 className="text-base font-bold text-slate-800">User Not Found</h3>
        <p className="text-xs text-slate-500 mt-1">The requested user account does not exist or has been deleted.</p>
        <button
          onClick={() => setCurrentRoute('/admin/users')}
          className="mt-4 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
        >
          Back to All Users
        </button>
      </div>
    );
  }

  const primaryDevice = user.devices[0];
  const userSubscription = subscriptions.find(s => s.user_id === user.id) || user.current_subscription;
  const userAlerts = securityAlerts.filter(a => a.user_id === user.id);
  const userCommands = securityCommands.filter(c => c.user_id === user.id);
  const intruderPhotos = userAlerts.filter(a => a.alert_type === 'INTRUDER_PHOTO_CAPTURED' && a.evidence_url);

  // Comprehensive Action History (User Device Actions + Command history)
  const actionHistory: DeviceActionLog[] = useMemo(() => {
    return user.action_history || [];
  }, [user.action_history]);

  // Filtered action history
  const filteredActions = useMemo(() => {
    return actionHistory.filter(act => {
      const matchesSearch =
        actionSearch === '' ||
        act.title.toLowerCase().includes(actionSearch.toLowerCase()) ||
        act.details.toLowerCase().includes(actionSearch.toLowerCase()) ||
        act.action_type.toLowerCase().includes(actionSearch.toLowerCase());

      if (!matchesSearch) return false;

      if (actionFilter === 'ALL') return true;
      if (actionFilter === 'POWER' && (act.action_type === 'POWER_BUTTON_CLICK' || act.action_type === 'POWER_OFF_ATTEMPT')) return true;
      if (actionFilter === 'SIM' && (act.action_type === 'SIM_REMOVED' || act.action_type === 'SIM_CHANGED')) return true;
      if (actionFilter === 'INTRUDER' && (act.action_type === 'INTRUDER_PHOTO_CAPTURED' || act.action_type === 'WRONG_PIN_ATTEMPT')) return true;
      if (actionFilter === 'COMMANDS' && act.action_type === 'COMMAND_EXECUTED') return true;
      if (actionFilter === 'SENSORS' && (act.action_type === 'CHARGER_DISCONNECTED' || act.action_type === 'MOTION_TAMPER' || act.action_type === 'LOCATION_PING')) return true;

      return false;
    });
  }, [actionHistory, actionFilter, actionSearch]);

  const handleCopyShortUrl = (url?: string) => {
    if (!url) return;
    navigator.clipboard.writeText(url);
    setCopiedUrl(true);
    addToast('Link Copied', 'Emergency location URL copied to clipboard', 'info');
    setTimeout(() => setCopiedUrl(false), 2000);
  };

  const handleConfirmCommand = () => {
    if (!pendingCommand || !primaryDevice) return;
    dispatchSecurityCommand(
      user.id,
      primaryDevice.id,
      pendingCommand.type,
      `Admin initiated ${pendingCommand.type} from User Detail Control Panel`
    );
    setPendingCommand(null);
  };

  // Trusted Contact Management State (Enforcing Mandatory: Name, Mobile, Email)
  const [isContactModalOpen, setIsContactModalOpen] = useState(false);
  const [editingContact, setEditingContact] = useState<TrustedContact | null>(null);
  const [contactFormData, setContactFormData] = useState({
    name: '',
    mobile_number: '',
    email: '',
    relationship: 'Emergency Contact',
    is_verified: true,
  });
  const [contactErrors, setContactErrors] = useState<{ name?: string; mobile_number?: string; email?: string }>({});
  const [isSavingContact, setIsSavingContact] = useState(false);
  const [contactToDelete, setContactToDelete] = useState<TrustedContact | null>(null);
  const [isDeletingContact, setIsDeletingContact] = useState(false);

  const openAddContactModal = () => {
    setEditingContact(null);
    setContactFormData({
      name: '',
      mobile_number: '',
      email: '',
      relationship: 'Emergency Contact',
      is_verified: true,
    });
    setContactErrors({});
    setIsContactModalOpen(true);
  };

  const openEditContactModal = (contact: TrustedContact) => {
    setEditingContact(contact);
    setContactFormData({
      name: contact.name,
      mobile_number: contact.mobile_number,
      email: contact.email,
      relationship: contact.relationship || 'Emergency Contact',
      is_verified: contact.is_verified,
    });
    setContactErrors({});
    setIsContactModalOpen(true);
  };

  const validateContactForm = () => {
    const errs: { name?: string; mobile_number?: string; email?: string } = {};
    if (!contactFormData.name.trim()) {
      errs.name = 'Full legal name is mandatory';
    }
    if (!contactFormData.mobile_number.trim()) {
      errs.mobile_number = 'Mobile number is mandatory';
    } else if (contactFormData.mobile_number.trim().length < 7) {
      errs.mobile_number = 'Please enter a valid mobile number';
    }
    if (!contactFormData.email.trim()) {
      errs.email = 'Email address is mandatory (required to receive intruder photo and location links)';
    } else if (!contactFormData.email.includes('@') || !contactFormData.email.includes('.')) {
      errs.email = 'Please enter a valid email address';
    }
    setContactErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSaveContact = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateContactForm()) return;

    setIsSavingContact(true);
    try {
      if (editingContact) {
        await updateTrustedContact(user.id, editingContact.id, {
          name: contactFormData.name.trim(),
          mobile_number: contactFormData.mobile_number.trim(),
          email: contactFormData.email.trim(),
          relationship: contactFormData.relationship,
          is_verified: contactFormData.is_verified,
        });
      } else {
        await addTrustedContact(user.id, {
          name: contactFormData.name.trim(),
          mobile_number: contactFormData.mobile_number.trim(),
          email: contactFormData.email.trim(),
          relationship: contactFormData.relationship,
          is_verified: contactFormData.is_verified,
        });
      }
      setIsContactModalOpen(false);
    } finally {
      setIsSavingContact(false);
    }
  };

  const handleConfirmDeleteContact = async () => {
    if (!contactToDelete) return;
    setIsDeletingContact(true);
    try {
      await deleteTrustedContact(user.id, contactToDelete.id);
      setContactToDelete(null);
    } finally {
      setIsDeletingContact(false);
    }
  };

  const [isTriggering, setIsTriggering] = useState(false);

  const handleTriggerRealOtp = async () => {
    setIsTriggering(true);
    try {
      const res = await fetch('/admin/api/otp/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          phoneNumber: user.phone,
          email: user.email,
          userName: user.name,
          purpose: 'SECURITY_VERIFICATION',
          deviceFcmToken: primaryDevice?.device_uuid,
        })
      });
      const data = await res.json();
      if (data.success) {
        addToast('Real OTP Dispatched', `Generated 6-digit OTP stored in MySQL and sent to device owner (${user.email})`, 'success');
      } else {
        addToast('OTP Failed', data.message, 'error');
      }
    } catch (err: any) {
      addToast('Error', err.message, 'error');
    } finally {
      setIsTriggering(false);
    }
  };

  const handleTriggerIntruderEmergency = async () => {
    setIsTriggering(true);
    try {
      const res = await fetch('/admin/api/trigger/intruder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId: user.id,
          deviceId: primaryDevice?.id || 101,
          eventType: 'SIM_CARD_REMOVED',
          photoUrl: intruderPhotos[0]?.photo_url || 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=600&auto=format&fit=crop&q=80',
          lat: user.location?.lat || 37.7749,
          lng: user.location?.lng || -122.4194,
        })
      });
      const data = await res.json();
      if (data.success) {
        addToast(
          'Emergency Alert Dispatched',
          `Intruder photo link & live GPS location link dispatched strictly to trusted contact emails! Device owner received verification OTP only.`,
          'success'
        );
      } else {
        addToast('Trigger Failed', data.message, 'error');
      }
    } catch (err: any) {
      addToast('Error', err.message, 'error');
    } finally {
      setIsTriggering(false);
    }
  };

  const getActionBadgeColor = (severity: string) => {
    switch (severity) {
      case 'CRITICAL':
        return 'bg-rose-100 text-rose-800 border-rose-200';
      case 'HIGH':
        return 'bg-amber-100 text-amber-800 border-amber-200';
      case 'MEDIUM':
        return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'LOW':
      case 'INFO':
      default:
        return 'bg-sky-100 text-sky-800 border-sky-200';
    }
  };

  const getActionIcon = (actionType: string) => {
    switch (actionType) {
      case 'POWER_BUTTON_CLICK':
      case 'POWER_OFF_ATTEMPT':
        return <Power className="w-4 h-4 text-rose-600" />;
      case 'SIM_REMOVED':
      case 'SIM_CHANGED':
        return <Radio className="w-4 h-4 text-rose-600" />;
      case 'INTRUDER_PHOTO_CAPTURED':
        return <Camera className="w-4 h-4 text-amber-600" />;
      case 'WRONG_PIN_ATTEMPT':
        return <Lock className="w-4 h-4 text-amber-600" />;
      case 'CHARGER_DISCONNECTED':
        return <Battery className="w-4 h-4 text-orange-600" />;
      case 'COMMAND_EXECUTED':
        return <Terminal className="w-4 h-4 text-emerald-600" />;
      case 'LOCATION_PING':
        return <MapPin className="w-4 h-4 text-blue-600" />;
      default:
        return <AlertCircle className="w-4 h-4 text-slate-500" />;
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Breadcrumb & Return Action */}
      <div className="flex items-center justify-between">
        <button
          onClick={() => setCurrentRoute('/admin/users')}
          className="flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to All Users</span>
        </button>

        <div className="flex items-center gap-2 flex-wrap">
          <button
            onClick={handleTriggerRealOtp}
            disabled={isTriggering}
            className="px-3 py-1.5 text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100/80 transition-colors flex items-center gap-1.5 shadow-2xs"
            title="Generate real 6-digit OTP, store in MySQL table otp_verifications, and send via FCM & Email"
          >
            <KeyRound className="w-3.5 h-3.5 text-emerald-600" />
            <span>Send Real OTP</span>
          </button>

          <button
            onClick={handleTriggerIntruderEmergency}
            disabled={isTriggering}
            className="px-3 py-1.5 text-xs font-semibold text-rose-800 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100/80 transition-colors flex items-center gap-1.5 shadow-2xs"
            title="Dispatch Intruder Photo link and Live Location link via Firebase Cloud Messaging & Email to user and trusted circle"
          >
            <ShieldAlert className="w-3.5 h-3.5 text-rose-600" />
            <span>Trigger Intruder Alert</span>
          </button>

          <button
            onClick={() => setCurrentRoute(`/admin/users/${user.id}/edit`)}
            className="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1.5 shadow-2xs"
          >
            <Edit2 className="w-3.5 h-3.5" />
            <span>Edit Profile</span>
          </button>
        </div>
      </div>

      {/* User Header Profile Card */}
      <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-2xs">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-5">
          <div className="flex items-center gap-4">
            <div className="relative shrink-0">
              {user.avatar ? (
                <img
                  src={user.avatar}
                  alt={user.name}
                  className="w-16 h-16 rounded-2xl object-cover border-2 border-slate-200 shadow-xs"
                />
              ) : (
                <div className="w-16 h-16 rounded-2xl bg-gradient-to-tr from-slate-800 to-slate-950 text-white font-bold text-2xl flex items-center justify-center shadow-xs">
                  {user.name.charAt(0)}
                </div>
              )}
              <span
                className={`absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white ${
                  user.status === 'ACTIVE'
                    ? 'bg-emerald-500'
                    : user.status === 'DISABLED'
                    ? 'bg-rose-500'
                    : 'bg-amber-500'
                }`}
                title={`Status: ${user.status}`}
              />
            </div>

            <div>
              <div className="flex items-center gap-2.5 flex-wrap">
                <h1 className="text-xl font-bold text-slate-900">{user.name}</h1>
                <Badge
                  variant={
                    user.status === 'ACTIVE'
                      ? 'success'
                      : user.status === 'DISABLED'
                      ? 'danger'
                      : 'warning'
                  }
                >
                  {user.status}
                </Badge>
                {primaryDevice?.is_protection_enabled && (
                  <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    <ShieldCheck className="w-3 h-3 text-emerald-600" />
                    Protection Armed
                  </span>
                )}
              </div>

              <div className="flex items-center gap-4 text-xs text-slate-500 mt-1.5 flex-wrap">
                <span className="font-mono">User ID: #{user.id}</span>
                <span>•</span>
                <span className="flex items-center gap-1 text-slate-700 font-medium">
                  <Phone className="w-3.5 h-3.5 text-emerald-600" />
                  {user.phone}
                </span>
                <span>•</span>
                <span className="flex items-center gap-1 text-slate-700 font-medium">
                  <Mail className="w-3.5 h-3.5 text-slate-400" />
                  {user.email}
                </span>
              </div>
            </div>
          </div>

          {/* Quick Stat Badges */}
          <div className="flex items-center gap-2.5 text-xs border-t md:border-t-0 pt-3 md:pt-0 border-slate-100 flex-wrap">
            <div className="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-center">
              <span className="text-[10px] text-slate-400 uppercase font-bold block">Subscription</span>
              <span className="font-bold text-slate-900">{user.subscription_status}</span>
            </div>
            <div className="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-center">
              <span className="text-[10px] text-slate-400 uppercase font-bold block">Activation</span>
              <span className="font-bold text-emerald-700">{user.activation_status}</span>
            </div>
            <div className="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-center">
              <span className="text-[10px] text-slate-400 uppercase font-bold block">Action Events</span>
              <span className="font-bold text-purple-700">{actionHistory.length} Recorded</span>
            </div>
          </div>
        </div>

        {/* Tab Navigation Menu */}
        <div className="flex items-center gap-1 border-t border-slate-100 mt-6 pt-3 overflow-x-auto scrollbar-none">
          {[
            { id: 'all', label: 'All Information', icon: Layers },
            { id: 'trusted', label: `Trusted Contacts (${user.trusted_contacts?.length || 0})`, icon: Phone },
            { id: 'device', label: 'Device Info', icon: Smartphone },
            { id: 'subscription', label: 'Subscription & Plan', icon: CreditCard },
            { id: 'commands', label: 'Security Commands', icon: Terminal },
            { id: 'location', label: 'Live Location', icon: MapPin },
            { id: 'intruders', label: `Intruder Photos (${intruderPhotos.length})`, icon: Camera },
            { id: 'actions', label: `Action History (${actionHistory.length})`, icon: History }
          ].map(tab => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as TabType)}
                className={`flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all ${
                  isActive
                    ? 'bg-emerald-600 text-white shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                }`}
              >
                <Icon className="w-3.5 h-3.5" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* TAB 1: DEVICE INFORMATION & EMERGENCY CIRCLE */}
      {(activeTab === 'all' || activeTab === 'device' || activeTab === 'trusted') && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Hardware Device Details Card */}
          {(activeTab === 'all' || activeTab === 'device') && (
            <div className={`bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4 ${activeTab === 'device' ? 'lg:col-span-2' : ''}`}>
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <Smartphone className="w-4 h-4 text-emerald-600" />
                <span>Hardware Device Information</span>
              </h3>
              {primaryDevice && (
                <Badge variant={primaryDevice.is_protection_enabled ? 'success' : 'neutral'}>
                  {primaryDevice.is_protection_enabled ? 'Anti-Theft Active' : 'Unprotected'}
                </Badge>
              )}
            </div>

            {primaryDevice ? (
              <div className="space-y-4 text-xs">
                <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
                  <div className="flex items-center justify-between mb-3">
                    <span className="font-bold text-slate-900 text-sm">{primaryDevice.device_name}</span>
                    <span className="px-2 py-0.5 text-[10px] font-bold rounded uppercase bg-slate-200 text-slate-700">
                      {primaryDevice.platform}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-[11px] text-slate-600">
                    <div>
                      <span className="text-slate-400 block">OS Version:</span>
                      <span className="font-semibold text-slate-900">{primaryDevice.os_version}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block">App Version:</span>
                      <span className="font-semibold text-slate-900">{primaryDevice.app_version}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block">Management Mode:</span>
                      <span className="font-semibold text-emerald-700">{primaryDevice.device_management_mode || 'DEVICE_OWNER'}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block">Battery Level:</span>
                      <span className="font-semibold text-slate-900 flex items-center gap-1">
                        <Battery className="w-3.5 h-3.5 text-emerald-600" />
                        {primaryDevice.battery_level || 85}% {primaryDevice.is_charging ? '(Charging)' : ''}
                      </span>
                    </div>
                    <div>
                      <span className="text-slate-400 block">Network Connection:</span>
                      <span className="font-semibold text-slate-900">{primaryDevice.network_type || '5G Cellular'}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block">Last Contact:</span>
                      <span className="text-slate-700">{primaryDevice.last_seen}</span>
                    </div>
                  </div>
                </div>

                <div className="p-3 bg-slate-100 rounded-lg text-[11px] font-mono text-slate-600 flex items-center justify-between">
                  <span className="truncate">UUID: {primaryDevice.device_uuid}</span>
                  <button
                    onClick={() => {
                      navigator.clipboard.writeText(primaryDevice.device_uuid);
                      addToast('UUID Copied', 'Device UUID copied to clipboard', 'info');
                    }}
                    className="p-1 hover:text-slate-900"
                    title="Copy UUID"
                  >
                    <Copy className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            ) : (
              <div className="p-8 text-center bg-slate-50 rounded-lg border border-slate-100 text-xs text-slate-400">
                No active hardware device registered to this user account yet.
              </div>
            )}
          </div>
          )}

          {/* Trusted Users (Emergency Circle) */}
          {(activeTab === 'all' || activeTab === 'trusted') && (
            <div className={`bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4 ${activeTab === 'trusted' ? 'lg:col-span-2' : ''}`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-2">
              <div>
                <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                  <Phone className="w-4 h-4 text-emerald-600" />
                  <span>Trusted Emergency Users</span>
                </h3>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Mandatory: Name, Mobile, Email • Receives intruder photo link & live GPS tracking
                </p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-[11px] font-mono font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">
                  {user.trusted_contacts?.length || 0} / 2 Slots
                </span>
                <button
                  type="button"
                  onClick={openAddContactModal}
                  disabled={(user.trusted_contacts?.length || 0) >= 2}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-2xs"
                  title={(user.trusted_contacts?.length || 0) >= 2 ? 'Maximum 2 trusted users allowed' : 'Add new trusted user'}
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Add Trusted User</span>
                </button>
              </div>
            </div>

            {/* Informational Policy Banner */}
            <div className="p-3 bg-amber-50/70 border border-amber-200/80 rounded-lg flex items-start gap-2.5 text-xs text-amber-900">
              <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div>
                <span className="font-bold">Security Alert Delivery Policy:</span> Front-camera intruder photos and live Google Maps tracking links are emailed <span className="underline font-semibold">strictly to trusted emergency contacts</span>. The primary device owner receives an OTP only to prevent warning an active thief.
              </div>
            </div>

            <div className="space-y-3">
              {user.trusted_contacts && user.trusted_contacts.length > 0 ? (
                user.trusted_contacts.map(contact => (
                  <div
                    key={contact.id}
                    className="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 hover:border-slate-300 transition-colors"
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className="text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded font-mono">
                          Slot {contact.slot || 1}
                        </span>
                        <span className="font-bold text-slate-900 text-xs">{contact.name}</span>
                        <span className="text-[10px] text-slate-500 font-semibold bg-slate-200/70 px-1.5 py-0.5 rounded">
                          {contact.relationship}
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant={contact.is_verified ? 'success' : 'warning'}>
                          {contact.is_verified ? 'OTP Verified' : 'Pending Verification'}
                        </Badge>
                        <button
                          type="button"
                          onClick={() => openEditContactModal(contact)}
                          className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition-colors shadow-2xs"
                          title="Admin Edit Trusted Contact Details"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                          <span>Edit</span>
                        </button>
                        <button
                          type="button"
                          onClick={() => setContactToDelete(contact)}
                          className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-colors shadow-2xs"
                          title="Delete Trusted Contact"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                          <span>Delete</span>
                        </button>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-200/50 text-xs">
                      <div className="flex items-center gap-1.5 text-slate-800 font-mono">
                        <Phone className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>{contact.mobile_number}</span>
                        <span className="text-[10px] text-emerald-700 font-sans font-medium">(Mandatory Mobile)</span>
                      </div>
                      <div className="flex items-center gap-1.5 text-slate-700 truncate">
                        <Mail className="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                        <span className="truncate font-mono">{contact.email}</span>
                        <span className="text-[10px] text-indigo-700 font-sans font-medium shrink-0 bg-indigo-50 border border-indigo-200 px-1 rounded">Photo & GPS Recipient</span>
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs space-y-2">
                  <p className="text-slate-500 font-medium">No trusted emergency contacts configured yet.</p>
                  <p className="text-slate-400 text-[11px]">
                    Admin can register trusted contacts directly with mandatory Name, Mobile, and Email to ensure intruder alert photos and location links are delivered.
                  </p>
                  <button
                    type="button"
                    onClick={openAddContactModal}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-2xs mt-1"
                  >
                    <Plus className="w-3.5 h-3.5" />
                    <span>Add First Trusted Contact</span>
                  </button>
                </div>
              )}
            </div>
          </div>
          )}
        </div>
      )}

      {/* TAB 2: SUBSCRIPTION & ACTIVATION INFO */}
      {(activeTab === 'all' || activeTab === 'subscription') && (
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <CreditCard className="w-4 h-4 text-emerald-600" />
              <span>Subscription & Activation Entitlements</span>
            </h3>
            {userSubscription && (
              <button
                onClick={() => {
                  renewSubscription(userSubscription.id, 30);
                  addToast('Subscription Extended', 'Added 30 days of active protection', 'success');
                }}
                className="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-colors"
              >
                + Extend 30 Days
              </button>
            )}
          </div>

          {userSubscription ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
              <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                <span className="text-slate-400 font-semibold uppercase text-[10px] block">Assigned Protection Plan</span>
                <p className="font-bold text-slate-900 text-sm mt-0.5">{userSubscription.plan_name}</p>
                <span className="font-mono text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 mt-1 inline-block">
                  {userSubscription.plan_code}
                </span>
              </div>

              <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                <span className="text-slate-400 font-semibold uppercase text-[10px] block">Subscription Status</span>
                <div className="mt-1 flex items-center gap-2">
                  <Badge variant={userSubscription.status === 'ACTIVE' ? 'success' : 'warning'}>
                    {userSubscription.status}
                  </Badge>
                  <span className="text-xs font-bold text-slate-800">{userSubscription.days_remaining} Days Left</span>
                </div>
                <div className="w-full bg-slate-200 h-1.5 rounded-full mt-2 overflow-hidden">
                  <div
                    className="bg-emerald-500 h-full rounded-full transition-all"
                    style={{ width: `${Math.min(100, Math.max(5, (userSubscription.days_remaining / 365) * 100))}%` }}
                  />
                </div>
              </div>

              <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                <p className="text-slate-800 font-medium mt-1">
                  Starts: {userSubscription.starts_at ? (userSubscription.starts_at.includes(' ') ? userSubscription.starts_at.split(' ')[0] : userSubscription.starts_at.split('T')[0]) : '-'}
                </p>
                <p className="text-slate-500 text-[11px]">
                  Expires: {userSubscription.expires_at ? (userSubscription.expires_at.includes(' ') ? userSubscription.expires_at.split(' ')[0] : userSubscription.expires_at.split('T')[0]) : '-'}
                </p>
              </div>

              <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                <span className="text-slate-400 font-semibold uppercase text-[10px] block">Payment Reference</span>
                <p className="font-mono text-slate-800 font-bold mt-1">{userSubscription.payment_reference}</p>
                <span className="text-[11px] text-emerald-700 font-semibold">${userSubscription.price_paid} Paid</span>
              </div>
            </div>
          ) : (
            <div className="p-8 bg-slate-50 rounded-xl text-center text-xs text-slate-400">
              No active subscription record. Account is awaiting activation license code.
            </div>
          )}
        </div>
      )}

      {/* TAB 3: SECURITY COMMANDS (REMOTE CONTROLS) */}
      {(activeTab === 'all' || activeTab === 'commands') && (
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
              <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <Terminal className="w-4 h-4 text-emerald-600" />
                <span>Authoritative Security Commands</span>
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Dispatch anti-theft hardware commands directly to device via high-priority FCM push channels
              </p>
            </div>
            {primaryDevice && (
              <span className="text-[11px] font-mono bg-slate-100 px-2.5 py-1 rounded text-slate-700">
                Target: {primaryDevice.device_name}
              </span>
            )}
          </div>

          {/* Interactive Command Control Tiles */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {/* 1. Siren Alarm */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'SIREN_ALARM',
                  title: 'Trigger High-Decibel Siren Alarm',
                  description:
                    'This will immediately sound an ear-piercing anti-theft siren at 100% volume on the target device, ignoring silent or vibrate mode.',
                  variant: 'warning'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-amber-200 bg-amber-50/40 hover:bg-amber-50 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-amber-100 text-amber-700 group-hover:scale-105 transition-transform">
                  <Volume2 className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">
                  AUDIO SIREN
                </span>
              </div>
              <div className="text-xs font-bold text-slate-900">Sound Siren Alarm</div>
              <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                Trigger loud maximum-volume alarm to expose thief
              </div>
            </button>

            {/* 2. Kiosk Lock */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'KIOSK_LOCK',
                  title: 'Engage Remote Kiosk Screen Lock',
                  description:
                    'This will remotely pin the device into full-screen emergency lockdown mode, preventing access to the launcher, recent apps, and power-off dialog.',
                  variant: 'warning'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-rose-200 bg-rose-50/40 hover:bg-rose-50 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-rose-100 text-rose-700 group-hover:scale-105 transition-transform">
                  <Lock className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-rose-700 bg-rose-100 px-1.5 py-0.5 rounded">
                  HIGH PRIORITY
                </span>
              </div>
              <div className="text-xs font-bold text-slate-900">Kiosk Lock</div>
              <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                Lock device into tamper-proof emergency PIN screen
              </div>
            </button>

            {/* 3. Kiosk Unlock */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'KIOSK_UNLOCK',
                  title: 'Release Kiosk Screen Lockdown',
                  description:
                    'This will clear the remote screen lock and restore standard operating system controls to the user.',
                  variant: 'info'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-blue-200 bg-blue-50/40 hover:bg-blue-50 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-blue-100 text-blue-700 group-hover:scale-105 transition-transform">
                  <Unlock className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded">
                  RESTORE
                </span>
              </div>
              <div className="text-xs font-bold text-slate-900">Kiosk Unlock</div>
              <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                Release screen pin and allow normal phone usage
              </div>
            </button>

            {/* 4. Track Live Location */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'TRACK_LOCATION',
                  title: 'Request Immediate GPS Fix',
                  description:
                    'This sends a high-priority GPS wake request to the target device to return instant precision coordinates.',
                  variant: 'info'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/40 hover:bg-emerald-50 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-emerald-100 text-emerald-700 group-hover:scale-105 transition-transform">
                  <Navigation className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                  GPS RADAR
                </span>
              </div>
              <div className="text-xs font-bold text-slate-900">Ping Location</div>
              <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                Wake satellite receiver and return real-time coordinates
              </div>
            </button>

            {/* 5. Stealth Camera Capture */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'CAPTURE_PHOTO',
                  title: 'Capture Stealth Intruder Photo',
                  description:
                    'Silently triggers both front and back cameras without screen flash or shutter sounds and uploads images to cloud forensics.',
                  variant: 'warning'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-purple-200 bg-purple-50/40 hover:bg-purple-50 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-purple-100 text-purple-700 group-hover:scale-105 transition-transform">
                  <Camera className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-purple-700 bg-purple-100 px-1.5 py-0.5 rounded">
                  FORENSICS
                </span>
              </div>
              <div className="text-xs font-bold text-slate-900">Capture Photo</div>
              <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                Snap discreet camera photos of phone holder
              </div>
            </button>

            {/* 6. Wipe App Data */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'WIPE_DATA',
                  title: 'Securely Wipe Encrypted Data',
                  description:
                    'WARNING: This will purge all cached credentials, user keys, encrypted offline logs, and private evidence stored in the app vault.',
                  variant: 'danger'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-rose-200 bg-rose-50/40 hover:bg-rose-50 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-rose-100 text-rose-700 group-hover:scale-105 transition-transform">
                  <Trash2 className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-rose-700 bg-rose-100 px-1.5 py-0.5 rounded">
                  SECURE WIPE
                </span>
              </div>
              <div className="text-xs font-bold text-slate-900">Wipe Data</div>
              <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                Purge sensitive enterprise data and credential cache
              </div>
            </button>

            {/* 7. Remote Factory Reset */}
            <button
              onClick={() =>
                setPendingCommand({
                  type: 'FACTORY_RESET',
                  title: 'Remote Total Hardware Wipe (Factory Reset)',
                  description:
                    'EXTREME DANGER: This will format all internal partitions and permanently erase the device. This action cannot be reversed.',
                  variant: 'danger'
                })
              }
              disabled={!primaryDevice}
              className="p-3.5 rounded-xl border border-rose-300 bg-rose-100/40 hover:bg-rose-100/70 text-left transition-all disabled:opacity-50 disabled:cursor-not-allowed group shadow-2xs"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="p-2 rounded-lg bg-rose-600 text-white group-hover:scale-105 transition-transform">
                  <RotateCcw className="w-4 h-4" />
                </div>
                <span className="text-[10px] font-bold text-rose-800 bg-rose-200 px-1.5 py-0.5 rounded">
                  TOTAL RESET
                </span>
              </div>
              <div className="text-xs font-bold text-rose-900">Factory Reset</div>
              <div className="text-[11px] text-rose-700 mt-0.5 leading-snug">
                Execute total hardware wipe via Device Owner MDM
              </div>
            </button>
          </div>

          {/* Command History Audit Trail */}
          <div className="pt-4 border-t border-slate-100">
            <span className="text-xs font-bold text-slate-800 block mb-2">Command Execution History:</span>
            {userCommands.length === 0 ? (
              <div className="p-4 bg-slate-50 rounded-lg text-center text-xs text-slate-400 italic">
                No remote commands have been dispatched to this subscriber yet.
              </div>
            ) : (
              <div className="overflow-x-auto rounded-xl border border-slate-200">
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                      <th className="py-2.5 px-3">Command Type</th>
                      <th className="py-2.5 px-3">Target Device</th>
                      <th className="py-2.5 px-3">Status</th>
                      <th className="py-2.5 px-3">Reason / Context</th>
                      <th className="py-2.5 px-3">Dispatched At</th>
                      <th className="py-2.5 px-3">Executed At</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {userCommands.map(cmd => (
                      <tr key={cmd.id} className="hover:bg-slate-50/80">
                        <td className="py-2.5 px-3 font-bold text-slate-900 font-mono">{cmd.command_type}</td>
                        <td className="py-2.5 px-3 text-slate-600">{cmd.device_name}</td>
                        <td className="py-2.5 px-3">
                          <Badge variant={cmd.status === 'COMPLETED' ? 'success' : cmd.status === 'SENT' ? 'info' : 'warning'}>
                            {cmd.status}
                          </Badge>
                        </td>
                        <td className="py-2.5 px-3 text-slate-600 max-w-xs truncate">{cmd.reason}</td>
                        <td className="py-2.5 px-3 text-slate-400 whitespace-nowrap">{cmd.created_at}</td>
                        <td className="py-2.5 px-3 text-slate-400 whitespace-nowrap">{cmd.executed_at || 'In flight...'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 4: LIVE LOCATION TELEMETRY */}
      {(activeTab === 'all' || activeTab === 'location') && (
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <MapPin className="w-4 h-4 text-emerald-600" />
              <span>Live Geolocation Telemetry</span>
            </h3>
            {user.location && (
              <button
                onClick={() => setIsMapModalOpen(true)}
                className="px-3.5 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-1.5 shadow-2xs"
              >
                <span>Open Full Map</span>
                <ExternalLink className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          {user.location ? (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
              <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                <span className="text-slate-400 uppercase text-[10px] font-bold block">GPS Coordinates</span>
                <p className="font-mono text-slate-900 font-bold text-base">
                  {user.location.latitude.toFixed(6)}, {user.location.longitude.toFixed(6)}
                </p>
                <div className="flex items-center gap-2 text-slate-500">
                  <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                  <span>Accuracy: ±{user.location.accuracy} meters</span>
                </div>
              </div>

              <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                <span className="text-slate-400 uppercase text-[10px] font-bold block">Resolved Address</span>
                <p className="font-bold text-slate-900 text-sm">{user.location.address || 'Street address acquired'}</p>
                <div className="flex items-center gap-1.5 text-slate-500 text-[11px]">
                  <Clock className="w-3.5 h-3.5 text-slate-400" />
                  <span>Last synced: {user.location.last_updated}</span>
                </div>
              </div>

              <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                <span className="text-slate-400 uppercase text-[10px] font-bold block">Emergency Share URL</span>
                <p className="text-slate-500 text-[11px]">Direct public tracking link for law enforcement:</p>
                <div className="flex items-center gap-2 mt-1">
                  <span className="font-mono text-xs font-semibold text-emerald-700 bg-white px-2 py-1 rounded border border-emerald-200 truncate flex-1">
                    {user.location.short_url || 'https://sata.app/l/live-track'}
                  </span>
                  <button
                    onClick={() => handleCopyShortUrl(user.location?.short_url)}
                    className="p-1.5 text-slate-600 hover:text-slate-900 rounded bg-white border border-slate-200 shadow-2xs"
                    title="Copy Link"
                  >
                    {copiedUrl ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
                  </button>
                </div>
              </div>
            </div>
          ) : (
            <div className="p-8 bg-slate-50 rounded-xl text-center text-xs text-slate-400">
              No live GPS coordinates received from this device yet.
            </div>
          )}
        </div>
      )}

      {/* TAB 5: INTRUDER PHOTO FORENSICS */}
      {(activeTab === 'all' || activeTab === 'intruders') && (
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <Camera className="w-4 h-4 text-emerald-600" />
              <span>Intruder Photo Forensics ({intruderPhotos.length} Available)</span>
            </h3>
            <span className="text-xs text-slate-400">Captured silently on unauthorized interaction</span>
          </div>

          {intruderPhotos.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {intruderPhotos.map(alert => (
                <div
                  key={alert.id}
                  onClick={() => setSelectedPhotoAlert(alert)}
                  className="group relative rounded-xl overflow-hidden border border-slate-200 bg-slate-900 cursor-pointer shadow-xs hover:border-emerald-500 transition-all"
                >
                  <img
                    src={alert.evidence_url}
                    alt="Intruder Photo"
                    className="w-full h-48 object-cover group-hover:scale-105 transition-transform"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-3.5 flex flex-col justify-between text-white">
                    <span className="text-[10px] font-bold font-mono bg-rose-600 px-2 py-0.5 rounded-full w-fit shadow-xs">
                      INTRUDER CAPTURE
                    </span>
                    <div>
                      <div className="text-xs font-bold">{alert.device_name}</div>
                      <div className="text-[10px] text-slate-300 font-mono mt-0.5">{alert.created_at}</div>
                      <div className="text-[10px] text-emerald-400 mt-1 flex items-center gap-1 font-semibold">
                        <Eye className="w-3 h-3" />
                        <span>Click to Enlarge Evidence</span>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="p-8 bg-slate-50 rounded-xl text-center text-xs text-slate-400">
              No intruder photo captures on file for this user account. Photos appear here automatically when wrong PIN or power button tampering occurs.
            </div>
          )}
        </div>
      )}

      {/* TAB 6: ACTION HISTORY (USER DEVICE ACTIONS & TAMPER LOGS) */}
      {(activeTab === 'all' || activeTab === 'actions') && (
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
              <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <History className="w-4 h-4 text-emerald-600" />
                <span>Device Action & Tamper History ({filteredActions.length} Events)</span>
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Real-time forensic stream of hardware events: power button clicks, SIM removals, PIN failures, and admin commands
              </p>
            </div>

            {/* Filter Chips */}
            <div className="flex items-center gap-1.5 flex-wrap">
              {[
                { id: 'ALL', label: 'All Actions' },
                { id: 'POWER', label: 'Power Button' },
                { id: 'SIM', label: 'SIM Events' },
                { id: 'INTRUDER', label: 'PIN & Intruder' },
                { id: 'COMMANDS', label: 'Commands' },
                { id: 'SENSORS', label: 'Sensors' }
              ].map(f => (
                <button
                  key={f.id}
                  onClick={() => setActionFilter(f.id)}
                  className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors ${
                    actionFilter === f.id
                      ? 'bg-slate-900 text-white'
                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  }`}
                >
                  {f.label}
                </button>
              ))}
            </div>
          </div>

          {/* Action History Timeline */}
          {filteredActions.length === 0 ? (
            <div className="p-10 bg-slate-50 rounded-xl text-center text-xs text-slate-400">
              <History className="w-8 h-8 text-slate-300 mx-auto mb-2" />
              <p className="font-semibold text-slate-700">No action events matching filter</p>
              <p className="text-[11px] text-slate-400 mt-0.5">Device telemetry updates in real-time as interactions occur.</p>
            </div>
          ) : (
            <div className="space-y-3 relative before:absolute before:left-4 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
              {filteredActions.map(act => (
                <div
                  key={act.id}
                  className="relative pl-10 group"
                >
                  {/* Timeline Node Icon */}
                  <div className="absolute left-2 top-3 -translate-x-1/2 w-8 h-8 rounded-full bg-white border-2 border-slate-200 flex items-center justify-center shadow-2xs group-hover:border-emerald-500 transition-colors">
                    {getActionIcon(act.action_type)}
                  </div>

                  {/* Action Content Card */}
                  <div className="p-4 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-slate-300 transition-all shadow-2xs space-y-2">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-bold text-slate-900 text-xs">{act.title}</span>
                        <span
                          className={`px-2 py-0.5 text-[10px] font-bold rounded border ${getActionBadgeColor(
                            act.severity
                          )}`}
                        >
                          {act.severity}
                        </span>
                        <span className="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-slate-200/80 text-slate-700">
                          {act.status}
                        </span>
                      </div>
                      <span className="text-[11px] font-mono text-slate-400 whitespace-nowrap">{act.timestamp}</span>
                    </div>

                    <p className="text-xs text-slate-700 leading-relaxed font-sans">{act.details}</p>

                    <div className="flex items-center gap-4 text-[11px] text-slate-400 pt-1 border-t border-slate-200/40 flex-wrap">
                      <span className="font-medium text-slate-600">Hardware: {act.device_name || primaryDevice?.device_name || 'Device'}</span>
                      {act.location_summary && (
                        <span>• Location: {act.location_summary}</span>
                      )}
                      {act.ip_address && (
                        <span>• IP: {act.ip_address}</span>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Confirmation & Forensics Modals */}
      <ConfirmationModal
        isOpen={Boolean(pendingCommand)}
        onClose={() => setPendingCommand(null)}
        onConfirm={handleConfirmCommand}
        title={pendingCommand?.title || 'Confirm Command'}
        description={pendingCommand?.description || ''}
        confirmLabel="Authorize Command Dispatch"
        variant={pendingCommand?.variant || 'danger'}
        deviceInfo={
          primaryDevice
            ? {
                userName: user.name,
                deviceName: primaryDevice.device_name,
                platform: primaryDevice.platform
              }
            : undefined
        }
      />

      <PhotoModal
        isOpen={Boolean(selectedPhotoAlert)}
        onClose={() => setSelectedPhotoAlert(null)}
        alert={selectedPhotoAlert}
      />

      <MapModal
        isOpen={isMapModalOpen}
        onClose={() => setIsMapModalOpen(false)}
        userName={user.name}
        deviceName={primaryDevice?.device_name}
        latitude={user.location?.latitude || null}
        longitude={user.location?.longitude || null}
        accuracy={user.location?.accuracy}
        address={user.location?.address}
        lastUpdated={user.location?.last_updated}
        shortUrl={user.location?.short_url}
      />

      {/* ADD / EDIT TRUSTED EMERGENCY USER MODAL */}
      {isContactModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-lg bg-emerald-100 text-emerald-700">
                  <Phone className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">
                    {editingContact ? 'Edit Trusted Emergency Contact' : 'Add Trusted Emergency Contact'}
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Full Legal Name, Mobile Number, and Email are mandatory
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsContactModalOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleSaveContact} className="p-5 space-y-4">
              <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-900 flex items-start gap-2">
                <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <div>
                  <strong>Emergency Photo & Location Recipient:</strong> Front camera intruder photos and Google Maps links are sent exclusively to this email. The device owner will only receive an OTP.
                </div>
              </div>

              {/* Full Legal Name */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Full Legal Name <span className="text-rose-500 font-bold">*</span>
                </label>
                <div className="relative">
                  <UserIcon className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    value={contactFormData.name}
                    onChange={e => setContactFormData({ ...contactFormData, name: e.target.value })}
                    placeholder="e.g. John Doe"
                    className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                      contactErrors.name ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                    }`}
                  />
                </div>
                {contactErrors.name && (
                  <p className="text-[11px] text-rose-500 mt-1">{contactErrors.name}</p>
                )}
              </div>

              {/* Mobile Phone Number */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Mobile Number (E.164) <span className="text-rose-500 font-bold">*</span>
                </label>
                <div className="relative">
                  <Phone className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    value={contactFormData.mobile_number}
                    onChange={e => setContactFormData({ ...contactFormData, mobile_number: e.target.value })}
                    placeholder="+14155552671"
                    className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                      contactErrors.mobile_number ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                    }`}
                  />
                </div>
                {contactErrors.mobile_number ? (
                  <p className="text-[11px] text-rose-500 mt-1">{contactErrors.mobile_number}</p>
                ) : (
                  <p className="text-[10px] text-slate-400 mt-0.5">Required for SMS alerts and emergency contact verification</p>
                )}
              </div>

              {/* Email Address */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Email Address <span className="text-rose-500 font-bold">*</span>
                </label>
                <div className="relative">
                  <Mail className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="email"
                    value={contactFormData.email}
                    onChange={e => setContactFormData({ ...contactFormData, email: e.target.value })}
                    placeholder="trusted.contact@example.com"
                    className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                      contactErrors.email ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                    }`}
                  />
                </div>
                {contactErrors.email ? (
                  <p className="text-[11px] text-rose-500 mt-1">{contactErrors.email}</p>
                ) : (
                  <p className="text-[10px] text-indigo-600 mt-0.5 font-medium">
                    Mandatory: High-resolution intruder photos and Google Maps links will be emailed here
                  </p>
                )}
              </div>

              {/* Relationship */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Relationship to User
                </label>
                <select
                  value={contactFormData.relationship}
                  onChange={e => setContactFormData({ ...contactFormData, relationship: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                >
                  <option value="Spouse">Spouse</option>
                  <option value="Parent">Parent</option>
                  <option value="Sibling">Sibling</option>
                  <option value="Son">Son</option>
                  <option value="Daughter">Daughter</option>
                  <option value="Partner">Partner</option>
                  <option value="Close Friend">Close Friend</option>
                  <option value="Colleague">Colleague</option>
                  <option value="Emergency Contact">Emergency Contact</option>
                </select>
              </div>

              {/* Verification Status */}
              <div className="pt-2">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={contactFormData.is_verified}
                    onChange={e => setContactFormData({ ...contactFormData, is_verified: e.target.checked })}
                    className="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500"
                  />
                  <span className="text-xs text-slate-700 font-medium">
                    Mark as OTP Verified
                  </span>
                </label>
              </div>

              {/* Modal Actions */}
              <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setIsContactModalOpen(false)}
                  className="px-3.5 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSavingContact}
                  className="px-4 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors shadow-2xs disabled:opacity-50"
                >
                  {isSavingContact ? 'Saving...' : editingContact ? 'Save Changes' : 'Add Trusted Contact'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DELETE TRUSTED CONTACT CONFIRMATION MODAL */}
      {contactToDelete && (
        <ConfirmationModal
          isOpen={Boolean(contactToDelete)}
          onClose={() => setContactToDelete(null)}
          onConfirm={handleConfirmDeleteContact}
          title="Remove Trusted Emergency Contact"
          description={`Are you sure you want to remove ${contactToDelete.name} (${contactToDelete.email}) from User #${user.id}'s emergency contacts? They will no longer receive emergency intruder photos or live location links.`}
          confirmLabel={isDeletingContact ? 'Removing...' : 'Remove Contact'}
          variant="danger"
        />
      )}
    </div>
  );
};
