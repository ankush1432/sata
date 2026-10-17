import React, { useState, useEffect } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  ArrowLeft,
  Edit2,
  Phone,
  Mail,
  User as UserIcon,
  Shield,
  ShieldAlert,
  Plus,
  Trash2,
  X,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';
import { UserStatus, TrustedContact } from '../../../types/admin';

interface EditUserViewProps {
  userId: number;
}

export const EditUserView: React.FC<EditUserViewProps> = ({ userId }) => {
  const {
    users,
    updateUser,
    addTrustedContact,
    updateTrustedContact,
    deleteTrustedContact,
    setCurrentRoute,
    addToast
  } = useAdmin();

  const user = users.find(u => u.id === userId);

  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    email: '',
    status: 'ACTIVE' as UserStatus,
    notes: ''
  });

  // Trusted Contact Modal state
  const [isContactModalOpen, setIsContactModalOpen] = useState(false);
  const [editingContact, setEditingContact] = useState<TrustedContact | null>(null);
  const [contactToDelete, setContactToDelete] = useState<TrustedContact | null>(null);
  const [isSavingContact, setIsSavingContact] = useState(false);
  const [isDeletingContact, setIsDeletingContact] = useState(false);

  const [contactFormData, setContactFormData] = useState({
    name: '',
    mobile_number: '',
    email: '',
    relationship: 'Family Member',
    is_verified: false,
  });

  const [contactErrors, setContactErrors] = useState<{
    name?: string;
    mobile_number?: string;
    email?: string;
  }>({});

  useEffect(() => {
    if (user) {
      setFormData({
        name: user.name,
        phone: user.phone,
        email: user.email,
        status: user.status,
        notes: user.notes || ''
      });
    }
  }, [user]);

  if (!user) {
    return (
      <div className="bg-white rounded-xl p-8 text-center border border-slate-200">
        <p className="text-xs text-slate-500">User not found</p>
        <button
          onClick={() => setCurrentRoute('/admin/users')}
          className="mt-3 px-3 py-1.5 text-xs text-emerald-600 font-semibold"
        >
          Return to Users
        </button>
      </div>
    );
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.phone.trim() || !formData.email.trim()) {
      addToast('Validation Error', 'All fields marked with * are required', 'error');
      return;
    }

    updateUser(user.id, {
      name: formData.name.trim(),
      phone: formData.phone.trim(),
      email: formData.email.trim(),
      status: formData.status,
      notes: formData.notes.trim()
    });

    setCurrentRoute(`/admin/users/${user.id}`);
  };

  const openAddContactModal = () => {
    setEditingContact(null);
    setContactFormData({
      name: '',
      mobile_number: '',
      email: '',
      relationship: 'Family Member',
      is_verified: true,
    });
    setContactErrors({});
    setIsContactModalOpen(true);
  };

  const openEditContactModal = (contact: TrustedContact) => {
    setEditingContact(contact);
    setContactFormData({
      name: contact.name,
      mobile_number: contact.mobile_number,
      email: contact.email || '',
      relationship: contact.relationship,
      is_verified: !!contact.is_verified,
    });
    setContactErrors({});
    setIsContactModalOpen(true);
  };

  const validateContactForm = () => {
    const errs: { name?: string; mobile_number?: string; email?: string } = {};
    if (!contactFormData.name.trim()) {
      errs.name = 'Full legal name is mandatory';
    }
    if (!contactFormData.mobile_number.trim()) {
      errs.mobile_number = 'Mobile number is mandatory';
    } else if (contactFormData.mobile_number.trim().length < 7) {
      errs.mobile_number = 'Please enter a valid mobile number';
    }
    if (!contactFormData.email.trim()) {
      errs.email = 'Email address is mandatory (required to receive intruder photo and location links)';
    } else if (!contactFormData.email.includes('@') || !contactFormData.email.includes('.')) {
      errs.email = 'Please enter a valid email address';
    }
    setContactErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSaveContact = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateContactForm()) return;

    setIsSavingContact(true);
    try {
      if (editingContact) {
        await updateTrustedContact(user.id, editingContact.id, {
          name: contactFormData.name.trim(),
          mobile_number: contactFormData.mobile_number.trim(),
          email: contactFormData.email.trim(),
          relationship: contactFormData.relationship,
          is_verified: contactFormData.is_verified,
        });
      } else {
        await addTrustedContact(user.id, {
          name: contactFormData.name.trim(),
          mobile_number: contactFormData.mobile_number.trim(),
          email: contactFormData.email.trim(),
          relationship: contactFormData.relationship,
          is_verified: contactFormData.is_verified,
        });
      }
      setIsContactModalOpen(false);
    } finally {
      setIsSavingContact(false);
    }
  };

  const handleConfirmDeleteContact = async () => {
    if (!contactToDelete) return;
    setIsDeletingContact(true);
    try {
      await deleteTrustedContact(user.id, contactToDelete.id);
      setContactToDelete(null);
    } finally {
      setIsDeletingContact(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <button
          onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
          className="flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors bg-white px-3 py-1.5 rounded-lg border border-slate-200"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to User Profile #{user.id}</span>
        </button>
        <span className="text-xs text-slate-400 font-mono">
          Managing: <strong className="text-slate-800">{user.name}</strong>
        </span>
      </div>

      {/* Main Profile Edit Form */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-emerald-100 text-emerald-700">
              <Edit2 className="w-5 h-5" />
            </div>
            <div>
              <h1 className="text-base font-bold text-slate-900">Edit User Profile</h1>
              <p className="text-xs text-slate-500">Modify contact details and operational account status</p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Full Name <span className="text-rose-500">*</span>
            </label>
            <input
              type="text"
              value={formData.name}
              onChange={e => setFormData({ ...formData, name: e.target.value })}
              className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Phone Number <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={formData.phone}
                onChange={e => setFormData({ ...formData, phone: e.target.value })}
                className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Email Address <span className="text-rose-500">*</span>
              </label>
              <input
                type="email"
                value={formData.email}
                onChange={e => setFormData({ ...formData, email: e.target.value })}
                className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Account Status
            </label>
            <select
              value={formData.status}
              onChange={e => setFormData({ ...formData, status: e.target.value as UserStatus })}
              className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            >
              <option value="ACTIVE">ACTIVE (Protection Enabled)</option>
              <option value="AWAITING_ACTIVATION">AWAITING_ACTIVATION (Pending Code)</option>
              <option value="PENDING_OTP">PENDING_OTP (Unverified)</option>
              <option value="DISABLED">DISABLED (Suspended)</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Notes
            </label>
            <textarea
              rows={3}
              value={formData.notes}
              onChange={e => setFormData({ ...formData, notes: e.target.value })}
              className="w-full p-3 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
          </div>

          <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
              className="px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors shadow-2xs"
            >
              Save Profile Changes
            </button>
          </div>
        </form>
      </div>

      {/* Trusted Users (Emergency Circle) Management Section */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-indigo-100 text-indigo-700">
              <Phone className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-bold text-slate-900">Trusted Emergency Contacts (Slots 1 & 2)</h2>
              <p className="text-xs text-slate-500">
                Mandatory: Full Name, Mobile, Email • Receives intruder photo link & live GPS tracking
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-[11px] font-mono font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">
              {user.trusted_contacts?.length || 0} / 2 Slots
            </span>
            <button
              type="button"
              onClick={openAddContactModal}
              disabled={(user.trusted_contacts?.length || 0) >= 2}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-2xs"
              title={(user.trusted_contacts?.length || 0) >= 2 ? 'Maximum 2 trusted users allowed' : 'Add new trusted user'}
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Add Trusted User</span>
            </button>
          </div>
        </div>

        <div className="p-6 space-y-4">
          {/* Security Banner */}
          <div className="p-3 bg-amber-50/70 border border-amber-200 rounded-lg flex items-start gap-2.5 text-xs text-amber-900">
            <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
            <div>
              <strong>Security Protocol Notice:</strong> Intruder front camera photos and live GPS tracking links are emailed <span className="underline font-semibold">strictly to trusted emergency contacts</span>. Device owner receives OTP only to protect recovery.
            </div>
          </div>

          {/* List of Trusted Contacts */}
          <div className="space-y-3">
            {user.trusted_contacts && user.trusted_contacts.length > 0 ? (
              user.trusted_contacts.map(contact => (
                <div
                  key={contact.id}
                  className="p-4 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition-colors"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded font-mono">
                        Slot {contact.slot || 1}
                      </span>
                      <span className="font-bold text-slate-900 text-xs">{contact.name}</span>
                      <span className="text-[10px] text-slate-500 font-semibold bg-slate-200/70 px-1.5 py-0.5 rounded">
                        {contact.relationship}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span
                        className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                          contact.is_verified
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800'
                        }`}
                      >
                        {contact.is_verified ? 'Verified' : 'Pending OTP'}
                      </span>
                      <button
                        type="button"
                        onClick={() => openEditContactModal(contact)}
                        className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition-colors shadow-2xs"
                        title="Edit Trusted Contact"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                        <span>Edit</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setContactToDelete(contact)}
                        className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-colors shadow-2xs"
                        title="Delete Trusted Contact"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                        <span>Delete</span>
                      </button>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-200/70 text-xs">
                    <div className="flex items-center gap-1.5 text-slate-800 font-mono">
                      <Phone className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                      <span>{contact.mobile_number}</span>
                      <span className="text-[10px] text-emerald-700 font-sans font-medium">(Mandatory Mobile)</span>
                    </div>
                    <div className="flex items-center gap-1.5 text-slate-700 truncate">
                      <Mail className="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                      <span className="truncate font-mono">{contact.email}</span>
                      <span className="text-[10px] text-indigo-700 font-sans font-medium shrink-0 bg-indigo-50 border border-indigo-200 px-1 rounded">
                        Photo & GPS Recipient
                      </span>
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <div className="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs space-y-2">
                <p className="text-slate-500 font-medium">No trusted emergency contacts configured yet.</p>
                <p className="text-slate-400 text-[11px]">
                  Register a trusted contact with mandatory Name, Mobile, and Email so intruder photos and location links are delivered.
                </p>
                <button
                  type="button"
                  onClick={openAddContactModal}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-2xs mt-1"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Add First Trusted Contact</span>
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ADD / EDIT TRUSTED EMERGENCY USER MODAL */}
      {isContactModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-lg bg-emerald-100 text-emerald-700">
                  <Phone className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">
                    {editingContact ? 'Edit Trusted Emergency Contact' : 'Add Trusted Emergency Contact'}
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Full Legal Name, Mobile Number, and Email are mandatory
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsContactModalOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleSaveContact} className="p-5 space-y-4">
              <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-900 flex items-start gap-2">
                <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <div>
                  <strong>Emergency Photo & Location Recipient:</strong> Front camera intruder photos and Google Maps links are sent exclusively to this email. The device owner will only receive an OTP.
                </div>
              </div>

              {/* Full Legal Name */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Full Legal Name <span className="text-rose-500 font-bold">*</span>
                </label>
                <div className="relative">
                  <UserIcon className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    value={contactFormData.name}
                    onChange={e => setContactFormData({ ...contactFormData, name: e.target.value })}
                    placeholder="e.g. John Doe"
                    className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                      contactErrors.name ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                    }`}
                  />
                </div>
                {contactErrors.name && (
                  <p className="text-[11px] text-rose-500 mt-1">{contactErrors.name}</p>
                )}
              </div>

              {/* Mobile Phone Number */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Mobile Number (E.164) <span className="text-rose-500 font-bold">*</span>
                </label>
                <div className="relative">
                  <Phone className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    value={contactFormData.mobile_number}
                    onChange={e => setContactFormData({ ...contactFormData, mobile_number: e.target.value })}
                    placeholder="+14155552671"
                    className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                      contactErrors.mobile_number ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                    }`}
                  />
                </div>
                {contactErrors.mobile_number ? (
                  <p className="text-[11px] text-rose-500 mt-1">{contactErrors.mobile_number}</p>
                ) : (
                  <p className="text-[10px] text-slate-400 mt-0.5">Required for SMS alerts and emergency contact verification</p>
                )}
              </div>

              {/* Email Address */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Email Address <span className="text-rose-500 font-bold">*</span>
                </label>
                <div className="relative">
                  <Mail className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="email"
                    value={contactFormData.email}
                    onChange={e => setContactFormData({ ...contactFormData, email: e.target.value })}
                    placeholder="trusted.contact@example.com"
                    className={`w-full pl-9 pr-3 py-2 text-xs bg-white border rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 ${
                      contactErrors.email ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-emerald-500'
                    }`}
                  />
                </div>
                {contactErrors.email ? (
                  <p className="text-[11px] text-rose-500 mt-1">{contactErrors.email}</p>
                ) : (
                  <p className="text-[10px] text-indigo-600 mt-0.5 font-medium">
                    Mandatory: High-resolution intruder photos and Google Maps links will be emailed here
                  </p>
                )}
              </div>

              {/* Relationship */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Relationship to User
                </label>
                <select
                  value={contactFormData.relationship}
                  onChange={e => setContactFormData({ ...contactFormData, relationship: e.target.value })}
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

              {/* Verification Status */}
              <div className="pt-2">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={contactFormData.is_verified}
                    onChange={e => setContactFormData({ ...contactFormData, is_verified: e.target.checked })}
                    className="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500"
                  />
                  <span className="text-xs text-slate-700 font-medium">
                    Mark as verified contact (OTP verification complete)
                  </span>
                </label>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setIsContactModalOpen(false)}
                  className="px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSavingContact}
                  className="px-5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors shadow-2xs"
                >
                  {isSavingContact ? 'Saving...' : editingContact ? 'Update Contact' : 'Add Contact'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DELETE CONFIRMATION MODAL */}
      {contactToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl max-w-sm w-full border border-slate-200 shadow-xl p-5 space-y-4">
            <div className="flex items-center gap-3 text-rose-600">
              <div className="p-2 rounded-lg bg-rose-100">
                <AlertCircle className="w-5 h-5" />
              </div>
              <h3 className="text-sm font-bold text-slate-900">Remove Trusted Contact?</h3>
            </div>
            <p className="text-xs text-slate-600">
              Are you sure you want to remove <strong className="text-slate-900">{contactToDelete.name}</strong> from trusted emergency users? They will no longer receive emergency intruder photos or tracking links.
            </p>
            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setContactToDelete(null)}
                className="px-3.5 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmDeleteContact}
                disabled={isDeletingContact}
                className="px-4 py-1.5 text-xs font-semibold text-white bg-rose-600 rounded-lg hover:bg-rose-700 disabled:opacity-50"
              >
                {isDeletingContact ? 'Removing...' : 'Confirm Remove'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
