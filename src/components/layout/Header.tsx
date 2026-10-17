import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Menu,
  Search,
  Bell,
  CheckCircle2,
  AlertTriangle,
  LogOut,
  User,
  Shield,
  X,
  ExternalLink,
  Database
} from 'lucide-react';
import { DatabaseManagerModal } from '../common/DatabaseManagerModal';

export const Header: React.FC = () => {
  const {
    toggleSidebar,
    currentRoute,
    setCurrentRoute,
    searchTerm,
    setSearchTerm,
    notifications,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    addToast,
    adminUser,
    logout
  } = useAdmin();

  const [showNotifications, setShowNotifications] = useState(false);
  const [showProfileMenu, setShowProfileMenu] = useState(false);
  const [showDbModal, setShowDbModal] = useState(false);
  const [dbConnected, setDbConnected] = useState<boolean | null>(null);

  React.useEffect(() => {
    const checkDb = () => {
      fetch('/admin/api/db/status')
        .then(r => r.ok ? r.json() : null)
        .then(data => {
          if (data?.data) {
            setDbConnected(Boolean(data.data.connected));
          }
        })
        .catch(() => setDbConnected(false));
    };
    checkDb();
    const interval = setInterval(checkDb, 15000);
    return () => clearInterval(interval);
  }, []);

  const unreadNotifications = notifications.filter(n => !n.is_read);

  // Generate dynamic breadcrumbs based on current route
  const getBreadcrumbs = () => {
    const parts = currentRoute.split('/').filter(Boolean);
    if (parts.length === 0 || (parts.length === 2 && parts[1] === 'dashboard')) {
      return [{ label: 'Dashboard', path: '/admin/dashboard' }];
    }

    const crumbs = [{ label: 'Dashboard', path: '/admin/dashboard' }];

    if (parts.length > 1 && parts[1] !== 'dashboard') {
      const moduleName = parts[1];
      const mapping: Record<string, string> = {
        users: 'User Management',
        'subscription-plans': 'Subscription Plans',
        subscriptions: 'Subscriptions',
        'activation-codes': 'Activation Codes',
        'security-alerts': 'Security Alerts',
        'intruder-photos': 'Intruder Photos',
        'live-locations': 'Live Locations',
        'security-commands': 'Security Commands',
        reports: 'Reports',
        exports: 'Exports',
        settings: 'Settings',
        'system-logs': 'System Logs',
        'system-status': 'System Status'
      };

      crumbs.push({
        label: mapping[moduleName] || moduleName.toUpperCase(),
        path: `/admin/${moduleName}`
      });

      if (parts[2]) {
        crumbs.push({
          label: parts[2] === 'create' ? 'Add User' : parts[2] === 'generate' ? 'Generate Codes' : `Details #${parts[2]}`,
          path: currentRoute
        });
      }
    }

    return crumbs;
  };

  const breadcrumbs = getBreadcrumbs();

  return (
    <header className="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6">
      {/* Left: Sidebar Toggle & Breadcrumbs */}
      <div className="flex items-center gap-3 min-w-0">
        <button
          onClick={toggleSidebar}
          className="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
          title="Toggle Navigation Menu"
        >
          <Menu className="w-5 h-5" />
        </button>

        {/* Breadcrumb Navigation */}
        <nav className="hidden sm:flex items-center gap-1.5 text-xs text-slate-500 truncate">
          {breadcrumbs.map((crumb, idx) => (
            <React.Fragment key={`${crumb.path}-${idx}`}>
              {idx > 0 && <span className="text-slate-300">/</span>}
              <button
                onClick={() => setCurrentRoute(crumb.path)}
                className={`hover:text-slate-900 transition-colors truncate ${
                  idx === breadcrumbs.length - 1 ? 'font-semibold text-slate-900' : ''
                }`}
              >
                {crumb.label}
              </button>
            </React.Fragment>
          ))}
        </nav>
      </div>

      {/* Center/Right: Global Search Bar */}
      <div className="flex-1 max-w-xs sm:max-w-sm mx-3">
        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search users, devices, codes, alerts..."
            value={searchTerm}
            onChange={e => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
          />
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          )}
        </div>
      </div>

      {/* Right: DB Status, Notifications & Admin Profile */}
      <div className="flex items-center gap-2">
        {/* phpMyAdmin MySQL Live Indicator Button */}
        <button
          onClick={() => setShowDbModal(true)}
          className={`px-2.5 py-1.5 rounded-lg border flex items-center gap-1.5 text-xs font-semibold transition-colors shadow-2xs ${
            dbConnected === true
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100/70'
              : dbConnected === false
              ? 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100/70'
              : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
          }`}
          title="phpMyAdmin MySQL Database & Real-Time Gateway Management"
        >
          <Database className={`w-3.5 h-3.5 ${dbConnected ? 'text-emerald-600' : 'text-rose-600'}`} />
          <span className="hidden md:inline">
            {dbConnected === true ? 'phpMyAdmin: Connected' : dbConnected === false ? 'phpMyAdmin: Disconnected' : 'Checking DB...'}
          </span>
          <span
            className={`w-2 h-2 rounded-full ${
              dbConnected === true ? 'bg-emerald-500' : dbConnected === false ? 'bg-rose-500 animate-pulse' : 'bg-slate-400'
            }`}
          />
        </button>

        {/* Notifications Dropdown */}
        <div className="relative">
          <button
            onClick={() => {
              setShowNotifications(!showNotifications);
              setShowProfileMenu(false);
            }}
            className="p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors relative"
            title="Notifications"
          >
            <Bell className="w-5 h-5" />
            {unreadNotifications.length > 0 && (
              <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white" />
            )}
          </button>

          {showNotifications && (
            <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50 animate-in fade-in zoom-in-95 duration-100">
              <div className="px-4 py-2 border-b border-slate-100 flex items-center justify-between">
                <div>
                  <h4 className="text-xs font-bold text-slate-900">Notifications</h4>
                  <span className="text-[10px] text-slate-500">{unreadNotifications.length} unread alerts</span>
                </div>
                {unreadNotifications.length > 0 && (
                  <button
                    onClick={markAllNotificationsAsRead}
                    className="text-[11px] text-emerald-600 hover:text-emerald-700 font-semibold"
                  >
                    Mark all read
                  </button>
                )}
              </div>

              <div className="max-h-72 overflow-y-auto divide-y divide-slate-100">
                {notifications.length === 0 ? (
                  <div className="p-4 text-center text-xs text-slate-400">No notifications</div>
                ) : (
                  notifications.map(n => (
                    <div
                      key={n.id}
                      onClick={() => markNotificationAsRead(n.id)}
                      className={`p-3 text-xs hover:bg-slate-50 transition-colors cursor-pointer flex items-start gap-2.5 ${
                        !n.is_read ? 'bg-slate-50/60' : ''
                      }`}
                    >
                      <div className="shrink-0 pt-0.5">
                        {n.type === 'ALERT' || n.type === 'SECURITY' ? (
                          <AlertTriangle className="w-4 h-4 text-rose-500" />
                        ) : (
                          <CheckCircle2 className="w-4 h-4 text-blue-500" />
                        )}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <span className="font-semibold text-slate-900 truncate">{n.title}</span>
                          <span className="text-[10px] text-slate-400 shrink-0">
                            {n.created_at.split(' ')[1] || n.created_at}
                          </span>
                        </div>
                        <p className="text-[11px] text-slate-600 mt-0.5 leading-snug">{n.body}</p>
                      </div>
                    </div>
                  ))
                )}
              </div>

              <div className="px-4 py-2 border-t border-slate-100 text-center">
                <button
                  onClick={() => {
                    setCurrentRoute('/admin/security-alerts');
                    setShowNotifications(false);
                  }}
                  className="text-xs text-slate-600 hover:text-slate-900 font-medium"
                >
                  View All Security Alerts →
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Single Admin Profile Menu */}
        <div className="relative flex items-center gap-2">
          <button
            onClick={() => {
              setShowProfileMenu(!showProfileMenu);
              setShowNotifications(false);
            }}
            className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 transition-colors text-left"
          >
            <div className="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs">
              A
            </div>
            <div className="hidden md:block">
              <span className="text-xs font-semibold text-slate-900 block leading-tight">{adminUser?.name || 'Admin'}</span>
              <span className="text-[10px] text-slate-500 block leading-tight">{adminUser?.role || 'Super Admin'}</span>
            </div>
          </button>

          {/* Direct 1-Click Logout Button */}
          <button
            onClick={() => logout()}
            className="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors flex items-center gap-1 text-xs font-medium"
            title="Sign Out of Admin Console"
          >
            <LogOut className="w-4 h-4 text-rose-500" />
            <span className="hidden sm:inline text-rose-600 font-semibold">Sign Out</span>
          </button>

          {showProfileMenu && (
            <div className="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-50">
              <div className="px-4 py-2 border-b border-slate-100">
                <div className="text-xs font-bold text-slate-900">{adminUser?.name || 'SATA Administrator'}</div>
                <div className="text-[11px] text-slate-500 truncate">{adminUser?.email || 'admin@sata.security'}</div>
              </div>

              <button
                onClick={() => {
                  setCurrentRoute('/admin/settings');
                  setShowProfileMenu(false);
                }}
                className="w-full text-left px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
              >
                <User className="w-4 h-4 text-slate-400" />
                <span>Account Settings</span>
              </button>

              <button
                onClick={() => {
                  setCurrentRoute('/admin/system-status');
                  setShowProfileMenu(false);
                }}
                className="w-full text-left px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
              >
                <Shield className="w-4 h-4 text-slate-400" />
                <span>System Health & API</span>
              </button>

              <div className="border-t border-slate-100 my-1" />

              <button
                onClick={() => {
                  setShowProfileMenu(false);
                  logout();
                }}
                className="w-full text-left px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2"
              >
                <LogOut className="w-4 h-4" />
                <span>Sign Out</span>
              </button>
            </div>
          )}
        </div>
      </div>

      <DatabaseManagerModal isOpen={showDbModal} onClose={() => setShowDbModal(false)} />
    </header>
  );
};
