import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { FileText, Search, Download } from 'lucide-react';
import { Badge } from '../../common/Badge';

export const SystemLogsView: React.FC = () => {
  const { systemLogs, exportToCSV } = useAdmin();
  const [filterModule, setFilterModule] = useState<string>('ALL');

  const filteredLogs = systemLogs.filter(
    l => filterModule === 'ALL' || l.module === filterModule
  );

  const handleExport = () => {
    const headers = ['ID', 'Timestamp', 'Action', 'Module', 'User', 'IP Address', 'Device', 'Result', 'Details'];
    const rows = filteredLogs.map(l => [
      l.id,
      l.timestamp,
      l.action,
      l.module,
      l.user_name,
      l.ip,
      l.device,
      l.result,
      l.details || ''
    ]);
    exportToCSV('sata_audit_logs', headers, rows);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">System & Audit Logs</h1>
          <p className="text-xs text-slate-500">
            Immutable chronological record of administrative actions, user changes, and security dispatches
          </p>
        </div>

        <button
          onClick={handleExport}
          className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <Download className="w-3.5 h-3.5" />
          <span>Export Audit Trail</span>
        </button>
      </div>

      <div className="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs flex items-center justify-between gap-3">
        <span className="text-xs font-medium text-slate-500">Filter by Module:</span>
        <select
          value={filterModule}
          onChange={e => setFilterModule(e.target.value)}
          className="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs text-slate-800"
        >
          <option value="ALL">All Modules ({systemLogs.length})</option>
          <option value="Users">Users</option>
          <option value="Subscriptions">Subscriptions</option>
          <option value="Activation">Activation</option>
          <option value="Security">Security</option>
          <option value="System">System</option>
        </select>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[11px] tracking-wider">
                <th className="py-3 px-4">Timestamp</th>
                <th className="py-3 px-4">Action</th>
                <th className="py-3 px-4">Module</th>
                <th className="py-3 px-4">Actor / Target</th>
                <th className="py-3 px-4">IP Address</th>
                <th className="py-3 px-4">Device</th>
                <th className="py-3 px-4">Result</th>
                <th className="py-3 px-4">Details</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredLogs.map(log => (
                <tr key={log.id} className="hover:bg-slate-50/60">
                  <td className="py-3 px-4 text-slate-500 whitespace-nowrap font-mono">{log.timestamp}</td>
                  <td className="py-3 px-4 font-bold text-slate-900">{log.action}</td>
                  <td className="py-3 px-4">
                    <span className="font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded text-[10px]">
                      {log.module}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-slate-800 font-medium">{log.user_name}</td>
                  <td className="py-3 px-4 font-mono text-[11px] text-slate-500">{log.ip}</td>
                  <td className="py-3 px-4 text-slate-600">{log.device}</td>
                  <td className="py-3 px-4">
                    <Badge
                      variant={
                        log.result === 'SUCCESS'
                          ? 'success'
                          : log.result === 'WARNING'
                          ? 'warning'
                          : 'danger'
                      }
                    >
                      {log.result}
                    </Badge>
                  </td>
                  <td className="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">{log.details || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
