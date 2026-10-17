import React from 'react';
import { AdminProvider, useAdmin } from './context/AdminContext';
import { Sidebar } from './components/layout/Sidebar';
import { Header } from './components/layout/Header';
import { ToastContainer } from './components/common/ToastContainer';

// Views
import { DashboardView } from './components/views/DashboardView';
import { UsersListView } from './components/views/users/UsersListView';
import { UserDetailView } from './components/views/users/UserDetailView';
import { AddUserView } from './components/views/users/AddUserView';
import { EditUserView } from './components/views/users/EditUserView';
import { PlansView } from './components/views/subscriptions/PlansView';
import { SubscriptionsView } from './components/views/subscriptions/SubscriptionsView';
import { SubscriptionHistoryView } from './components/views/subscriptions/SubscriptionHistoryView';
import { ActivationCodesView } from './components/views/activation/ActivationCodesView';
import { ActivationHistoryView } from './components/views/activation/ActivationHistoryView';
import { SecurityAlertsView } from './components/views/security/SecurityAlertsView';
import { IntruderPhotosView } from './components/views/security/IntruderPhotosView';
import { LiveLocationsView } from './components/views/security/LiveLocationsView';
import { SecurityCommandsView } from './components/views/security/SecurityCommandsView';
import { ReportsView } from './components/views/reports/ReportsView';
import { ExportsView } from './components/views/exports/ExportsView';
import { SettingsView } from './components/views/system/SettingsView';
import { SystemLogsView } from './components/views/system/SystemLogsView';
import { SystemStatusView } from './components/views/system/SystemStatusView';
import { LoginView } from './components/views/LoginView';

function AdminAppContent() {
  const { currentRoute, isSidebarCollapsed, isAuthenticated } = useAdmin();

  // If unauthenticated, show Administrator Login portal
  if (!isAuthenticated) {
    return (
      <>
        <LoginView />
        <ToastContainer />
      </>
    );
  }

  // Route Parser & View Renderer
  const renderCurrentView = () => {
    const route = currentRoute.split('?')[0];

    // User Detail & Edit matchers
    const userDetailMatch = route.match(/^\/admin\/users\/(\d+)$/);
    if (userDetailMatch) {
      return <UserDetailView userId={Number(userDetailMatch[1])} />;
    }

    const userEditMatch = route.match(/^\/admin\/users\/(\d+)\/edit$/);
    if (userEditMatch) {
      return <EditUserView userId={Number(userEditMatch[1])} />;
    }

    switch (route) {
      case '/admin/dashboard':
        return <DashboardView />;

      // User Management
      case '/admin/users':
        return <UsersListView />;
      case '/admin/users/create':
        return <AddUserView />;
      case '/admin/users/activation':
        return <UsersListView initialFilter="AWAITING_ACTIVATION" />;
      case '/admin/users/disabled':
        return <UsersListView initialFilter="DISABLED" />;

      // Subscriptions
      case '/admin/subscription-plans':
        return <PlansView />;
      case '/admin/subscriptions':
        return <SubscriptionsView />;
      case '/admin/subscriptions/history':
        return <SubscriptionHistoryView />;

      // Activation
      case '/admin/activation-codes':
      case '/admin/activation-codes/generate':
        return <ActivationCodesView />;
      case '/admin/activation-codes/history':
        return <ActivationHistoryView />;

      // Security
      case '/admin/security-alerts':
        return <SecurityAlertsView />;
      case '/admin/intruder-photos':
        return <IntruderPhotosView />;
      case '/admin/live-locations':
        return <LiveLocationsView />;
      case '/admin/security-commands':
        return <SecurityCommandsView />;

      // Reports
      case '/admin/reports': {
        const queryParams = new URLSearchParams(currentRoute.split('?')[1] || '');
        const tab = (queryParams.get('tab') as any) || 'users';
        return <ReportsView initialTab={tab} />;
      }

      // Exports
      case '/admin/exports': {
        const queryParams = new URLSearchParams(currentRoute.split('?')[1] || '');
        const tab = (queryParams.get('tab') as any) || 'users';
        return <ExportsView initialTab={tab} />;
      }

      // System
      case '/admin/settings':
        return <SettingsView />;
      case '/admin/system-logs':
        return <SystemLogsView />;
      case '/admin/system-status':
        return <SystemStatusView />;

      default:
        return <DashboardView />;
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased flex">
      {/* Collapsible Left Navigation Sidebar */}
      <Sidebar />

      {/* Main Container */}
      <div
        className={`flex-1 flex flex-col min-w-0 transition-all duration-300 ${
          isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'
        }`}
      >
        {/* Top Header */}
        <Header />

        {/* Dynamic Route Body Content */}
        <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          {renderCurrentView()}
        </main>
      </div>

      {/* Floating System Toasts */}
      <ToastContainer />
    </div>
  );
}

export default function App() {
  return (
    <AdminProvider>
      <AdminAppContent />
    </AdminProvider>
  );
}
