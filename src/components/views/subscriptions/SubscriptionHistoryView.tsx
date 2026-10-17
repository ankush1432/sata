import React from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { History, Download, Calendar } from 'lucide-react';
import { Badge } from '../../common/Badge';

export const SubscriptionHistoryView: React.FC = () => {
  const { subscriptions, exportToCSV } = useAdmin();

  const handleExport = () => {
    const headers = ['ID', 'User', 'Plan', 'Start Date', 'Expiry Date', 'Status', 'Price Paid', 'Reference'];
    const rows = subscriptions.map(s => [
      s.id,
      s.user_name,
      s.plan_name,
      s.starts_at,
      s.expires_at,
      s.status,
      `$${s.price_paid}`,
      s.payment_reference
    ]);
    exportToCSV('sata_subscription_history', headers, rows);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Subscription History</h1>
          <p className="text-xs text-slate-500">Immutable ledger of historical license grants and billing periods</p>
        </div>

        <button
          onClick={handleExport}
          className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <Download className="w-3.5 h-3.5" />
          <span>Export History</span>
        </button>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[11px] tracking-wider">
                <th className="py-3 px-4">Transaction ID</th>
                <th className="py-3 px-4">User</th>
                <th className="py-3 px-4">Plan Name</th>
                <th className="py-3 px-4">Period</th>
                <th className="py-3 px-4">Amount</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Payment Ref</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {subscriptions.map(sub => (
                <tr key={sub.id} className="hover:bg-slate-50/60">
                  <td className="py-3 px-4 font-mono font-medium text-slate-500">#SUB-{sub.id}</td>
                  <td className="py-3 px-4 font-semibold text-slate-900">{sub.user_name}</td>
                  <td className="py-3 px-4 text-slate-700">{sub.plan_name}</td>
                  <td className="py-3 px-4 text-slate-500 whitespace-nowrap">
                    {sub.starts_at ? (sub.starts_at.includes(' ') ? sub.starts_at.split(' ')[0] : sub.starts_at.split('T')[0]) : '-'} → {sub.expires_at ? (sub.expires_at.includes(' ') ? sub.expires_at.split(' ')[0] : sub.expires_at.split('T')[0]) : '-'}
                  </td>
                  <td className="py-3 px-4 font-bold text-slate-900">${sub.price_paid.toFixed(2)}</td>
                  <td className="py-3 px-4">
                    <Badge variant={sub.status === 'ACTIVE' ? 'success' : 'neutral'}>
                      {sub.status}
                    </Badge>
                  </td>
                  <td className="py-3 px-4 font-mono text-[11px] text-slate-500">{sub.payment_reference}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
