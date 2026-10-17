import React from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { History, Download, KeyRound } from 'lucide-react';
import { Badge } from '../../common/Badge';

export const ActivationHistoryView: React.FC = () => {
  const { activationCodes, exportToCSV } = useAdmin();

  const usedCodes = activationCodes.filter(c => c.status === 'USED');

  const handleExport = () => {
    const headers = ['Code', 'User', 'Plan', 'Redeemed At', 'Expires At', 'Batch / Notes'];
    const rows = usedCodes.map(c => [
      c.code,
      c.user_name || 'N/A',
      c.plan_name,
      c.activated_at || '',
      c.expires_at,
      c.notes || ''
    ]);
    exportToCSV('sata_activation_history', headers, rows);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Activation History</h1>
          <p className="text-xs text-slate-500">Audit trail of redeemed user vouchers and license assignments</p>
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
                <th className="py-3 px-4">Redeemed Code</th>
                <th className="py-3 px-4">Account Owner</th>
                <th className="py-3 px-4">Subscription Plan</th>
                <th className="py-3 px-4">Redeemed Date</th>
                <th className="py-3 px-4">Valid Until</th>
                <th className="py-3 px-4">Audit Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {usedCodes.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">
                    No activated codes on record
                  </td>
                </tr>
              ) : (
                usedCodes.map(code => (
                  <tr key={code.id} className="hover:bg-slate-50/60">
                    <td className="py-3 px-4 font-mono font-bold text-slate-900">{code.code}</td>
                    <td className="py-3 px-4 font-semibold text-slate-900">{code.user_name}</td>
                    <td className="py-3 px-4 text-slate-700">{code.plan_name}</td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">
                      {code.expires_at ? String(code.expires_at).split(/[ T]/)[0] : '-'}
                    </td>
                    <td className="py-3 px-4">
                      <Badge variant="success">ACTIVATED</Badge>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
