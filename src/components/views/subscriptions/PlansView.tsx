import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  Layers,
  Plus,
  Edit2,
  Trash2,
  CheckCircle,
  XCircle,
  Clock,
  DollarSign,
  Tag,
  ShieldCheck
} from 'lucide-react';
import { Badge } from '../../common/Badge';
import { Modal } from '../../common/Modal';
import { ConfirmationModal } from '../../common/ConfirmationModal';
import { Plan } from '../../../types/admin';

export const PlansView: React.FC = () => {
  const { plans, addPlan, updatePlan, deletePlan, togglePlanStatus } = useAdmin();

  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [editingPlan, setEditingPlan] = useState<Plan | null>(null);
  const [deletingPlan, setDeletingPlan] = useState<Plan | null>(null);

  // Form State
  const [formData, setFormData] = useState({
    name: '',
    code: '',
    description: '',
    duration_days: 30,
    price: 9.99,
    currency: 'USD',
    features: 'Live GPS Tracking, Intruder Photo Capture, Remote Alarm'
  });

  const handleOpenAdd = () => {
    setFormData({
      name: '',
      code: '',
      description: '',
      duration_days: 30,
      price: 9.99,
      currency: 'USD',
      features: 'Live GPS Tracking, Intruder Photo Capture, Remote Alarm'
    });
    setIsAddModalOpen(true);
  };

  const handleOpenEdit = (plan: Plan) => {
    setEditingPlan(plan);
    setFormData({
      name: plan.name,
      code: plan.code,
      description: plan.description,
      duration_days: plan.duration_days,
      price: plan.price,
      currency: plan.currency,
      features: plan.features.join(', ')
    });
  };

  const handleSaveAdd = (e: React.FormEvent) => {
    e.preventDefault();
    addPlan({
      name: formData.name.trim(),
      code: formData.code.trim().toUpperCase() || `PLAN_${formData.duration_days}D`,
      description: formData.description.trim(),
      duration_days: Number(formData.duration_days),
      price: Number(formData.price),
      currency: formData.currency,
      status: 'ACTIVE',
      features: formData.features.split(',').map(f => f.trim()).filter(Boolean)
    });
    setIsAddModalOpen(false);
  };

  const handleSaveEdit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingPlan) return;
    updatePlan(editingPlan.id, {
      name: formData.name.trim(),
      code: formData.code.trim().toUpperCase(),
      description: formData.description.trim(),
      duration_days: Number(formData.duration_days),
      price: Number(formData.price),
      features: formData.features.split(',').map(f => f.trim()).filter(Boolean)
    });
    setEditingPlan(null);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Subscription Plans</h1>
          <p className="text-xs text-slate-500">Configure duration tiers, pricing, and anti-theft feature packages</p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="px-3.5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <Plus className="w-3.5 h-3.5" />
          <span>+ Add Plan</span>
        </button>
      </div>

      {/* Plans Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {plans.map(plan => (
          <div
            key={plan.id}
            className={`bg-white rounded-xl border p-5 shadow-2xs transition-all flex flex-col justify-between ${
              plan.status === 'ACTIVE' ? 'border-slate-200' : 'border-slate-200 bg-slate-50/60 opacity-80'
            }`}
          >
            <div>
              <div className="flex items-center justify-between mb-2">
                <span className="font-mono text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                  {plan.code}
                </span>
                <Badge variant={plan.status === 'ACTIVE' ? 'success' : 'neutral'}>
                  {plan.status}
                </Badge>
              </div>

              <h3 className="text-base font-bold text-slate-900">{plan.name}</h3>
              <p className="text-xs text-slate-500 mt-1 min-h-[32px]">{plan.description}</p>

              <div className="mt-4 pt-3 border-t border-slate-100 flex items-baseline gap-1">
                <span className="text-2xl font-black text-slate-900">${plan.price.toFixed(2)}</span>
                <span className="text-xs text-slate-500">/ {plan.duration_days} Days</span>
              </div>

              {/* Feature Tags */}
              <div className="mt-3 space-y-1">
                <span className="text-[10px] uppercase font-bold text-slate-400">Included Features:</span>
                <div className="flex flex-wrap gap-1 mt-1">
                  {plan.features.map((feature, i) => (
                    <span
                      key={i}
                      className="text-[10px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-medium flex items-center gap-1"
                    >
                      <ShieldCheck className="w-2.5 h-2.5 text-emerald-600" />
                      <span>{feature}</span>
                    </span>
                  ))}
                </div>
              </div>
            </div>

            {/* Actions Toolbar */}
            <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
              <span className="text-slate-500 font-medium text-[11px]">
                {plan.subscribers_count} subscribers
              </span>

              <div className="flex items-center gap-1">
                <button
                  onClick={() => togglePlanStatus(plan.id)}
                  title={plan.status === 'ACTIVE' ? 'Disable Plan' : 'Enable Plan'}
                  className={`p-1.5 rounded-md transition-colors ${
                    plan.status === 'ACTIVE'
                      ? 'text-amber-600 hover:bg-amber-50'
                      : 'text-emerald-600 hover:bg-emerald-50'
                  }`}
                >
                  {plan.status === 'ACTIVE' ? <XCircle className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
                </button>
                <button
                  onClick={() => handleOpenEdit(plan)}
                  title="Edit Plan"
                  className="p-1.5 text-slate-600 hover:text-emerald-600 hover:bg-slate-100 rounded-md transition-colors"
                >
                  <Edit2 className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setDeletingPlan(plan)}
                  title="Delete Plan"
                  className="p-1.5 text-rose-500 hover:bg-rose-50 rounded-md transition-colors"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Add / Edit Plan Modal */}
      <Modal
        isOpen={isAddModalOpen || Boolean(editingPlan)}
        onClose={() => {
          setIsAddModalOpen(false);
          setEditingPlan(null);
        }}
        title={editingPlan ? 'Edit Subscription Plan' : 'Create New Subscription Plan'}
        maxWidth="md"
      >
        <form onSubmit={editingPlan ? handleSaveEdit : handleSaveAdd} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Plan Name</label>
            <input
              type="text"
              required
              value={formData.name}
              onChange={e => setFormData({ ...formData, name: e.target.value })}
              placeholder="e.g. 60-Day Midterm Defense"
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Code Identifier</label>
              <input
                type="text"
                required
                value={formData.code}
                onChange={e => setFormData({ ...formData, code: e.target.value })}
                placeholder="PLAN_60D"
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 font-mono uppercase focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Duration (Days)</label>
              <input
                type="number"
                required
                min={1}
                value={formData.duration_days}
                onChange={e => setFormData({ ...formData, duration_days: Number(e.target.value) })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Price (USD)</label>
            <input
              type="number"
              step="0.01"
              required
              min={0}
              value={formData.price}
              onChange={e => setFormData({ ...formData, price: Number(e.target.value) })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Description</label>
            <textarea
              rows={2}
              value={formData.description}
              onChange={e => setFormData({ ...formData, description: e.target.value })}
              placeholder="Brief summary of anti-theft coverage..."
              className="w-full p-2.5 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Included Features (comma separated)
            </label>
            <input
              type="text"
              value={formData.features}
              onChange={e => setFormData({ ...formData, features: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={() => {
                setIsAddModalOpen(false);
                setEditingPlan(null);
              }}
              className="px-3.5 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs"
            >
              {editingPlan ? 'Save Changes' : 'Create Plan'}
            </button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmationModal
        isOpen={Boolean(deletingPlan)}
        onClose={() => setDeletingPlan(null)}
        onConfirm={() => {
          if (deletingPlan) {
            deletePlan(deletingPlan.id);
            setDeletingPlan(null);
          }
        }}
        title="Delete Plan"
        description={`Are you sure you want to delete "${deletingPlan?.name}"? Historical subscriptions linked to this plan will remain intact.`}
        confirmLabel="Delete Plan"
        variant="danger"
      />
    </div>
  );
};
