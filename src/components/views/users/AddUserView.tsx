import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { ArrowLeft, UserPlus, Shield, Phone, Mail, User, Layers, CheckCircle2, ShieldAlert } from 'lucide-react';

export const AddUserView: React.FC = () => {
  const { plans, addUser, setCurrentRoute, addToast } = useAdmin();

  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    email: '',
    planId: plans[0]?.id || 1,
    notes: ''
  });

  const [includeTrustedContact, setIncludeTrustedContact] = useState(false);
  const [trustedContact, setTrustedContact] = useState({
    name: '',
    phone: '',
    email: '',
    relationship: 'Emergency Contact'
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const validate = () => {
    const newErrors: Record<string, string> = {};
    if (!formData.name.trim()) newErrors.name = 'Full name is required';
    if (!formData.phone.trim()) {
      newErrors.phone = 'Mobile phone number is required';
    } else if (!/^\+?[1-9]\d{7,14}$/.test(formData.phone.replace(/[\s-]/g, ''))) {
      newErrors.phone = 'Enter a valid international format (e.g. +14155552671)';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'Email address is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Enter a valid email address';
    }

    if (includeTrustedContact) {
      if (!trustedContact.name.trim()) {
        newErrors.tc_name = 'Trusted user name is mandatory';
      }
      if (!trustedContact.phone.trim()) {
        newErrors.tc_phone = 'Trusted user mobile number is mandatory';
      } else if (trustedContact.phone.trim().length < 7) {
        newErrors.tc_phone = 'Enter a valid mobile phone number';
      }
      if (!trustedContact.email.trim()) {
        newErrors.tc_email = 'Trusted user email is mandatory (receives intruder photo and location links)';
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trustedContact.email)) {
        newErrors.tc_email = 'Enter a valid email address';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) {
      addToast('Validation Error', 'Please complete all mandatory fields marked with *', 'error');
      return;
    }

    setIsSubmitting(true);
    try {
      const created = addUser({
        name: formData.name.trim(),
        phone: formData.phone.trim(),
        email: formData.email.trim(),
        planId: Number(formData.planId),
        notes: formData.notes.trim(),
        trustedContacts: includeTrustedContact
          ? [
              {
                name: trustedContact.name.trim(),
                phone: trustedContact.phone.trim(),
                email: trustedContact.email.trim(),
                relationship: trustedContact.relationship
              }
            ]
          : undefined
      });

      setCurrentRoute(`/admin/users/${created.id}`);
    } catch (err) {
      addToast('Error', 'Failed to create user record', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Back button */}
      <div>
        <button
          onClick={() => setCurrentRoute('/admin/users')}
          className="flex items-center gap-1.5 text-xs font-medium text-slate-600 hover:text-slate-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to All Users</span>
        </button>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="p-6 border-b border-slate-100 bg-slate-50/50">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-emerald-100 text-emerald-700">
              <UserPlus className="w-5 h-5" />
            </div>
            <div>
              <h1 className="text-base font-bold text-slate-900">Provision New User Account</h1>
              <p className="text-xs text-slate-500">
                Direct administrative account creation with pre-activated protection entitlements
              </p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-5">
          {/* Full Name */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Full Legal Name <span className="text-rose-500">*</span>
            </label>
            <div className="relative">
              <User className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={formData.name}
                onChange={e => setFormData({ ...formData, name: e.target.value })}
                placeholder="e.g. Jane Doe"
                className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                  errors.name ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                }`}
              />
            </div>
            {errors.name && <p className="text-[11px] text-rose-500 mt-1">{errors.name}</p>}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Phone Number */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Mobile Number (E.164) <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <Phone className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={formData.phone}
                  onChange={e => setFormData({ ...formData, phone: e.target.value })}
                  placeholder="+12025550143"
                  className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                    errors.phone ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                  }`}
                />
              </div>
              {errors.phone && <p className="text-[11px] text-rose-500 mt-1">{errors.phone}</p>}
              <p className="text-[10px] text-slate-400 mt-0.5">Used for SMS security alerts and OTP authentication</p>
            </div>

            {/* Email Address */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Email Address <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <Mail className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="email"
                  value={formData.email}
                  onChange={e => setFormData({ ...formData, email: e.target.value })}
                  placeholder="jane.doe@example.com"
                  className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                    errors.email ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                  }`}
                />
              </div>
              {errors.email && <p className="text-[11px] text-rose-500 mt-1">{errors.email}</p>}
            </div>
          </div>

          {/* Initial Plan Assignment */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Initial Protection Subscription Plan
            </label>
            <div className="relative">
              <Layers className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <select
                value={formData.planId}
                onChange={e => setFormData({ ...formData, planId: Number(e.target.value) })}
                className="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              >
                {plans.map(p => (
                  <option key={p.id} value={p.id}>
                    {p.name} ({p.duration_days} Days - ${p.price})
                  </option>
                ))}
              </select>
            </div>
            <p className="text-[10px] text-slate-400 mt-0.5">
              Account status will immediately transition to ACTIVE upon creation.
            </p>
          </div>

          {/* Trusted Emergency Contact Section (Optional checkbox, but all 3 fields mandatory if enabled) */}
          <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/70 space-y-4">
            <div className="flex items-center justify-between">
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={includeTrustedContact}
                  onChange={e => setIncludeTrustedContact(e.target.checked)}
                  className="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500"
                />
                <span className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                  <Phone className="w-3.5 h-3.5 text-emerald-600" />
                  <span>Configure Primary Trusted Emergency Contact</span>
                </span>
              </label>
              <span className="text-[10px] text-slate-500 font-medium bg-slate-200/70 px-2 py-0.5 rounded">
                Slot 1 of 2
              </span>
            </div>

            {includeTrustedContact && (
              <div className="space-y-3 pt-2 border-t border-slate-200/80">
                <div className="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-900 flex items-start gap-2">
                  <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                  <div>
                    <strong>Mandatory Requirement & Security Protocol:</strong> Full Name, Mobile Number, and Email Address are mandatory. Intruder front camera photos and live GPS tracking links will be shared <span className="underline font-semibold">strictly to this email</span>. Device owner only receives an OTP.
                  </div>
                </div>

                {/* Trusted Contact Name */}
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Trusted Contact Full Name <span className="text-rose-500 font-bold">*</span>
                  </label>
                  <div className="relative">
                    <User className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      type="text"
                      value={trustedContact.name}
                      onChange={e => setTrustedContact({ ...trustedContact, name: e.target.value })}
                      placeholder="e.g. Elena Rivera"
                      className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                        errors.tc_name ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                      }`}
                    />
                  </div>
                  {errors.tc_name && <p className="text-[11px] text-rose-500 mt-1">{errors.tc_name}</p>}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {/* Trusted Contact Mobile */}
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                      Trusted Contact Mobile Number <span className="text-rose-500 font-bold">*</span>
                    </label>
                    <div className="relative">
                      <Phone className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                      <input
                        type="text"
                        value={trustedContact.phone}
                        onChange={e => setTrustedContact({ ...trustedContact, phone: e.target.value })}
                        placeholder="+14155559812"
                        className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                          errors.tc_phone ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                        }`}
                      />
                    </div>
                    {errors.tc_phone && <p className="text-[11px] text-rose-500 mt-1">{errors.tc_phone}</p>}
                  </div>

                  {/* Trusted Contact Email */}
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                      Trusted Contact Email Address <span className="text-rose-500 font-bold">*</span>
                    </label>
                    <div className="relative">
                      <Mail className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                      <input
                        type="email"
                        value={trustedContact.email}
                        onChange={e => setTrustedContact({ ...trustedContact, email: e.target.value })}
                        placeholder="elena.rivera@example.com"
                        className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                          errors.tc_email ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                        }`}
                      />
                    </div>
                    {errors.tc_email && <p className="text-[11px] text-rose-500 mt-1">{errors.tc_email}</p>}
                  </div>
                </div>

                {/* Relationship */}
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Relationship
                  </label>
                  <select
                    value={trustedContact.relationship}
                    onChange={e => setTrustedContact({ ...trustedContact, relationship: e.target.value })}
                    className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                  >
                    <option value="Spouse">Spouse</option>
                    <option value="Parent">Parent</option>
                    <option value="Sibling">Sibling</option>
                    <option value="Son">Son</option>
                    <option value="Daughter">Daughter</option>
                    <option value="Partner">Partner</option>
                    <option value="Close Friend">Close Friend</option>
                    <option value="Colleague">Colleague</option>
                    <option value="Emergency Contact">Emergency Contact</option>
                  </select>
                </div>
              </div>
            )}
          </div>

          {/* Administrative Notes */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Administrative Reference Notes (Optional)
            </label>
            <textarea
              rows={3}
              value={formData.notes}
              onChange={e => setFormData({ ...formData, notes: e.target.value })}
              placeholder="e.g. Corporate fleet enrollment, executive VIP protection..."
              className="w-full p-3 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
          </div>

          {/* Form Actions */}
          <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setCurrentRoute('/admin/users')}
              className="px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors shadow-2xs disabled:opacity-50"
            >
              {isSubmitting ? 'Creating User...' : 'Create Account'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
