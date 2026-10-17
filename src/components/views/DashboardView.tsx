import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Users,
  UserCheck,
  UserX,
  CreditCard,
  KeyRound,
  ShieldAlert,
  MapPin,
  TrendingUp,
  DollarSign,
  ArrowUpRight,
  Clock,
  CheckCircle2,
  ChevronRight,
  Camera,
  Layers,
  Activity
} from 'lucide-react';
import { Badge } from '../common/Badge';
import { PhotoModal } from '../common/PhotoModal';
import { MapModal } from '../common/MapModal';
import { SecurityAlert } from '../../types/admin';

export const DashboardView: React.FC = () => {
  const { users, subscriptions, activationCodes, securityAlerts, setCurrentRoute } = useAdmin();

  // Modals for quick inspection from recent feeds
  const [selectedAlertForPhoto, setSelectedAlertForPhoto] = useState<SecurityAlert | null>(null);
  const [selectedMapAlert, setSelectedMapAlert] = useState<SecurityAlert | null>(null);

  // Statistics Calculations
  const totalUsers = users.length;
  const activeUsers = users.filter(u => u.status === 'ACTIVE').length;
  const pendingOtpUsers = users.filter(u => u.status === 'PENDING_OTP').length;
  const awaitingActivationUsers = users.filter(u => u.status === 'AWAITING_ACTIVATION').length;
  const disabledUsers = users.filter(u => u.status === 'DISABLED').length;

  const activeSubscriptions = subscriptions.filter(s => s.status === 'ACTIVE').length;
  const expiringSubscriptions = subscriptions.filter(s => s.status === 'EXPIRING' || s.days_remaining <= 7).length;
  const expiredSubscriptions = subscriptions.filter(s => s.status === 'EXPIRED').length;
  const totalRevenue = subscriptions.reduce((acc, curr) => acc + (curr.price_paid || 0), 0);

  const totalCodes = activationCodes.length;
  const unusedCodes = activationCodes.filter(c => c.status === 'UNUSED').length;
  const usedCodes = activationCodes.filter(c => c.status === 'USED').length;
  const expiredCodes = activationCodes.filter(c => c.status === 'EXPIRED' || c.status === 'REVOKED').length;

  const totalAlerts = securityAlerts.length;
  const intruderEvents = securityAlerts.filter(a => a.alert_type === 'INTRUDER_PHOTO_CAPTURED').length;
  const locationPings = users.filter(u => u.location !== undefined).length;
  const pendingActions = securityAlerts.filter(a => a.status === 'UNRESOLVED').length;

  // Recent feeds
  const recentUsers = [...users].slice(0, 5);
  const recentActivations = [...activationCodes].filter(c => c.status === 'USED').slice(0, 5);
  const recentAlerts = [...securityAlerts].slice(0, 5);
  const recentSubscriptions = [...subscriptions].slice(0, 5);

  return (
    <div className="space-y-6">
      {/* Header Title & Quick Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">System Overview</h1>
          <p className="text-xs text-slate-500">Operational administration and high-level health indicators</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setCurrentRoute('/admin/users/create')}
            className="px-3.5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-1.5"
          >
            <span>+ Add New User</span>
          </button>
          <button
            onClick={() => setCurrentRoute('/admin/activation-codes/generate')}
            className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1.5"
          >
            <span>Generate Codes</span>
          </button>
        </div>
      </div>

      {/* Primary KPI Grid (4 Columns) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* User Statistics Card */}
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
          <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
            <span>User Accounts</span>
            <div className="p-2 rounded-lg bg-emerald-50 text-emerald-600">
              <Users className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-bold text-slate-900">{totalUsers}</div>
            <div className="mt-2 pt-2 border-t border-slate-100 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600">
              <span className="text-emerald-700 font-medium">{activeUsers} Active</span>
              <span>{pendingOtpUsers} Pending OTP</span>
              <span>{awaitingActivationUsers} Awaiting Act.</span>
              <span className="text-rose-600">{disabledUsers} Disabled</span>
            </div>
          </div>
        </div>

        {/* Subscription Card */}
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
          <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
            <span>Subscriptions</span>
            <div className="p-2 rounded-lg bg-blue-50 text-blue-600">
              <CreditCard className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-bold text-slate-900">{activeSubscriptions}</div>
            <div className="mt-2 pt-2 border-t border-slate-100 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600">
              <span className="text-blue-700 font-medium">${totalRevenue.toFixed(2)} Revenue</span>
              <span className="text-amber-600">{expiringSubscriptions} Expiring</span>
              <span className="text-slate-400">{expiredSubscriptions} Expired</span>
            </div>
          </div>
        </div>

        {/* Activation Codes Card */}
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
          <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
            <span>Activation Codes</span>
            <div className="p-2 rounded-lg bg-purple-50 text-purple-600">
              <KeyRound className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-bold text-slate-900">{totalCodes}</div>
            <div className="mt-2 pt-2 border-t border-slate-100 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600">
              <span className="text-emerald-600 font-medium">{unusedCodes} Unused</span>
              <span className="text-purple-600">{usedCodes} Redeemed</span>
              <span className="text-slate-400">{expiredCodes} Expired/Revoked</span>
            </div>
          </div>
        </div>

        {/* Security & Forensics Card */}
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
          <div className="flex items-center justify-between text-slate-500 text-xs font-medium">
            <span>Security Incidents</span>
            <div className="p-2 rounded-lg bg-rose-50 text-rose-600">
              <ShieldAlert className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-bold text-slate-900">{totalAlerts}</div>
            <div className="mt-2 pt-2 border-t border-slate-100 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600">
              <span className="text-rose-600 font-medium">{intruderEvents} Intruder Photos</span>
              <span className="text-amber-600">{pendingActions} Pending Review</span>
              <span className="text-slate-500">{locationPings} Active GPS</span>
            </div>
          </div>
        </div>
      </div>

      {/* Visual Analytics / Mini-Trends Section */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Registration Velocity Bar */}
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs lg:col-span-2">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Device Enrollment & Protection Trends</h3>
              <p className="text-xs text-slate-500">Weekly progression of active mobile devices and security coverage</p>
            </div>
            <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md">
              +14.2% Growth
            </span>
          </div>

          <div className="h-44 flex items-end gap-3 pt-6 pb-2 px-2">
            {[
              { day: 'Mon', active: 65, alerts: 4 },
              { day: 'Tue', active: 78, alerts: 8 },
              { day: 'Wed', active: 72, alerts: 6 },
              { day: 'Thu', active: 85, alerts: 11 },
              { day: 'Fri', active: 94, alerts: 14 },
              { day: 'Sat', active: 89, alerts: 9 },
              { day: 'Sun', active: 96, alerts: 5 }
            ].map(item => (
              <div key={item.day} className="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
                <div className="w-full flex items-end justify-center gap-1 h-32">
                  <div
                    style={{ height: `${item.active}%` }}
                    className="w-1/2 bg-emerald-500 rounded-t-sm hover:bg-emerald-600 transition-all cursor-pointer"
                    title={`Active Devices: ${item.active}`}
                  />
                  <div
                    style={{ height: `${item.alerts * 5}%` }}
                    className="w-1/2 bg-rose-400 rounded-t-sm hover:bg-rose-500 transition-all cursor-pointer"
                    title={`Security Alerts: ${item.alerts}`}
                  />
                </div>
                <span className="text-[11px] font-medium text-slate-500">{item.day}</span>
              </div>
            ))}
          </div>

          <div className="flex items-center justify-center gap-6 pt-3 border-t border-slate-100 text-xs text-slate-600">
            <div className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-emerald-500" />
              <span>Active Telemetry Pings</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-rose-400" />
              <span>Security Violations</span>
            </div>
          </div>
        </div>

        {/* System Distribution Card */}
        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
          <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider mb-4">Hardware Ecosystem Breakdown</h3>
          <div className="space-y-4">
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-slate-600 font-medium">Android (Device Owner / Admin)</span>
                <span className="text-slate-900 font-bold">78%</span>
              </div>
              <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                <div className="w-[78%] h-full bg-emerald-500" />
              </div>
              <p className="text-[10px] text-slate-400 mt-1">Full support: Kiosk Lock, Wipe, Reset, Camera</p>
            </div>

            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-slate-600 font-medium">Apple iOS (Supervised & Standard)</span>
                <span className="text-slate-900 font-bold">22%</span>
              </div>
              <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                <div className="w-[22%] h-full bg-blue-500" />
              </div>
              <p className="text-[10px] text-slate-400 mt-1">Sandbox compliant: Location & APNs Push</p>
            </div>

            <div className="p-3 bg-slate-50 rounded-lg border border-slate-100 text-xs text-slate-600 space-y-1">
              <div className="flex justify-between">
                <span>Active Live Sessions:</span>
                <span className="font-semibold text-slate-900">{locationPings} devices</span>
              </div>
              <div className="flex justify-between">
                <span>Pending Remote Commands:</span>
                <span className="font-semibold text-emerald-700">0 queued</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Dual Activity Panels (Recent Users & Recent Alerts) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Users Table */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Users</h3>
            <button
              onClick={() => setCurrentRoute('/admin/users')}
              className="text-xs text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1"
            >
              <span>View All Users</span>
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="divide-y divide-slate-100">
            {recentUsers.map(user => (
              <div key={user.id} className="p-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0">
                    {user.name.charAt(0)}
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-slate-900 truncate">{user.name}</span>
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
                    </div>
                    <div className="text-[11px] text-slate-500 truncate">{user.email} • {user.phone}</div>
                  </div>
                </div>

                <button
                  onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
                  className="px-2.5 py-1 text-[11px] font-medium text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200 transition-colors"
                >
                  Inspect
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Recent Security Alerts Feed */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Security Alerts</h3>
            <button
              onClick={() => setCurrentRoute('/admin/security-alerts')}
              className="text-xs text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1"
            >
              <span>View All Alerts</span>
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="divide-y divide-slate-100">
            {recentAlerts.map(alert => (
              <div key={alert.id} className="p-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                <div className="flex items-start gap-3 min-w-0">
                  <div className={`p-2 rounded-lg shrink-0 mt-0.5 ${
                    alert.severity === 'CRITICAL' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'
                  }`}>
                    {alert.alert_type === 'INTRUDER_PHOTO_CAPTURED' ? (
                      <Camera className="w-4 h-4" />
                    ) : (
                      <ShieldAlert className="w-4 h-4" />
                    )}
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-slate-900 truncate">
                        {alert.alert_type.replace(/_/g, ' ')}
                      </span>
                      <Badge variant={alert.status === 'UNRESOLVED' ? 'danger' : 'neutral'}>
                        {alert.status}
                      </Badge>
                    </div>
                    <p className="text-[11px] text-slate-500 mt-0.5 truncate">
                      {alert.user_name} ({alert.device_name})
                    </p>
                    <span className="text-[10px] text-slate-400">{alert.created_at}</span>
                  </div>
                </div>

                <div className="flex items-center gap-1.5 shrink-0">
                  {alert.evidence_url && (
                    <button
                      onClick={() => setSelectedAlertForPhoto(alert)}
                      className="p-1.5 rounded-md text-slate-600 hover:text-emerald-600 hover:bg-slate-100 transition-colors"
                      title="View Intruder Snapshot"
                    >
                      <Camera className="w-4 h-4" />
                    </button>
                  )}
                  {alert.location && (
                    <button
                      onClick={() => setSelectedMapAlert(alert)}
                      className="p-1.5 rounded-md text-slate-600 hover:text-blue-600 hover:bg-slate-100 transition-colors"
                      title="View on Map"
                    >
                      <MapPin className="w-4 h-4" />
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Dual Activity Panels: Recent Activations & Subscription Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Activations */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Code Redemptions</h3>
            <button
              onClick={() => setCurrentRoute('/admin/activation-codes')}
              className="text-xs text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1"
            >
              <span>View All Codes</span>
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
          <div className="divide-y divide-slate-100">
            {recentActivations.length === 0 ? (
              <div className="p-6 text-center text-xs text-slate-400">No recent activations</div>
            ) : (
              recentActivations.map(act => (
                <div key={act.id} className="p-4 flex items-center justify-between hover:bg-slate-50">
                  <div>
                    <div className="font-mono text-xs font-bold text-slate-900">{act.code}</div>
                    <div className="text-[11px] text-slate-500 mt-0.5">
                      Redeemed by <span className="font-medium text-slate-700">{act.user_name || 'User'}</span> for {act.plan_name}
                    </div>
                  </div>
                  <span className="text-[11px] text-slate-400">{act.activated_at?.split(' ')[0]}</span>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Recent Subscription Activity */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Active Subscription Coverage</h3>
            <button
              onClick={() => setCurrentRoute('/admin/subscriptions')}
              className="text-xs text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1"
            >
              <span>View Subscriptions</span>
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
          <div className="divide-y divide-slate-100">
            {recentSubscriptions.map(sub => (
              <div key={sub.id} className="p-4 flex items-center justify-between hover:bg-slate-50">
                <div>
                  <div className="text-xs font-bold text-slate-900">{sub.user_name}</div>
                  <div className="text-[11px] text-slate-500">{sub.plan_name}</div>
                </div>
                <div className="text-right">
                  <Badge variant={sub.status === 'ACTIVE' ? 'success' : 'warning'}>{sub.status}</Badge>
                  <div className="text-[10px] text-slate-400 mt-0.5">{sub.days_remaining} days remaining</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Modals for Quick Inspection */}
      <PhotoModal
        isOpen={Boolean(selectedAlertForPhoto)}
        onClose={() => setSelectedAlertForPhoto(null)}
        alert={selectedAlertForPhoto}
      />

      <MapModal
        isOpen={Boolean(selectedMapAlert)}
        onClose={() => setSelectedMapAlert(null)}
        userName={selectedMapAlert?.user_name}
        deviceName={selectedMapAlert?.device_name}
        latitude={selectedMapAlert?.location?.latitude || null}
        longitude={selectedMapAlert?.location?.longitude || null}
        accuracy={selectedMapAlert?.location?.accuracy}
        address={selectedMapAlert?.location?.address}
        lastUpdated={selectedMapAlert?.created_at}
      />
    </div>
  );
};
