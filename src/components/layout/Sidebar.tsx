import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  ShieldCheck,
  LayoutDashboard,
  Users,
  CreditCard,
  Layers,
  History,
  KeyRound,
  FilePlus,
  AlertTriangle,
  Camera,
  MapPin,
  Terminal,
  BarChart3,
  Download,
  Settings,
  FileText,
  Activity,
  LogOut,
  ChevronDown,
  ChevronRight,
  Menu,
  X
} from 'lucide-react';

interface NavGroup {
  id: string;
  label: string;
  items: {
    id: string;
    label: string;
    route: string;
    icon: React.ComponentType<{ className?: string }>;
    badge?: number;
  }[];
}

export const Sidebar: React.FC = () => {
  const { currentRoute, setCurrentRoute, isSidebarCollapsed, toggleSidebar, securityAlerts, users, addToast, adminUser, logout } = useAdmin();

  // Expanded state for nested groups
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({
    users: true,
    subscriptions: true,
    activation: true,
    security: true,
    reports: false,
    exports: false,
    system: false
  });

  const toggleGroup = (groupId: string) => {
    setOpenGroups(prev => ({ ...prev, [groupId]: !prev[groupId] }));
  };

  const unresolvedAlertsCount = securityAlerts.filter(a => a.status === 'UNRESOLVED').length;

  const NAV_GROUPS: NavGroup[] = [
    {
      id: 'users',
      label: 'USER MANAGEMENT',
      items: [
        { id: 'all-users', label: 'All Users', route: '/admin/users', icon: Users }
      ]
    },
    {
      id: 'subscriptions',
      label: 'SUBSCRIPTION',
      items: [
        { id: 'plans', label: 'Plans', route: '/admin/subscription-plans', icon: Layers },
        { id: 'subscriptions', label: 'Active Subscriptions', route: '/admin/subscriptions', icon: CreditCard },
        { id: 'subscription-history', label: 'Subscription History', route: '/admin/subscriptions/history', icon: History }
      ]
    },
    {
      id: 'activation',
      label: 'ACTIVATION',
      items: [
        { id: 'activation-codes', label: 'Activation Codes', route: '/admin/activation-codes', icon: KeyRound },
        { id: 'generate-code', label: 'Generate Code', route: '/admin/activation-codes/generate', icon: FilePlus },
        { id: 'activation-history', label: 'Activation History', route: '/admin/activation-codes/history', icon: History }
      ]
    },
    {
      id: 'security',
      label: 'SECURITY',
      items: [
        { id: 'alerts', label: 'Security Alerts', route: '/admin/security-alerts', icon: AlertTriangle, badge: unresolvedAlertsCount > 0 ? unresolvedAlertsCount : undefined },
        { id: 'intruders', label: 'Intruder Photos', route: '/admin/intruder-photos', icon: Camera },
        { id: 'locations', label: 'Live Locations', route: '/admin/live-locations', icon: MapPin },
        { id: 'commands', label: 'Security Commands', route: '/admin/security-commands', icon: Terminal }
      ]
    },
    {
      id: 'reports',
      label: 'REPORTS',
      items: [
        { id: 'rep-users', label: 'User Report', route: '/admin/reports?tab=users', icon: BarChart3 },
        { id: 'rep-sub', label: 'Subscription Report', route: '/admin/reports?tab=subscriptions', icon: BarChart3 },
        { id: 'rep-act', label: 'Activation Report', route: '/admin/reports?tab=activation', icon: BarChart3 },
        { id: 'rep-sec', label: 'Security Report', route: '/admin/reports?tab=security', icon: BarChart3 }
      ]
    },
    {
      id: 'exports',
      label: 'EXPORT',
      items: [
        { id: 'exp-users', label: 'Export Users', route: '/admin/exports?tab=users', icon: Download },
        { id: 'exp-sub', label: 'Export Subscriptions', route: '/admin/exports?tab=subscriptions', icon: Download },
        { id: 'exp-act', label: 'Export Activation Codes', route: '/admin/exports?tab=codes', icon: Download },
        { id: 'exp-sec', label: 'Export Security Logs', route: '/admin/exports?tab=logs', icon: Download }
      ]
    },
    {
      id: 'system',
      label: 'SYSTEM',
      items: [
        { id: 'settings', label: 'Settings', route: '/admin/settings', icon: Settings },
        { id: 'system-logs', label: 'System Logs', route: '/admin/system-logs', icon: FileText },
        { id: 'system-status', label: 'API / System Status', route: '/admin/system-status', icon: Activity }
      ]
    }
  ];

  return (
    <>
      {/* Mobile Drawer Backdrop */}
      {!isSidebarCollapsed && (
        <div
          className="fixed inset-0 bg-slate-900/40 z-40 lg:hidden backdrop-blur-2xs"
          onClick={toggleSidebar}
        />
      )}

      {/* Sidebar Container */}
      <aside
        className={`fixed top-0 bottom-0 left-0 z-40 flex flex-col bg-white border-r border-slate-200 transition-all duration-300 select-none ${
          isSidebarCollapsed ? '-translate-x-full lg:translate-x-0 lg:w-20' : 'translate-x-0 w-64'
        }`}
      >
        {/* Brand / Logo Header */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-slate-100 bg-white">
          <div
            onClick={() => setCurrentRoute('/admin/dashboard')}
            className="flex items-center gap-3 cursor-pointer overflow-hidden"
          >
            <div className="w-9 h-9 rounded-lg bg-emerald-600 flex items-center justify-center text-white shrink-0 shadow-xs">
              <ShieldCheck className="w-5 h-5" />
            </div>
            {!isSidebarCollapsed && (
              <div className="min-w-0">
                <span className="font-bold text-slate-900 text-sm tracking-tight block truncate">SATA Control</span>
                <span className="text-[10px] font-medium text-slate-400 block -mt-0.5">Admin Management</span>
              </div>
            )}
          </div>

          <button
            onClick={toggleSidebar}
            className="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 lg:hidden"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Scrollable Navigation Items */}
        <div className="flex-1 overflow-y-auto py-3 px-3 space-y-4">
          {/* Main Dashboard Link */}
          <div>
            <button
              onClick={() => setCurrentRoute('/admin/dashboard')}
              title="Dashboard"
              className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold transition-colors ${
                currentRoute === '/admin/dashboard'
                  ? 'bg-emerald-50 text-emerald-700 font-bold border border-emerald-200/60 shadow-2xs'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
              }`}
            >
              <LayoutDashboard className={`w-4 h-4 shrink-0 ${currentRoute === '/admin/dashboard' ? 'text-emerald-600' : 'text-slate-400'}`} />
              {!isSidebarCollapsed && <span>Dashboard</span>}
            </button>
          </div>

          {/* Grouped Modules */}
          {NAV_GROUPS.map(group => {
            const isGroupOpen = openGroups[group.id] !== false;
            const hasActiveRoute = group.items.some(item => currentRoute.startsWith(item.route.split('?')[0]));

            return (
              <div key={group.id} className="space-y-1">
                {!isSidebarCollapsed && (
                  <button
                    onClick={() => toggleGroup(group.id)}
                    className="w-full flex items-center justify-between px-3 py-1 text-[11px] font-bold text-slate-400 hover:text-slate-600 uppercase tracking-wider transition-colors"
                  >
                    <span>{group.label}</span>
                    {isGroupOpen ? <ChevronDown className="w-3 h-3" /> : <ChevronRight className="w-3 h-3" />}
                  </button>
                )}

                {(isGroupOpen || isSidebarCollapsed) && (
                  <div className="space-y-0.5">
                    {group.items.map(item => {
                      const isActive = currentRoute === item.route || currentRoute.startsWith(item.route.split('?')[0] + '/');
                      const Icon = item.icon;

                      return (
                        <button
                          key={item.id}
                          onClick={() => setCurrentRoute(item.route)}
                          title={item.label}
                          className={`w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-xs transition-colors ${
                            isActive
                              ? 'bg-slate-100 text-slate-900 font-semibold'
                              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                          }`}
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-emerald-600' : 'text-slate-400'}`} />
                            {!isSidebarCollapsed && <span className="truncate">{item.label}</span>}
                          </div>

                          {!isSidebarCollapsed && item.badge !== undefined && (
                            <span className="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-rose-100 text-rose-700">
                              {item.badge}
                            </span>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Footer Admin Info & Logout */}
        <div className="p-3 border-t border-slate-100 bg-slate-50/50">
          {!isSidebarCollapsed ? (
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2.5 min-w-0">
                <div className="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs">
                  {adminUser?.name ? adminUser.name.charAt(0) : 'SA'}
                </div>
                <div className="min-w-0">
                  <div className="text-xs font-semibold text-slate-900 truncate">{adminUser?.name || 'SATA Admin'}</div>
                  <div className="text-[10px] text-slate-500 truncate">{adminUser?.email || 'admin@sata.security'}</div>
                </div>
              </div>
              <button
                onClick={logout}
                title="Logout"
                className="text-slate-400 hover:text-rose-600 p-1.5 rounded-lg hover:bg-white transition-colors"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
          ) : (
            <button
              onClick={logout}
              title="Logout"
              className="w-full flex items-center justify-center p-2 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-white transition-colors"
            >
              <LogOut className="w-4 h-4" />
            </button>
          )}
        </div>
      </aside>
    </>
  );
};
