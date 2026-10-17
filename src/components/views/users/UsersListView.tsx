import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  Users,
  UserPlus,
  Search,
  Download,
  Eye,
  Edit2,
  Trash2,
  CheckCircle,
  XCircle,
  Smartphone,
  ChevronLeft,
  ChevronRight,
  ShieldCheck,
  ShieldAlert,
  Phone,
  Mail,
  UserCheck,
  BatteryCharging,
  Battery,
  AlertTriangle,
  LayoutGrid,
  List,
  Shield,
  Clock,
  ExternalLink
} from 'lucide-react';
import { Badge } from '../../common/Badge';
import { User } from '../../../types/admin';
import { ConfirmationModal } from '../../common/ConfirmationModal';

interface UsersListViewProps {
  initialFilter?: 'ALL' | 'ACTIVE' | 'DISABLED' | 'AWAITING_ACTIVATION';
}

export const UsersListView: React.FC<UsersListViewProps> = ({ initialFilter = 'ALL' }) => {
  const {
    users,
    deleteUser,
    activateUser,
    disableUser,
    setCurrentRoute,
    searchTerm,
    setSearchTerm,
    exportToCSV
  } = useAdmin();

  // Filters State
  const [statusFilter, setStatusFilter] = useState<string>(
    initialFilter === 'DISABLED'
      ? 'DISABLED'
      : initialFilter === 'AWAITING_ACTIVATION'
      ? 'AWAITING_ACTIVATION'
      : 'ALL'
  );
  const [subFilter, setSubFilter] = useState<string>('ALL');
  const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');
  const [page, setPage] = useState<number>(1);
  const pageSize = 10;

  // Modals state
  const [pendingDeleteUser, setPendingDeleteUser] = useState<User | null>(null);
  const [pendingDisableUser, setPendingDisableUser] = useState<User | null>(null);

  // Filtered dataset
  const filteredUsers = useMemo(() => {
    return users.filter(u => {
      const term = searchTerm.toLowerCase();
      const searchMatch =
        searchTerm === '' ||
        u.name.toLowerCase().includes(term) ||
        u.email.toLowerCase().includes(term) ||
        u.phone.includes(searchTerm) ||
        u.devices.some(d => d.device_name.toLowerCase().includes(term)) ||
        (u.trusted_contacts &&
          u.trusted_contacts.some(
            c =>
              c.name.toLowerCase().includes(term) ||
              c.mobile_number.includes(searchTerm) ||
              (c.email && c.email.toLowerCase().includes(term))
          ));

      const statusMatch = statusFilter === 'ALL' || u.status === statusFilter;
      const subMatch = subFilter === 'ALL' || u.subscription_status === subFilter;

      return searchMatch && statusMatch && subMatch;
    });
  }, [users, searchTerm, statusFilter, subFilter]);

  const totalPages = Math.ceil(filteredUsers.length / pageSize) || 1;
  const paginatedUsers = filteredUsers.slice((page - 1) * pageSize, page * pageSize);

  const activeCount = users.filter(u => u.status === 'ACTIVE').length;
  const awaitingCount = users.filter(u => u.status === 'AWAITING_ACTIVATION').length;
  const disabledCount = users.filter(u => u.status === 'DISABLED').length;

  const handleExportCSV = () => {
    const headers = [
      'ID',
      'Name',
      'Mobile Phone',
      'Email',
      'Trusted Contact 1 Name',
      'Trusted Contact 1 Phone',
      'Trusted Contact 1 Email',
      'Trusted Contact 2 Name',
      'Trusted Contact 2 Phone',
      'Trusted Contact 2 Email',
      'Device Name',
      'Platform',
      'Status',
      'Subscription',
      'Created At'
    ];
    const rows = filteredUsers.map(u => {
      const c1 = u.trusted_contacts?.[0];
      const c2 = u.trusted_contacts?.[1];
      return [
        u.id,
        u.name,
        u.phone,
        u.email,
        c1 ? c1.name : 'N/A',
        c1 ? c1.mobile_number : 'N/A',
        c1 ? c1.email || 'N/A' : 'N/A',
        c2 ? c2.name : 'N/A',
        c2 ? c2.mobile_number : 'N/A',
        c2 ? c2.email || 'N/A' : 'N/A',
        u.devices[0]?.device_name || 'No Device',
        u.devices[0]?.platform || 'N/A',
        u.status,
        u.subscription_status,
        u.created_at
      ];
    });
    exportToCSV('sata_all_users_export', headers, rows);
  };

  // Helper to get initials
  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .substring(0, 2)
      .toUpperCase();
  };

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">All Protected Users</h1>
            <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
              {users.length} Total Users
            </span>
          </div>
          <p className="text-xs text-slate-500 mt-0.5">
            Complete database of subscribers, profile photos, emergency contacts, hardware devices & security forensics
          </p>
        </div>

        <div className="flex items-center gap-2.5 flex-wrap">
          {/* View mode toggle */}
          <div className="flex items-center bg-slate-200/80 p-0.5 rounded-lg border border-slate-300/60">
            <button
              onClick={() => setViewMode('table')}
              className={`p-1.5 rounded-md text-xs font-medium transition-colors ${
                viewMode === 'table' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
              }`}
              title="Table View"
            >
              <List className="w-3.5 h-3.5" />
            </button>
            <button
              onClick={() => setViewMode('grid')}
              className={`p-1.5 rounded-md text-xs font-medium transition-colors ${
                viewMode === 'grid' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
              }`}
              title="Card Grid View"
            >
              <LayoutGrid className="w-3.5 h-3.5" />
            </button>
          </div>

          <button
            onClick={handleExportCSV}
            className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-2xs"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export CSV</span>
          </button>
          <button
            onClick={() => setCurrentRoute('/admin/users/create')}
            className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors shadow-2xs"
          >
            <UserPlus className="w-4 h-4" />
            <span>Add New User</span>
          </button>
        </div>
      </div>

      {/* Summary Stat Metric Chips */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div
          onClick={() => { setStatusFilter('ALL'); setPage(1); }}
          className={`p-3 rounded-xl border transition-all cursor-pointer ${
            statusFilter === 'ALL'
              ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
              : 'bg-white text-slate-800 border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className={`text-[10px] font-bold uppercase block ${statusFilter === 'ALL' ? 'text-slate-400' : 'text-slate-500'}`}>
            Total Registered
          </span>
          <span className="text-lg font-bold mt-0.5 block">{users.length}</span>
        </div>

        <div
          onClick={() => { setStatusFilter('ACTIVE'); setPage(1); }}
          className={`p-3 rounded-xl border transition-all cursor-pointer ${
            statusFilter === 'ACTIVE'
              ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
              : 'bg-white text-slate-800 border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className={`text-[10px] font-bold uppercase block ${statusFilter === 'ACTIVE' ? 'text-emerald-100' : 'text-slate-500'}`}>
            Active Shield
          </span>
          <span className="text-lg font-bold mt-0.5 block">{activeCount}</span>
        </div>

        <div
          onClick={() => { setStatusFilter('AWAITING_ACTIVATION'); setPage(1); }}
          className={`p-3 rounded-xl border transition-all cursor-pointer ${
            statusFilter === 'AWAITING_ACTIVATION'
              ? 'bg-amber-600 text-white border-amber-600 shadow-xs'
              : 'bg-white text-slate-800 border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className={`text-[10px] font-bold uppercase block ${statusFilter === 'AWAITING_ACTIVATION' ? 'text-amber-100' : 'text-slate-500'}`}>
            Awaiting Code
          </span>
          <span className="text-lg font-bold mt-0.5 block">{awaitingCount}</span>
        </div>

        <div
          onClick={() => { setStatusFilter('DISABLED'); setPage(1); }}
          className={`p-3 rounded-xl border transition-all cursor-pointer ${
            statusFilter === 'DISABLED'
              ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
              : 'bg-white text-slate-800 border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className={`text-[10px] font-bold uppercase block ${statusFilter === 'DISABLED' ? 'text-rose-100' : 'text-slate-500'}`}>
            Disabled Accounts
          </span>
          <span className="text-lg font-bold mt-0.5 block">{disabledCount}</span>
        </div>
      </div>

      {/* Search & Filter Bar */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex flex-col md:flex-row gap-3 items-center justify-between">
        <div className="relative w-full md:max-w-md">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search by user name, phone, email, or trusted user name/phone/email..."
            value={searchTerm}
            onChange={e => {
              setSearchTerm(e.target.value);
              setPage(1);
            }}
            className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
          />
        </div>

        <div className="flex items-center gap-3 w-full md:w-auto">
          {/* Status Filter */}
          <div className="flex items-center gap-1.5 text-xs w-full md:w-auto">
            <span className="text-slate-500 font-medium shrink-0">Account:</span>
            <select
              value={statusFilter}
              onChange={e => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="w-full md:w-auto bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:bg-white"
            >
              <option value="ALL">All Accounts</option>
              <option value="ACTIVE">Active</option>
              <option value="PENDING_OTP">Pending OTP</option>
              <option value="AWAITING_ACTIVATION">Awaiting Code</option>
              <option value="DISABLED">Disabled</option>
            </select>
          </div>

          {/* Subscription Filter */}
          <div className="flex items-center gap-1.5 text-xs w-full md:w-auto">
            <span className="text-slate-500 font-medium shrink-0">Plan:</span>
            <select
              value={subFilter}
              onChange={e => {
                setSubFilter(e.target.value);
                setPage(1);
              }}
              className="w-full md:w-auto bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:bg-white"
            >
              <option value="ALL">All Plans</option>
              <option value="ACTIVE">Active Plan</option>
              <option value="EXPIRING">Expiring Soon</option>
              <option value="EXPIRED">Expired</option>
              <option value="NONE">No Subscription</option>
            </select>
          </div>
        </div>
      </div>

      {/* VIEW MODE 1: DATA TABLE */}
      {viewMode === 'table' ? (
        <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-50/90 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px] tracking-wider">
                  <th className="py-3 px-4">Profile Photo & Name</th>
                  <th className="py-3 px-4">User Contact</th>
                  <th className="py-3 px-4 min-w-[260px]">Trusted Users (Emergency Circle)</th>
                  <th className="py-3 px-4">Hardware Device</th>
                  <th className="py-3 px-4">Subscription</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {paginatedUsers.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="py-12 text-center text-slate-400">
                      <Users className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                      <p className="font-semibold text-slate-700">No users found</p>
                      <p className="text-[11px] text-slate-400 mt-0.5">Try adjusting your search terms or filters.</p>
                    </td>
                  </tr>
                ) : (
                  paginatedUsers.map(user => {
                    const primaryDevice = user.devices[0];
                    const contacts = user.trusted_contacts || [];

                    return (
                      <tr
                        key={user.id}
                        className="hover:bg-slate-50/90 transition-colors group cursor-pointer"
                        onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
                      >
                        {/* Profile Photo (Avatar) + User Name */}
                        <td className="py-3.5 px-4">
                          <div className="flex items-center gap-3">
                            <div className="relative shrink-0">
                              {user.avatar ? (
                                <img
                                  src={user.avatar}
                                  alt={user.name}
                                  className="w-11 h-11 rounded-full object-cover border-2 border-slate-200 shadow-2xs group-hover:border-emerald-500 transition-colors"
                                />
                              ) : (
                                <div className="w-11 h-11 rounded-full bg-gradient-to-tr from-slate-700 to-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-2xs group-hover:border-emerald-500 transition-colors">
                                  {getInitials(user.name)}
                                </div>
                              )}
                              <span
                                className={`absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white ${
                                  user.status === 'ACTIVE'
                                    ? 'bg-emerald-500'
                                    : user.status === 'DISABLED'
                                    ? 'bg-rose-500'
                                    : 'bg-amber-500'
                                }`}
                                title={`Account: ${user.status}`}
                              />
                            </div>

                            <div className="min-w-0">
                              <span className="font-bold text-slate-900 group-hover:text-emerald-700 transition-colors text-xs block truncate">
                                {user.name}
                              </span>
                              <span className="text-[10px] text-slate-400 font-mono">User #{user.id}</span>
                            </div>
                          </div>
                        </td>

                        {/* User Contact (Mobile & Email) */}
                        <td className="py-3.5 px-4" onClick={e => e.stopPropagation()}>
                          <div className="space-y-1">
                            <div className="flex items-center gap-1.5 text-slate-900 font-semibold text-xs">
                              <Phone className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                              <span>{user.phone}</span>
                            </div>
                            <div className="flex items-center gap-1.5 text-slate-500 text-[11px] truncate max-w-[180px]">
                              <Mail className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                              <span className="truncate">{user.email}</span>
                            </div>
                          </div>
                        </td>

                        {/* Trusted Users (Name, Mobile, Email) */}
                        <td className="py-3.5 px-4" onClick={e => e.stopPropagation()}>
                          {contacts.length > 0 ? (
                            <div className="space-y-2">
                              {contacts.slice(0, 2).map((c, i) => (
                                <div
                                  key={c.id || i}
                                  className="p-2 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px] leading-tight space-y-1"
                                >
                                  <div className="flex items-center justify-between gap-1">
                                    <span className="font-bold text-slate-900 truncate">
                                      {c.name}{' '}
                                      <span className="text-[10px] text-slate-500 font-normal">
                                        ({c.relationship})
                                      </span>
                                    </span>
                                    {c.is_verified && (
                                      <span className="text-[9px] font-bold text-emerald-700 bg-emerald-100/90 px-1.5 py-0.2 rounded shrink-0">
                                        Verified
                                      </span>
                                    )}
                                  </div>
                                  <div className="flex items-center gap-1 text-slate-800 font-mono text-[10px]">
                                    <Phone className="w-3 h-3 text-emerald-600 shrink-0" />
                                    <span>{c.mobile_number}</span>
                                  </div>
                                  {c.email && (
                                    <div className="flex items-center gap-1 text-slate-500 text-[10px] truncate max-w-[200px]">
                                      <Mail className="w-3 h-3 text-slate-400 shrink-0" />
                                      <span className="truncate">{c.email}</span>
                                    </div>
                                  )}
                                </div>
                              ))}
                            </div>
                          ) : (
                            <span className="text-slate-400 italic text-[11px]">No trusted contacts</span>
                          )}
                        </td>

                        {/* Hardware Device */}
                        <td className="py-3.5 px-4">
                          {primaryDevice ? (
                            <div className="space-y-1">
                              <div className="flex items-center gap-1.5">
                                <Smartphone className="w-3.5 h-3.5 text-slate-600 shrink-0" />
                                <span className="font-semibold text-slate-900 truncate max-w-[140px]">
                                  {primaryDevice.device_name}
                                </span>
                              </div>
                              <div className="flex items-center gap-1.5 text-[10px] text-slate-500">
                                <span className="uppercase font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 text-[9px]">
                                  {primaryDevice.platform}
                                </span>
                                {primaryDevice.battery_level !== undefined && (
                                  <span className="flex items-center gap-0.5 text-slate-600">
                                    {primaryDevice.is_charging ? (
                                      <BatteryCharging className="w-3 h-3 text-emerald-600" />
                                    ) : (
                                      <Battery className="w-3 h-3 text-slate-400" />
                                    )}
                                    <span>{primaryDevice.battery_level}%</span>
                                  </span>
                                )}
                              </div>
                            </div>
                          ) : (
                            <span className="text-slate-400 italic text-[11px]">No Device</span>
                          )}
                        </td>

                        {/* Subscription */}
                        <td className="py-3.5 px-4">
                          <Badge
                            variant={
                              user.subscription_status === 'ACTIVE'
                                ? 'success'
                                : user.subscription_status === 'EXPIRING'
                                ? 'warning'
                                : 'neutral'
                            }
                          >
                            {user.subscription_status}
                          </Badge>
                          {user.current_subscription && (
                            <span className="text-[10px] text-slate-500 block mt-0.5 truncate max-w-[130px]">
                              {user.current_subscription.plan_name}
                            </span>
                          )}
                        </td>

                        {/* Account Status */}
                        <td className="py-3.5 px-4">
                          <Badge
                            variant={
                              user.status === 'ACTIVE'
                                ? 'success'
                                : user.status === 'DISABLED'
                                ? 'danger'
                                : 'warning'
                            }
                          >
                            {user.status}
                          </Badge>
                        </td>

                        {/* Actions */}
                        <td className="py-3.5 px-4 text-right" onClick={e => e.stopPropagation()}>
                          <div className="flex items-center justify-end gap-1.5">
                            <button
                              onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
                              className="px-2.5 py-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition-colors flex items-center gap-1 shadow-2xs"
                              title="Open User Detail & Device Controls"
                            >
                              <Eye className="w-3.5 h-3.5" />
                              <span>View</span>
                            </button>
                            <button
                              onClick={() => setCurrentRoute(`/admin/users/${user.id}/edit`)}
                              title="Edit User"
                              className="p-1.5 text-slate-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors"
                            >
                              <Edit2 className="w-3.5 h-3.5" />
                            </button>
                            {user.status === 'ACTIVE' ? (
                              <button
                                onClick={() => setPendingDisableUser(user)}
                                title="Disable User Account"
                                className="p-1.5 text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-colors"
                              >
                                <XCircle className="w-3.5 h-3.5" />
                              </button>
                            ) : (
                              <button
                                onClick={() => activateUser(user.id)}
                                title="Activate User Account"
                                className="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors"
                              >
                                <CheckCircle className="w-3.5 h-3.5" />
                              </button>
                            )}
                            <button
                              onClick={() => setPendingDeleteUser(user)}
                              title="Delete User"
                              className="p-1.5 text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
              <span>
                Showing {(page - 1) * pageSize + 1} - {Math.min(page * pageSize, filteredUsers.length)} of{' '}
                {filteredUsers.length} users
              </span>
              <div className="flex items-center gap-1">
                <button
                  disabled={page === 1}
                  onClick={() => setPage(p => p - 1)}
                  className="p-1 rounded hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>
                <span className="px-2 font-semibold text-slate-700">
                  Page {page} of {totalPages}
                </span>
                <button
                  disabled={page === totalPages}
                  onClick={() => setPage(p => p + 1)}
                  className="p-1 rounded hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}
        </div>
      ) : (
        /* VIEW MODE 2: CARD GRID */
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {paginatedUsers.map(user => {
            const primaryDevice = user.devices[0];
            const contacts = user.trusted_contacts || [];

            return (
              <div
                key={user.id}
                onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
                className="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs hover:border-emerald-500/80 transition-all cursor-pointer group flex flex-col justify-between space-y-4"
              >
                <div>
                  {/* Top user row */}
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <div className="relative shrink-0">
                        {user.avatar ? (
                          <img
                            src={user.avatar}
                            alt={user.name}
                            className="w-12 h-12 rounded-xl object-cover border-2 border-slate-200 shadow-2xs group-hover:border-emerald-500 transition-colors"
                          />
                        ) : (
                          <div className="w-12 h-12 rounded-xl bg-slate-900 text-white font-bold text-sm flex items-center justify-center">
                            {getInitials(user.name)}
                          </div>
                        )}
                        <span
                          className={`absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white ${
                            user.status === 'ACTIVE'
                              ? 'bg-emerald-500'
                              : user.status === 'DISABLED'
                              ? 'bg-rose-500'
                              : 'bg-amber-500'
                          }`}
                        />
                      </div>

                      <div>
                        <h3 className="font-bold text-slate-900 group-hover:text-emerald-700 text-sm transition-colors">
                          {user.name}
                        </h3>
                        <span className="text-[10px] text-slate-400 font-mono">User #{user.id}</span>
                      </div>
                    </div>

                    <Badge
                      variant={
                        user.status === 'ACTIVE'
                          ? 'success'
                          : user.status === 'DISABLED'
                          ? 'danger'
                          : 'warning'
                      }
                    >
                      {user.status}
                    </Badge>
                  </div>

                  {/* User Contact */}
                  <div className="mt-3 pt-3 border-t border-slate-100 space-y-1 text-xs">
                    <div className="flex items-center gap-2 text-slate-900 font-semibold">
                      <Phone className="w-3.5 h-3.5 text-emerald-600" />
                      <span>{user.phone}</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-500 text-[11px] truncate">
                      <Mail className="w-3.5 h-3.5 text-slate-400" />
                      <span className="truncate">{user.email}</span>
                    </div>
                  </div>

                  {/* Trusted Contacts Box */}
                  <div className="mt-3 p-2.5 rounded-lg bg-slate-50 border border-slate-100 space-y-1.5 text-xs">
                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                      Emergency Circle ({contacts.length})
                    </span>
                    {contacts.length > 0 ? (
                      contacts.map((c, idx) => (
                        <div key={idx} className="text-[11px] leading-tight pb-1 last:pb-0 border-b last:border-0 border-slate-200/50">
                          <div className="font-bold text-slate-800 flex items-center justify-between">
                            <span>{c.name} ({c.relationship})</span>
                            {c.is_verified && <span className="text-[9px] text-emerald-600 font-bold">Verified</span>}
                          </div>
                          <div className="text-[10px] text-slate-500 font-mono mt-0.5">{c.mobile_number}</div>
                          {c.email && <div className="text-[10px] text-slate-400 truncate">{c.email}</div>}
                        </div>
                      ))
                    ) : (
                      <span className="text-slate-400 italic text-[11px]">No trusted contacts</span>
                    )}
                  </div>

                  {/* Hardware Device */}
                  {primaryDevice && (
                    <div className="mt-3 flex items-center justify-between text-xs bg-slate-100/70 p-2 rounded-lg">
                      <div className="flex items-center gap-1.5 truncate">
                        <Smartphone className="w-3.5 h-3.5 text-slate-600" />
                        <span className="font-semibold text-slate-800 truncate">{primaryDevice.device_name}</span>
                      </div>
                      <span className="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                        {primaryDevice.battery_level}% Batt
                      </span>
                    </div>
                  )}
                </div>

                {/* Bottom Card Action */}
                <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                  <span className="text-[10px] text-slate-400 font-mono">{user.subscription_status} Plan</span>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      setCurrentRoute(`/admin/users/${user.id}`);
                    }}
                    className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition-colors shadow-2xs"
                  >
                    <span>View Forensics</span>
                    <ExternalLink className="w-3 h-3" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Confirmation Modals for Disable and Delete */}
      <ConfirmationModal
        isOpen={Boolean(pendingDisableUser)}
        onClose={() => setPendingDisableUser(null)}
        onConfirm={() => {
          if (pendingDisableUser) {
            disableUser(pendingDisableUser.id);
            setPendingDisableUser(null);
          }
        }}
        title="Suspend User Account"
        description={`Are you sure you want to disable ${pendingDisableUser?.name}? All protected hardware sessions will be revoked.`}
        confirmLabel="Disable Account"
        variant="warning"
      />

      <ConfirmationModal
        isOpen={Boolean(pendingDeleteUser)}
        onClose={() => setPendingDeleteUser(null)}
        onConfirm={() => {
          if (pendingDeleteUser) {
            deleteUser(pendingDeleteUser.id);
            setPendingDeleteUser(null);
          }
        }}
        title="Permanently Delete User"
        description={`Are you sure you want to permanently delete ${pendingDeleteUser?.name}? This action cannot be undone and will purge all forensic data.`}
        confirmLabel="Delete User"
        variant="danger"
      />
    </div>
  );
};
