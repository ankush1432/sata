import React, { useState, useEffect } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  Settings,
  Shield,
  Bell,
  HardDrive,
  Check,
  Save,
  Database,
  RefreshCw,
  Server,
  Zap,
  Mail,
  FileCode,
  CheckCircle2,
  AlertTriangle
} from 'lucide-react';

export const SettingsView: React.FC = () => {
  const { settings, updateSettings, addToast } = useAdmin();

  const [formData, setFormData] = useState(settings);

  // phpMyAdmin DB state
  const [dbHost, setDbHost] = useState('127.0.0.1');
  const [dbPort, setDbPort] = useState('3306');
  const [dbUser, setDbUser] = useState('root');
  const [dbPass, setDbPass] = useState('');
  const [dbName, setDbName] = useState('sata');
  const [dbTesting, setDbTesting] = useState(false);
  const [dbStatus, setDbStatus] = useState<any>(null);

  // FCM and Email settings
  const [fcmProjectId, setFcmProjectId] = useState('sata-security-prod');
  const [fcmServerKey, setFcmServerKey] = useState('');
  const [smtpHost, setSmtpHost] = useState('smtp.gmail.com');
  const [smtpPort, setSmtpPort] = useState('587');
  const [smtpUser, setSmtpUser] = useState('');
  const [smtpPass, setSmtpPass] = useState('');
  const [smtpFrom, setSmtpFrom] = useState('security@sata.security');

  useEffect(() => {
    fetch('/admin/api/db/status')
      .then(r => r.ok ? r.json() : null)
      .then(json => {
        if (json?.data) {
          setDbStatus(json.data);
          if (json.data.config) {
            setDbHost(json.data.config.host || '127.0.0.1');
            setDbPort(String(json.data.config.port || '3306'));
            setDbUser(json.data.config.user || 'root');
            setDbName(json.data.config.database || 'sata');
          }
        }
      })
      .catch(() => {});
  }, []);

  const handleTestDb = async () => {
    setDbTesting(true);
    try {
      const res = await fetch('/admin/api/db/config', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          host: dbHost,
          port: Number(dbPort),
          user: dbUser,
          password: dbPass,
          database: dbName
        })
      });
      const json = await res.json();
      if (json.success) {
        addToast('MySQL Connected', `Successfully reached database '${dbName}' at ${dbHost}:${dbPort}`, 'success');
      } else {
        addToast('Connection Failed', json.error || json.message, 'error');
      }
      const stRes = await fetch('/admin/api/db/status');
      const stJson = await stRes.json();
      setDbStatus(stJson.data);
    } catch (err: any) {
      addToast('Error', err.message, 'error');
    } finally {
      setDbTesting(false);
    }
  };

  const handleRunMigration = async () => {
    setDbTesting(true);
    try {
      const res = await fetch('/admin/api/db/migrate', { method: 'POST' });
      const json = await res.json();
      if (json.success) {
        addToast('Migration Complete', `Executed ${json.statementsRun} statements from database.sql into phpMyAdmin`, 'success');
      } else {
        addToast('Migration Failed', json.message, 'error');
      }
    } catch (err: any) {
      addToast('Migration Error', err.message, 'error');
    } finally {
      setDbTesting(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateSettings(formData);
    addToast('Settings Saved', 'System configuration and policies updated successfully', 'success');
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div>
        <h1 className="text-xl font-bold text-slate-900 tracking-tight">System Settings & Integration Policies</h1>
        <p className="text-xs text-slate-500">
          Configure phpMyAdmin MySQL database, Firebase Cloud Messaging (FCM), and SMTP alert delivery
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* phpMyAdmin MySQL Configuration Card */}
        <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div className="flex items-center gap-2.5">
              <Database className="w-4 h-4 text-emerald-600" />
              <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">phpMyAdmin / XAMPP Database Connection</h3>
            </div>
            {dbStatus && (
              <span className={`text-[10px] font-mono px-2 py-0.5 rounded font-bold ${
                dbStatus.connected ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
              }`}>
                {dbStatus.connected ? `CONNECTED (${dbStatus.tablesCount} TABLES)` : 'DISCONNECTED'}
              </span>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">MySQL Host</label>
              <input
                type="text"
                value={dbHost}
                onChange={e => setDbHost(e.target.value)}
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">MySQL Port</label>
              <input
                type="text"
                value={dbPort}
                onChange={e => setDbPort(e.target.value)}
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Database Name (phpMyAdmin)</label>
              <input
                type="text"
                value={dbName}
                onChange={e => setDbName(e.target.value)}
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Database Username</label>
              <input
                type="text"
                value={dbUser}
                onChange={e => setDbUser(e.target.value)}
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>

            <div className="sm:col-span-2">
              <label className="block font-semibold text-slate-700 mb-1">Database Password</label>
              <input
                type="password"
                value={dbPass}
                onChange={e => setDbPass(e.target.value)}
                placeholder="(Empty in default XAMPP)"
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>
          </div>

          <div className="flex items-center justify-between pt-3 border-t border-slate-100 flex-wrap gap-2">
            <button
              type="button"
              onClick={handleRunMigration}
              disabled={dbTesting}
              className="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors"
            >
              <FileCode className="w-3.5 h-3.5 text-blue-600" />
              <span>Import / Run database.sql</span>
            </button>

            <button
              type="button"
              onClick={handleTestDb}
              disabled={dbTesting}
              className="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-2xs"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${dbTesting ? 'animate-spin' : ''}`} />
              <span>{dbTesting ? 'Connecting...' : 'Test & Save MySQL'}</span>
            </button>
          </div>
        </div>

        {/* General Application Settings */}
        <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center gap-2.5 pb-3 border-b border-slate-100">
            <Settings className="w-4 h-4 text-emerald-600" />
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">General Configuration</h3>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Application Name</label>
              <input
                type="text"
                value={formData.general.app_name}
                onChange={e =>
                  setFormData({
                    ...formData,
                    general: { ...formData.general, app_name: e.target.value }
                  })
                }
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Support Contact Email</label>
              <input
                type="email"
                value={formData.general.support_email}
                onChange={e =>
                  setFormData({
                    ...formData,
                    general: { ...formData.general, support_email: e.target.value }
                  })
                }
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800"
              />
            </div>
          </div>
        </div>

        {/* Security & Command Policies */}
        <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center gap-2.5 pb-3 border-b border-slate-100">
            <Shield className="w-4 h-4 text-emerald-600" />
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Security Guardrails</h3>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Max OTP Failure Attempts</label>
              <input
                type="number"
                min={3}
                max={10}
                value={formData.security.max_otp_attempts}
                onChange={e =>
                  setFormData({
                    ...formData,
                    security: { ...formData.security, max_otp_attempts: Number(e.target.value) }
                  })
                }
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800"
              />
              <span className="text-[10px] text-slate-400">Lockout after consecutive wrong attempts</span>
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">OTP Validity Window (Mins)</label>
              <input
                type="number"
                min={1}
                max={15}
                value={formData.security.otp_ttl_minutes}
                onChange={e =>
                  setFormData({
                    ...formData,
                    security: { ...formData.security, otp_ttl_minutes: Number(e.target.value) }
                  })
                }
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Command TTL Expiry (Hours)</label>
              <input
                type="number"
                min={1}
                max={72}
                value={formData.security.command_ttl_hours}
                onChange={e =>
                  setFormData({
                    ...formData,
                    security: { ...formData.security, command_ttl_hours: Number(e.target.value) }
                  })
                }
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800"
              />
            </div>
          </div>
        </div>

        {/* Notifications & FCM */}
        <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-2xs space-y-4">
          <div className="flex items-center gap-2.5 pb-3 border-b border-slate-100">
            <Bell className="w-4 h-4 text-emerald-600" />
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Push Delivery (Firebase Cloud Messaging v1)</h3>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Firebase Project ID</label>
              <input
                type="text"
                value={fcmProjectId}
                onChange={e => setFcmProjectId(e.target.value)}
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">FCM Server Key / Service Account</label>
              <input
                type="password"
                value={fcmServerKey}
                onChange={e => setFcmServerKey(e.target.value)}
                placeholder="AAAA... or Service Account JSON"
                className="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-800 font-mono"
              />
            </div>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 pt-2">
          <button
            type="submit"
            className="px-5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-2xs transition-colors flex items-center gap-1.5"
          >
            <Save className="w-4 h-4" />
            <span>Save System Settings</span>
          </button>
        </div>
      </form>
    </div>
  );
};
