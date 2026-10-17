import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  KeyRound,
  FilePlus,
  Search,
  Filter,
  Copy,
  Check,
  XCircle,
  Download,
  Trash2,
  Calendar,
  Layers,
  Sparkles
} from 'lucide-react';
import { Badge } from '../../common/Badge';
import { ConfirmationModal } from '../../common/ConfirmationModal';
import { Modal } from '../../common/Modal';
import { ActivationCode } from '../../../types/admin';

export const ActivationCodesView: React.FC = () => {
  const {
    activationCodes,
    plans,
    generateActivationCodes,
    revokeActivationCode,
    deleteActivationCode,
    searchTerm,
    setSearchTerm,
    exportToCSV,
    addToast
  } = useAdmin();

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('ALL');
  const [copiedCodeId, setCopiedCodeId] = useState<number | null>(null);

  // Modals
  const [isGenerateModalOpen, setIsGenerateModalOpen] = useState(false);
  const [revokingCode, setRevokingCode] = useState<ActivationCode | null>(null);
  const [deletingCode, setDeletingCode] = useState<ActivationCode | null>(null);
  const [lastGenerated, setLastGenerated] = useState<ActivationCode[]>([]);

  // Generator form
  const [genPlanId, setGenPlanId] = useState<number>(plans[0]?.id || 1);
  const [genCount, setGenCount] = useState<number>(5);
  const [genExpiryDays, setGenExpiryDays] = useState<number>(365);
  const [genNotes, setGenNotes] = useState<string>('Standard Enterprise Batch');

  const filteredCodes = useMemo(() => {
    return activationCodes.filter(c => {
      const matchSearch =
        searchTerm === '' ||
        c.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (c.user_name && c.user_name.toLowerCase().includes(searchTerm.toLowerCase())) ||
        c.plan_name.toLowerCase().includes(searchTerm.toLowerCase());

      const matchStatus = statusFilter === 'ALL' || c.status === statusFilter;
      return matchSearch && matchStatus;
    });
  }, [activationCodes, searchTerm, statusFilter]);

  const handleCopy = (code: ActivationCode) => {
    navigator.clipboard.writeText(code.code);
    setCopiedCodeId(code.id);
    addToast('Code Copied', `Copied ${code.code} to clipboard`, 'info');
    setTimeout(() => setCopiedCodeId(null), 2000);
  };

  const handleGenerate = (e: React.FormEvent) => {
    e.preventDefault();
    const codes = generateActivationCodes(Number(genPlanId), Number(genCount), Number(genExpiryDays), genNotes);
    setLastGenerated(codes);
    setIsGenerateModalOpen(false);
  };

  const handleExport = () => {
    const headers = ['Code', 'User', 'Plan', 'Status', 'Generated At', 'Activated At', 'Expires At', 'Batch / Notes'];
    const rows = filteredCodes.map(c => [
      c.code,
      c.user_name || 'Unassigned',
      c.plan_name,
      c.status,
      c.generated_at,
      c.activated_at || 'N/A',
      c.expires_at,
      c.notes || ''
    ]);
    exportToCSV('sata_activation_codes', headers, rows);
  };

  return (
    <div className="space-y-5">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Activation Codes</h1>
          <p className="text-xs text-slate-500">
            Cryptographically secure license activation vouchers for account onboarding
          </p>
        </div>

        <div className="flex items-center gap-2 self-start">
          <button
            onClick={handleExport}
            className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export Codes</span>
          </button>
          <button
            onClick={() => setIsGenerateModalOpen(true)}
            className="px-3.5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-1.5"
          >
            <FilePlus className="w-3.5 h-3.5" />
            <span>+ Generate Codes</span>
          </button>
        </div>
      </div>

      {/* Recently Generated Notification Banner */}
      {lastGenerated.length > 0 && (
        <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <Sparkles className="w-5 h-5 text-emerald-600 shrink-0" />
            <div>
              <span className="text-xs font-bold text-emerald-900">
                Successfully Generated {lastGenerated.length} New Activation Code(s)!
              </span>
              <p className="text-[11px] text-emerald-700">
                Batch initialized for {lastGenerated[0]?.plan_name}. Ready for immediate customer distribution.
              </p>
            </div>
          </div>
          <button
            onClick={() => setLastGenerated([])}
            className="text-xs font-semibold text-emerald-800 hover:text-emerald-950 underline self-start sm:self-center"
          >
            Dismiss
          </button>
        </div>
      )}

      {/* Filter Toolbar */}
      <div className="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search by code, user name, or plan..."
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
            <option value="ALL">All Codes</option>
            <option value="UNUSED">Unused</option>
            <option value="USED">Used</option>
            <option value="EXPIRED">Expired</option>
            <option value="REVOKED">Revoked</option>
          </select>
        </div>
      </div>

      {/* Codes Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[11px] tracking-wider">
                <th className="py-3 px-4">Activation Code</th>
                <th className="py-3 px-4">Plan</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Assigned / Redeemed By</th>
                <th className="py-3 px-4">Generated At</th>
                <th className="py-3 px-4">Expires At</th>
                <th className="py-3 px-4">Notes</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredCodes.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-400">
                    No activation codes found matching filter criteria
                  </td>
                </tr>
              ) : (
                filteredCodes.map(code => (
                  <tr key={code.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3 px-4">
                      <div className="flex items-center gap-2">
                        <span className="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded text-xs select-all">
                          {code.code}
                        </span>
                        <button
                          onClick={() => handleCopy(code)}
                          title="Copy Code"
                          className="p-1 text-slate-400 hover:text-slate-700 rounded transition-colors"
                        >
                          {copiedCodeId === code.id ? (
                            <Check className="w-3.5 h-3.5 text-emerald-600" />
                          ) : (
                            <Copy className="w-3.5 h-3.5" />
                          )}
                        </button>
                      </div>
                    </td>
                    <td className="py-3 px-4 font-medium text-slate-800">{code.plan_name}</td>
                    <td className="py-3 px-4">
                      <Badge
                        variant={
                          code.status === 'UNUSED'
                            ? 'success'
                            : code.status === 'USED'
                            ? 'neutral'
                            : 'danger'
                        }
                      >
                        {code.status}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 text-slate-700">
                      {code.user_name ? (
                        <span className="font-semibold text-slate-900">{code.user_name}</span>
                      ) : (
                        <span className="text-slate-400 italic">Not yet redeemed</span>
                      )}
                    </td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">
                      {code.generated_at ? String(code.generated_at).split(/[ T]/)[0] : '-'}
                    </td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">
                      {code.expires_at ? String(code.expires_at).split(/[ T]/)[0] : '-'}
                    </td>
                    <td className="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">{code.notes || '-'}</td>
                    <td className="py-3 px-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        {code.status === 'UNUSED' && (
                          <button
                            onClick={() => setRevokingCode(code)}
                            title="Revoke Code"
                            className="p-1.5 text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                          >
                            <XCircle className="w-3.5 h-3.5" />
                          </button>
                        )}
                        <button
                          onClick={() => setDeletingCode(code)}
                          title="Delete Code Record"
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

      {/* Generate Code Modal */}
      <Modal
        isOpen={isGenerateModalOpen}
        onClose={() => setIsGenerateModalOpen(false)}
        title="Generate Secure Activation Codes"
        subtitle="Batch generation with cryptographic collision protection"
        maxWidth="md"
      >
        <form onSubmit={handleGenerate} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Select Subscription Plan <span className="text-rose-500">*</span>
            </label>
            <select
              value={genPlanId}
              onChange={e => setGenPlanId(Number(e.target.value))}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              {plans.map(p => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.duration_days} Days - ${p.price})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Quantity of Codes <span className="text-rose-500">*</span>
              </label>
              <input
                type="number"
                min={1}
                max={50}
                required
                value={genCount}
                onChange={e => setGenCount(Number(e.target.value))}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Validity (Days) <span className="text-rose-500">*</span>
              </label>
              <input
                type="number"
                min={1}
                max={730}
                required
                value={genExpiryDays}
                onChange={e => setGenExpiryDays(Number(e.target.value))}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Optional Batch Notes / Memo
            </label>
            <textarea
              rows={2}
              value={genNotes}
              onChange={e => setGenNotes(e.target.value)}
              placeholder="e.g. Retail promotional vouchers, customer renewal compensation..."
              className="w-full p-2.5 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsGenerateModalOpen(false)}
              className="px-3.5 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs"
            >
              Generate Codes Now
            </button>
          </div>
        </form>
      </Modal>

      {/* Revoke Confirmation */}
      <ConfirmationModal
        isOpen={Boolean(revokingCode)}
        onClose={() => setRevokingCode(null)}
        onConfirm={() => {
          if (revokingCode) {
            revokeActivationCode(revokingCode.id);
            setRevokingCode(null);
          }
        }}
        title="Revoke Activation Code"
        description={`Are you sure you want to revoke code "${revokingCode?.code}"? Once revoked, it cannot be redeemed by any user.`}
        confirmLabel="Revoke Code"
        variant="warning"
      />

      {/* Delete Confirmation */}
      <ConfirmationModal
        isOpen={Boolean(deletingCode)}
        onClose={() => setDeletingCode(null)}
        onConfirm={() => {
          if (deletingCode) {
            deleteActivationCode(deletingCode.id);
            setDeletingCode(null);
          }
        }}
        title="Delete Activation Code"
        description="Permanently delete this code record from the system database?"
        confirmLabel="Delete Code"
        variant="danger"
      />
    </div>
  );
};
