import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  CreditCard,
  Search,
  Filter,
  Download,
  RotateCw,
  XCircle,
  Trash2,
  Eye,
  Calendar,
  CheckCircle2
} from 'lucide-react';
import { Badge } from '../../common/Badge';
import { ConfirmationModal } from '../../common/ConfirmationModal';
import { Subscription } from '../../../types/admin';

export const SubscriptionsView: React.FC = () => {
  const {
    subscriptions,
    renewSubscription,
    cancelSubscription,
    deleteSubscription,
    setCurrentRoute,
    searchTerm,
    setSearchTerm,
    exportToCSV
  } = useAdmin();

  const [statusFilter, setStatusFilter] = useState<string>('ALL');
  const [cancelingSub, setCancelingSub] = useState<Subscription | null>(null);
  const [renewingSub, setRenewingSub] = useState<Subscription | null>(null);
  const [deletingSub, setDeletingSub] = useState<Subscription | null>(null);

  const filteredSubs = useMemo(() => {
    return subscriptions.filter(s => {
      const matchSearch =
        searchTerm === '' ||
        s.user_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        s.user_email.toLowerCase().includes(searchTerm.toLowerCase()) ||
        s.plan_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        s.payment_reference.toLowerCase().includes(searchTerm.toLowerCase());

      const matchStatus = statusFilter === 'ALL' || s.status === statusFilter;
      return matchSearch && matchStatus;
    });
  }, [subscriptions, searchTerm, statusFilter]);

  const handleExport = () => {
    const headers = ['ID', 'User', 'Email', 'Plan', 'Starts At', 'Expires At', 'Status', 'Paid', 'Reference'];
    const rows = filteredSubs.map(s => [
      s.id,
      s.user_name,
      s.user_email,
      s.plan_name,
      s.starts_at,
      s.expires_at,
      s.status,
      `$${s.price_paid}`,
      s.payment_reference
    ]);
    exportToCSV('sata_subscriptions', headers, rows);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Active Subscriptions</h1>
          <p className="text-xs text-slate-500">Monitor entitlements, renewals, and expiration schedules</p>
        </div>

        <button
          onClick={handleExport}
          className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <Download className="w-3.5 h-3.5" />
          <span>Export Subscriptions</span>
        </button>
      </div>

      {/* Filter Bar */}
      <div className="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search by user, email, plan, or reference..."
            value={searchTerm}
            onChange={e => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
        </div>

        <div className="flex items-center gap-1.5 text-xs">
          <span className="text-slate-500 font-medium">Status:</span>
          <select
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
            className="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:bg-white"
          >
            <option value="ALL">All Subscriptions</option>
            <option value="ACTIVE">Active</option>
            <option value="EXPIRING">Expiring (≤ 7 days)</option>
            <option value="EXPIRED">Expired</option>
            <option value="CANCELLED">Cancelled</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[11px] tracking-wider">
                <th className="py-3 px-4">User</th>
                <th className="py-3 px-4">Plan</th>
                <th className="py-3 px-4">Start Date</th>
                <th className="py-3 px-4">Expiry Date</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Reference</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredSubs.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-slate-400">
                    No subscriptions found
                  </td>
                </tr>
              ) : (
                filteredSubs.map(sub => (
                  <tr key={sub.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3 px-4">
                      <div className="font-bold text-slate-900">{sub.user_name}</div>
                      <div className="text-[11px] text-slate-400 truncate max-w-[160px]">{sub.user_email}</div>
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-semibold text-slate-800">{sub.plan_name}</div>
                      <span className="font-mono text-[10px] text-slate-400">{sub.plan_code}</span>
                    </td>
                    <td className="py-3 px-4 text-slate-600 whitespace-nowrap">
                      {sub.starts_at ? (sub.starts_at.includes(' ') ? sub.starts_at.split(' ')[0] : sub.starts_at.split('T')[0]) : '-'}
                    </td>
                    <td className="py-3 px-4 whitespace-nowrap">
                      <div className="text-slate-900 font-medium">
                        {sub.expires_at ? (sub.expires_at.includes(' ') ? sub.expires_at.split(' ')[0] : sub.expires_at.split('T')[0]) : '-'}
                      </div>
                      <span className="text-[10px] text-slate-400">{sub.days_remaining} days left</span>
                    </td>
                    <td className="py-3 px-4">
                      <Badge
                        variant={
                          sub.status === 'ACTIVE'
                            ? 'success'
                            : sub.status === 'EXPIRING'
                            ? 'warning'
                            : 'neutral'
                        }
                      >
                        {sub.status}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-500 text-[11px]">{sub.payment_reference}</td>
                    <td className="py-3 px-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => setCurrentRoute(`/admin/users/${sub.user_id}`)}
                          title="View User Details"
                          className="p-1.5 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-md transition-colors"
                        >
                          <Eye className="w-3.5 h-3.5" />
                        </button>
                        <button
                          onClick={() => setRenewingSub(sub)}
                          title="Renew / Extend Subscription"
                          className="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-md transition-colors"
                        >
                          <RotateCw className="w-3.5 h-3.5" />
                        </button>
                        {sub.status !== 'CANCELLED' && (
                          <button
                            onClick={() => setCancelingSub(sub)}
                            title="Cancel Subscription"
                            className="p-1.5 text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                          >
                            <XCircle className="w-3.5 h-3.5" />
                          </button>
                        )}
                        <button
                          onClick={() => setDeletingSub(sub)}
                          title="Delete Record"
                          className="p-1.5 text-rose-500 hover:bg-rose-50 rounded-md transition-colors"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Renew Modal */}
      <ConfirmationModal
        isOpen={Boolean(renewingSub)}
        onClose={() => setRenewingSub(null)}
        onConfirm={() => {
          if (renewingSub) {
            renewSubscription(renewingSub.id, 30);
            setRenewingSub(null);
          }
        }}
        title="Extend Subscription"
        description={`Extend "${renewingSub?.plan_name}" for ${renewingSub?.user_name} by an additional 30 days?`}
        confirmLabel="Renew +30 Days"
        variant="info"
      />

      {/* Cancel Modal */}
      <ConfirmationModal
        isOpen={Boolean(cancelingSub)}
        onClose={() => setCancelingSub(null)}
        onConfirm={() => {
          if (cancelingSub) {
            cancelSubscription(cancelingSub.id);
            setCancelingSub(null);
          }
        }}
        title="Terminate Subscription"
        description={`Are you sure you want to cancel the active subscription for ${cancelingSub?.user_name}? Background protection and command dispatch will be paused.`}
        confirmLabel="Cancel Subscription"
        variant="warning"
      />

      {/* Delete Modal */}
      <ConfirmationModal
        isOpen={Boolean(deletingSub)}
        onClose={() => setDeletingSub(null)}
        onConfirm={() => {
          if (deletingSub) {
            deleteSubscription(deletingSub.id);
            setDeletingSub(null);
          }
        }}
        title="Delete Subscription Record"
        description="Permanently delete this subscription entry from the accounting history?"
        confirmLabel="Delete Record"
        variant="danger"
      />
    </div>
  );
};
