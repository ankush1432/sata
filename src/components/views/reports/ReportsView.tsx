import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { BarChart3, Users, CreditCard, KeyRound, ShieldAlert, Download, Calendar } from 'lucide-react';

interface ReportsViewProps {
  initialTab?: 'users' | 'subscriptions' | 'activation' | 'security';
}

export const ReportsView: React.FC<ReportsViewProps> = ({ initialTab = 'users' }) => {
  const { users, subscriptions, activationCodes, securityAlerts, exportToCSV } = useAdmin();
  const [activeTab, setActiveTab] = useState<'users' | 'subscriptions' | 'activation' | 'security'>(initialTab);

  const totalUsers = users.length;
  const activeUsers = users.filter(u => u.status === 'ACTIVE').length;
  const totalRevenue = subscriptions.reduce((sum, s) => sum + s.price_paid, 0);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-slate-900 tracking-tight">Executive Management Reports</h1>
        <p className="text-xs text-slate-500">Consolidated analytics and business metrics for administration</p>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200">
        {[
          { id: 'users', label: 'User Report', icon: Users },
          { id: 'subscriptions', label: 'Subscription Report', icon: CreditCard },
          { id: 'activation', label: 'Activation Report', icon: KeyRound },
          { id: 'security', label: 'Security Report', icon: ShieldAlert }
        ].map(tab => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors ${
                isActive
                  ? 'border-emerald-600 text-emerald-600 bg-emerald-50/30'
                  : 'border-transparent text-slate-600 hover:text-slate-900'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* Content based on Active Tab */}
      {activeTab === 'users' && (
        <div className="space-y-5">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Total Registered</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">{totalUsers}</div>
              <p className="text-[11px] text-emerald-600 mt-1">100% database integrity</p>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Active Protection Users</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">{activeUsers}</div>
              <p className="text-[11px] text-slate-500 mt-1">{((activeUsers / (totalUsers || 1)) * 100).toFixed(1)}% ratio</p>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Hardware Devices Enrolled</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">
                {users.reduce((acc, u) => acc + u.devices.length, 0)}
              </div>
              <p className="text-[11px] text-blue-600 mt-1">Multi-device clustering supported</p>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'subscriptions' && (
        <div className="space-y-5">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Cumulative Paid Revenue</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">${totalRevenue.toFixed(2)}</div>
              <p className="text-[11px] text-emerald-600 mt-1">Direct stripe/in-app activations</p>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Active Licenses</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">
                {subscriptions.filter(s => s.status === 'ACTIVE').length}
              </div>
              <p className="text-[11px] text-slate-500 mt-1">Valid anti-theft licenses</p>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Expiring Soon (≤ 7 Days)</span>
              <div className="text-2xl font-bold text-amber-600 mt-1">
                {subscriptions.filter(s => s.days_remaining <= 7 && s.status === 'ACTIVE').length}
              </div>
              <p className="text-[11px] text-amber-700 mt-1">Automated reminders scheduled</p>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'activation' && (
        <div className="space-y-5">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Total Generated Vouchers</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">{activationCodes.length}</div>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Available Unredeemed</span>
              <div className="text-2xl font-bold text-emerald-700 mt-1">
                {activationCodes.filter(c => c.status === 'UNUSED').length}
              </div>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Redemption Success Rate</span>
              <div className="text-2xl font-bold text-blue-700 mt-1">
                {(
                  (activationCodes.filter(c => c.status === 'USED').length /
                    (activationCodes.length || 1)) *
                  100
                ).toFixed(1)}
                %
              </div>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'security' && (
        <div className="space-y-5">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Total Security Incidents</span>
              <div className="text-2xl font-bold text-slate-900 mt-1">{securityAlerts.length}</div>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Intruder Camera Captures</span>
              <div className="text-2xl font-bold text-rose-600 mt-1">
                {securityAlerts.filter(a => a.alert_type === 'INTRUDER_PHOTO_CAPTURED').length}
              </div>
            </div>
            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
              <span className="text-slate-500 text-xs font-medium">Resolved Incidents</span>
              <div className="text-2xl font-bold text-emerald-700 mt-1">
                {securityAlerts.filter(a => a.status === 'RESOLVED').length}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
