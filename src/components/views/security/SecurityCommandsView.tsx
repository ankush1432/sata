import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { Terminal, Download, ShieldCheck, Clock, CheckCircle2, RotateCcw, Lock, Unlock, Trash2 } from 'lucide-react';
import { Badge } from '../../common/Badge';

export const SecurityCommandsView: React.FC = () => {
  const { securityCommands, exportToCSV, setCurrentRoute } = useAdmin();

  const handleExport = () => {
    const headers = ['Command UUID', 'Target User', 'Device Name', 'Command Type', 'Status', 'Reason', 'Dispatched At', 'Executed At'];
    const rows = securityCommands.map(c => [
      c.command_uuid,
      c.user_name,
      c.device_name,
      c.command_type,
      c.status,
      c.reason,
      c.created_at,
      c.executed_at || 'Pending'
    ]);
    exportToCSV('sata_security_commands_log', headers, rows);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Security Commands Audit Log</h1>
          <p className="text-xs text-slate-500">
            System-wide log of remote execution actions (Factory Reset, Kiosk Lock/Unlock, Wipe Data)
          </p>
        </div>

        <button
          onClick={handleExport}
          className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <Download className="w-3.5 h-3.5" />
          <span>Export Command Logs</span>
        </button>
      </div>

      <div className="bg-amber-50/70 border border-amber-200 rounded-xl p-4 text-xs text-amber-900">
        <span className="font-bold">Operational Note:</span> To dispatch new security commands to a specific hardware device, navigate to the target user's profile and use the <span className="font-semibold underline">Security Commands</span> action panel.
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[11px] tracking-wider">
                <th className="py-3 px-4">Command UUID</th>
                <th className="py-3 px-4">Target User</th>
                <th className="py-3 px-4">Hardware Device</th>
                <th className="py-3 px-4">Command Type</th>
                <th className="py-3 px-4">Execution Status</th>
                <th className="py-3 px-4">Authorized Reason</th>
                <th className="py-3 px-4">Dispatched At</th>
                <th className="py-3 px-4">Executed At</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {securityCommands.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-400">
                    No remote commands logged
                  </td>
                </tr>
              ) : (
                securityCommands.map(cmd => (
                  <tr key={cmd.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3 px-4 font-mono font-medium text-slate-500">{cmd.command_uuid}</td>
                    <td className="py-3 px-4">
                      <button
                        onClick={() => setCurrentRoute(`/admin/users/${cmd.user_id}`)}
                        className="font-bold text-slate-900 hover:text-emerald-600 text-left"
                      >
                        {cmd.user_name}
                      </button>
                    </td>
                    <td className="py-3 px-4 text-slate-700">
                      <div>{cmd.device_name}</div>
                      <span className="uppercase text-[9px] font-bold text-slate-400">{cmd.platform}</span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded">
                        {cmd.command_type}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <Badge
                        variant={
                          cmd.status === 'COMPLETED'
                            ? 'success'
                            : cmd.status === 'SENT'
                            ? 'info'
                            : 'warning'
                        }
                      >
                        {cmd.status}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 text-slate-600 max-w-xs truncate">{cmd.reason}</td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">{cmd.created_at}</td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">{cmd.executed_at || 'In flight...'}</td>
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
