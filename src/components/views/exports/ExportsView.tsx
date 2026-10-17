import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { Download, Users, CreditCard, KeyRound, ShieldAlert, FileText, CheckCircle2 } from 'lucide-react';

interface ExportsViewProps {
  initialTab?: 'users' | 'subscriptions' | 'codes' | 'logs';
}

export const ExportsView: React.FC<ExportsViewProps> = ({ initialTab = 'users' }) => {
  const { users, subscriptions, activationCodes, securityAlerts, systemLogs, exportToCSV } = useAdmin();
  const [activeTab, setActiveTab] = useState<'users' | 'subscriptions' | 'codes' | 'logs'>(initialTab);

  // Filters for User Export
  const [userStatusFilter, setUserStatusFilter] = useState('ALL');

  // Filters for Subscriptions Export
  const [subStatusFilter, setSubStatusFilter] = useState('ALL');

  // Filters for Codes Export
  const [codeStatusFilter, setCodeStatusFilter] = useState('ALL');

  const handleExportUsers = () => {
    const filtered = users.filter(u => userStatusFilter === 'ALL' || u.status === userStatusFilter);
    const headers = ['ID', 'Name', 'Phone', 'Email', 'Status', 'Subscription', 'Created At'];
    const rows = filtered.map(u => [u.id, u.name, u.phone, u.email, u.status, u.subscription_status, u.created_at]);
    exportToCSV('sata_users_filtered', headers, rows);
  };

  const handleExportSubscriptions = () => {
    const filtered = subscriptions.filter(s => subStatusFilter === 'ALL' || s.status === subStatusFilter);
    const headers = ['ID', 'User', 'Plan', 'Status', 'Starts At', 'Expires At', 'Price Paid', 'Reference'];
    const rows = filtered.map(s => [s.id, s.user_name, s.plan_name, s.status, s.starts_at, s.expires_at, `$${s.price_paid}`, s.payment_reference]);
    exportToCSV('sata_subscriptions_filtered', headers, rows);
  };

  const handleExportCodes = () => {
    const filtered = activationCodes.filter(c => codeStatusFilter === 'ALL' || c.status === codeStatusFilter);
    const headers = ['Code', 'Plan', 'Status', 'User', 'Generated At', 'Expires At', 'Notes'];
    const rows = filtered.map(c => [c.code, c.plan_name, c.status, c.user_name || 'Unassigned', c.generated_at, c.expires_at, c.notes || '']);
    exportToCSV('sata_activation_codes_filtered', headers, rows);
  };

  const handleExportLogs = () => {
    const headers = ['ID', 'Timestamp', 'Action', 'Module', 'User', 'IP Address', 'Device', 'Result', 'Details'];
    const rows = systemLogs.map(l => [l.id, l.timestamp, l.action, l.module, l.user_name, l.ip, l.device, l.result, l.details || '']);
    exportToCSV('sata_system_audit_logs', headers, rows);
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-slate-900 tracking-tight">Data Export Center</h1>
        <p className="text-xs text-slate-500">
          Generate structured CSV data packages for compliance, external auditing, and business analysis
        </p>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200">
        {[
          { id: 'users', label: 'Export Users', icon: Users },
          { id: 'subscriptions', label: 'Export Subscriptions', icon: CreditCard },
          { id: 'codes', label: 'Export Activation Codes', icon: KeyRound },
          { id: 'logs', label: 'Export Security Logs', icon: FileText }
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

      {/* Export Cards */}
      <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-2xs max-w-2xl space-y-5">
        {activeTab === 'users' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-900">Configure User Export Filters</h3>
            <p className="text-xs text-slate-500">
              Apply criteria to export active accounts, pending activations, or suspended users.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Filter by Status</label>
              <select
                value={userStatusFilter}
                onChange={e => setUserStatusFilter(e.target.value)}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800"
              >
                <option value="ALL">All Account Statuses ({users.length} total)</option>
                <option value="ACTIVE">Active Users Only</option>
                <option value="AWAITING_ACTIVATION">Awaiting Activation Only</option>
                <option value="DISABLED">Disabled / Suspended Users</option>
              </select>
            </div>

            <button
              onClick={handleExportUsers}
              className="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-2"
            >
              <Download className="w-4 h-4" />
              <span>Download User Records (.CSV)</span>
            </button>
          </div>
        )}

        {activeTab === 'subscriptions' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-900">Configure Subscription Export Filters</h3>
            <p className="text-xs text-slate-500">
              Export payment references, plan periods, and subscriber history.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Filter by Status</label>
              <select
                value={subStatusFilter}
                onChange={e => setSubStatusFilter(e.target.value)}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800"
              >
                <option value="ALL">All Subscriptions ({subscriptions.length} total)</option>
                <option value="ACTIVE">Active Subscriptions Only</option>
                <option value="EXPIRING">Expiring Subscriptions Only</option>
                <option value="EXPIRED">Expired Subscriptions</option>
              </select>
            </div>

            <button
              onClick={handleExportSubscriptions}
              className="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-2"
            >
              <Download className="w-4 h-4" />
              <span>Download Subscriptions (.CSV)</span>
            </button>
          </div>
        )}

        {activeTab === 'codes' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-900">Configure Activation Code Export</h3>
            <p className="text-xs text-slate-500">
              Export generated batches for bulk customer distribution or partner retail fulfillment.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Filter by Code Status</label>
              <select
                value={codeStatusFilter}
                onChange={e => setCodeStatusFilter(e.target.value)}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800"
              >
                <option value="ALL">All Codes ({activationCodes.length} total)</option>
                <option value="UNUSED">Unused / Available Vouchers</option>
                <option value="USED">Redeemed Codes</option>
                <option value="REVOKED">Revoked Codes</option>
              </select>
            </div>

            <button
              onClick={handleExportCodes}
              className="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-2"
            >
              <Download className="w-4 h-4" />
              <span>Download Codes Package (.CSV)</span>
            </button>
          </div>
        )}

        {activeTab === 'logs' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-900">Export Administrative Audit Trail</h3>
            <p className="text-xs text-slate-500">
              Complete chronological audit trail with actor IP, device, action parameters, and cryptographic hashes.
            </p>

            <button
              onClick={handleExportLogs}
              className="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-2"
            >
              <Download className="w-4 h-4" />
              <span>Download Full Security Audit Log (.CSV)</span>
            </button>
          </div>
        )}
      </div>
    </div>
  );
};
