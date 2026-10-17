import React, { useState, useEffect } from 'react';
import {
  Database,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  Server,
  Zap,
  Send,
  Mail,
  Smartphone,
  ShieldAlert,
  KeyRound,
  FileCode,
  X,
  MapPin,
  ExternalLink
} from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';

interface DatabaseManagerModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export const DatabaseManagerModal: React.FC<DatabaseManagerModalProps> = ({ isOpen, onClose }) => {
  const { addToast } = useAdmin();

  const [activeTab, setActiveTab] = useState<'mysql' | 'triggers'>('mysql');
  const [isLoading, setIsLoading] = useState(false);
  const [dbData, setDbData] = useState<{
    connected: boolean;
    engine: string;
    config: { host: string; port: number; user: string; database: string };
    message: string;
    tablesCount?: number;
    tablesList?: string[];
    error?: string;
  }>({
    connected: false,
    engine: 'MySQL (phpMyAdmin / XAMPP)',
    config: { host: '127.0.0.1', port: 3306, user: 'root', database: 'sata' },
    message: 'Testing connection to phpMyAdmin...',
  });

  // DB Config Form
  const [host, setHost] = useState('127.0.0.1');
  const [port, setPort] = useState('3306');
  const [user, setUser] = useState('root');
  const [password, setPassword] = useState('');
  const [database, setDatabase] = useState('sata');

  // Trigger Form
  const [triggerPhone, setTriggerPhone] = useState('+14155552671');
  const [triggerEmail, setTriggerEmail] = useState('alex.rivera@example.com');
  const [triggerName, setTriggerName] = useState('Alex Rivera');
  const [triggerPhotoUrl, setTriggerPhotoUrl] = useState('https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=600&auto=format&fit=crop&q=80');
  const [triggerLat, setTriggerLat] = useState('37.7749');
  const [triggerLng, setTriggerLng] = useState('-122.4194');
  const [triggerResult, setTriggerResult] = useState<any>(null);
  const [isTriggering, setIsTriggering] = useState(false);

  // Fetch current DB status
  const loadDbStatus = async () => {
    setIsLoading(true);
    try {
      const res = await fetch('/admin/api/db/status');
      if (res.ok) {
        const json = await res.json();
        if (json?.data) {
          setDbData(json.data);
          if (json.data.config) {
            setHost(json.data.config.host || '127.0.0.1');
            setPort(String(json.data.config.port || '3306'));
            setUser(json.data.config.user || 'root');
            setDatabase(json.data.config.database || 'sata');
          }
        }
      }
    } catch (err: any) {
      console.warn('DB check error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      loadDbStatus();
    }
  }, [isOpen]);

  // Test / Save DB credentials
  const handleSaveDbConfig = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    try {
      const res = await fetch('/admin/api/db/config', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          host,
          port: Number(port),
          user,
          password,
          database,
        }),
      });
      const json = await res.json();
      if (json.success) {
        addToast('MySQL Connected', json.message, 'success');
      } else {
        addToast('MySQL Connection Failed', json.error || json.message, 'error');
      }
      await loadDbStatus();
    } catch (err: any) {
      addToast('Error', err.message, 'error');
    } finally {
      setIsLoading(false);
    }
  };

  // Run database.sql migration
  const handleMigrate = async () => {
    setIsLoading(true);
    try {
      const res = await fetch('/admin/api/db/migrate', { method: 'POST' });
      const json = await res.json();
      if (json.success) {
        addToast('Migration Complete', `Executed ${json.statementsRun} statements from database.sql into phpMyAdmin`, 'success');
        await loadDbStatus();
      } else {
        addToast('Migration Failed', json.message, 'error');
      }
    } catch (err: any) {
      addToast('Migration Error', err.message, 'error');
    } finally {
      setIsLoading(false);
    }
  };

  // Trigger Real OTP
  const handleTriggerOtp = async () => {
    setIsTriggering(true);
    setTriggerResult(null);
    try {
      const res = await fetch('/admin/api/otp/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          phoneNumber: triggerPhone,
          email: triggerEmail,
          userName: triggerName,
          purpose: 'ADMIN_SECURITY_CHECK',
          deviceFcmToken: 'fcm_device_token_client_active',
        }),
      });
      const json = await res.json();
      setTriggerResult({ type: 'OTP', data: json });
      if (json.success) {
        addToast('Real OTP Dispatched', `Generated 6-digit OTP stored in MySQL table 'otp_verifications' and sent via FCM & Email!`, 'success');
      } else {
        addToast('OTP Error', json.message, 'error');
      }
    } catch (err: any) {
      addToast('Error', err.message, 'error');
    } finally {
      setIsTriggering(false);
    }
  };

  // Trigger Intruder Alert (Photo Link & Location Link via FCM + Email)
  const handleTriggerIntruderAlert = async () => {
    setIsTriggering(true);
    setTriggerResult(null);
    try {
      const res = await fetch('/admin/api/trigger/intruder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId: 1,
          deviceId: 101,
          eventType: 'SIM_CARD_REMOVED',
          photoUrl: triggerPhotoUrl,
          lat: Number(triggerLat),
          lng: Number(triggerLng),
          address: 'Market St & 4th St, San Francisco, CA',
        }),
      });
      const json = await res.json();
      setTriggerResult({ type: 'INTRUDER_ALERT', data: json });
      if (json.success) {
        addToast('Intruder Alert Dispatched', `Photo Link & Google Maps Link triggered via Firebase Cloud Messaging & Email!`, 'success');
      } else {
        addToast('Trigger Error', json.message, 'error');
      }
    } catch (err: any) {
      addToast('Error', err.message, 'error');
    } finally {
      setIsTriggering(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in duration-150">
      <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        {/* Modal Header */}
        <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
          <div className="flex items-center gap-3">
            <div className={`p-2.5 rounded-xl ${dbData.connected ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'} shadow-2xs`}>
              <Database className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                <span>phpMyAdmin & Real-Time Integration Hub</span>
                <span className={`text-[10px] font-mono px-2 py-0.5 rounded font-bold ${
                  dbData.connected ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                }`}>
                  {dbData.connected ? 'MYSQL CONNECTED' : 'MYSQL DISCONNECTED'}
                </span>
              </h2>
              <p className="text-[11px] text-slate-500">
                Direct phpMyAdmin MySQL database configuration, Firebase Cloud Messaging (FCM) & Email triggers
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation Tabs */}
        <div className="flex border-b border-slate-200 px-5 bg-white text-xs font-semibold gap-6">
          <button
            onClick={() => setActiveTab('mysql')}
            className={`py-3 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'mysql'
                ? 'border-emerald-600 text-emerald-700 font-bold'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Database className="w-3.5 h-3.5" />
            <span>phpMyAdmin MySQL Database</span>
          </button>

          <button
            onClick={() => setActiveTab('triggers')}
            className={`py-3 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'triggers'
                ? 'border-emerald-600 text-emerald-700 font-bold'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Zap className="w-3.5 h-3.5" />
            <span>Firebase (FCM) & Email Triggers (OTP / Alerts)</span>
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-5 overflow-y-auto space-y-5 text-xs flex-1">
          {/* TAB 1: MYSQL PHPMYADMIN CONFIGURATION */}
          {activeTab === 'mysql' && (
            <div className="space-y-4">
              {/* Connection Status Banner */}
              <div className={`p-4 rounded-xl border flex items-start justify-between gap-3 ${
                dbData.connected
                  ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950'
                  : 'bg-rose-50/70 border-rose-200 text-rose-950'
              }`}>
                <div className="flex items-start gap-3">
                  {dbData.connected ? (
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                  ) : (
                    <AlertTriangle className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                  )}
                  <div>
                    <h4 className="font-bold text-xs">
                      {dbData.connected ? 'MySQL Active & Synchronized' : 'MySQL Database Connection Pending'}
                    </h4>
                    <p className="text-[11px] mt-0.5 opacity-90">{dbData.message}</p>
                    {dbData.connected && dbData.tablesCount !== undefined && (
                      <div className="mt-2 text-[11px] font-mono flex items-center gap-2">
                        <span className="bg-emerald-100 text-emerald-900 px-2 py-0.5 rounded font-bold">
                          {dbData.tablesCount} Tables in '{database}'
                        </span>
                        <span className="text-emerald-800">
                          Engine: InnoDB • Character Set: utf8mb4
                        </span>
                      </div>
                    )}
                  </div>
                </div>

                <button
                  type="button"
                  onClick={loadDbStatus}
                  disabled={isLoading}
                  className="px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-colors flex items-center gap-1 shrink-0"
                >
                  <RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />
                  <span>Refresh</span>
                </button>
              </div>

              {/* phpMyAdmin Database Connection Form */}
              <form onSubmit={handleSaveDbConfig} className="bg-slate-50/80 p-4 rounded-xl border border-slate-200 space-y-4">
                <div className="flex items-center justify-between border-b border-slate-200/80 pb-2">
                  <span className="font-bold text-slate-900 flex items-center gap-1.5 uppercase text-[10px] tracking-wider">
                    <Server className="w-3.5 h-3.5 text-slate-600" />
                    XAMPP / phpMyAdmin Connection Settings
                  </span>
                  <span className="text-[10px] text-slate-500 font-mono">SQLite Configuration Removed</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1 text-[11px]">MySQL Host</label>
                    <input
                      type="text"
                      value={host}
                      onChange={e => setHost(e.target.value)}
                      placeholder="127.0.0.1 or localhost"
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-800 font-mono text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block font-semibold text-slate-700 mb-1 text-[11px]">MySQL Port</label>
                    <input
                      type="text"
                      value={port}
                      onChange={e => setPort(e.target.value)}
                      placeholder="3306"
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-800 font-mono text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block font-semibold text-slate-700 mb-1 text-[11px]">Database Username</label>
                    <input
                      type="text"
                      value={user}
                      onChange={e => setUser(e.target.value)}
                      placeholder="root"
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-800 font-mono text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block font-semibold text-slate-700 mb-1 text-[11px]">Database Password</label>
                    <input
                      type="password"
                      value={password}
                      onChange={e => setPassword(e.target.value)}
                      placeholder="(Empty by default in XAMPP)"
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-800 font-mono text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                    />
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block font-semibold text-slate-700 mb-1 text-[11px]">Database Name in phpMyAdmin</label>
                    <input
                      type="text"
                      value={database}
                      onChange={e => setDatabase(e.target.value)}
                      placeholder="sata"
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-800 font-mono text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                    />
                    <span className="text-[10px] text-slate-400 mt-1 block">
                      Matches the database name created in phpMyAdmin: http://localhost/phpmyadmin
                    </span>
                  </div>
                </div>

                <div className="flex items-center justify-between pt-2 border-t border-slate-200/80">
                  <button
                    type="button"
                    onClick={handleMigrate}
                    disabled={isLoading}
                    className="px-3 py-2 bg-white border border-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-100 transition-colors flex items-center gap-1.5 shadow-2xs"
                    title="Import tables and initial seeds directly into phpMyAdmin"
                  >
                    <FileCode className="w-3.5 h-3.5 text-blue-600" />
                    <span>Run / Import database.sql</span>
                  </button>

                  <button
                    type="submit"
                    disabled={isLoading}
                    className="px-4 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-1.5 shadow-2xs"
                  >
                    <Database className="w-3.5 h-3.5" />
                    <span>{isLoading ? 'Connecting...' : 'Save & Reconnect'}</span>
                  </button>
                </div>
              </form>

              {/* Table List View */}
              {dbData.tablesList && dbData.tablesList.length > 0 && (
                <div className="bg-white p-3.5 rounded-xl border border-slate-200 space-y-2">
                  <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                    Verified Tables in '{database}':
                  </span>
                  <div className="flex flex-wrap gap-1.5">
                    {dbData.tablesList.map(t => (
                      <span key={t} className="px-2 py-0.5 bg-slate-100 rounded text-[11px] font-mono text-slate-700 border border-slate-200/60">
                        {t}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* TAB 2: REAL FIREBASE (FCM) & EMAIL TRIGGERS */}
          {activeTab === 'triggers' && (
            <div className="space-y-4">
              <div className="bg-blue-50 border border-blue-200 rounded-xl p-3.5 text-blue-950 space-y-1">
                <div className="flex items-center gap-2 font-bold text-xs">
                  <Zap className="w-4 h-4 text-blue-600" />
                  <span>Real Delivery Pipeline Active</span>
                </div>
                <p className="text-[11px] text-blue-800 leading-relaxed">
                  Real OTP codes are generated securely, stored in MySQL table <code className="bg-blue-100 px-1 rounded font-mono">otp_verifications</code>, and dispatched to the user's device via <strong>Firebase Cloud Messaging (FCM)</strong> and styled <strong>Email</strong>.
                  Intruder photos & live GPS links are immediately dispatched to the user and their <strong>Trusted Emergency Contacts</strong>.
                </p>
              </div>

              {/* Trigger Targets */}
              <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <h4 className="font-bold text-slate-900 uppercase text-[10px] tracking-wider">
                  Target Recipient & Incident Context
                </h4>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label className="block text-[10px] font-semibold text-slate-600 mb-1">User Name</label>
                    <input
                      type="text"
                      value={triggerName}
                      onChange={e => setTriggerName(e.target.value)}
                      className="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs"
                    />
                  </div>

                  <div>
                    <label className="block text-[10px] font-semibold text-slate-600 mb-1">User Mobile Phone</label>
                    <input
                      type="text"
                      value={triggerPhone}
                      onChange={e => setTriggerPhone(e.target.value)}
                      className="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono"
                    />
                  </div>

                  <div>
                    <label className="block text-[10px] font-semibold text-slate-600 mb-1">User Alert Email</label>
                    <input
                      type="email"
                      value={triggerEmail}
                      onChange={e => setTriggerEmail(e.target.value)}
                      className="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/80">
                  <div>
                    <label className="block text-[10px] font-semibold text-slate-600 mb-1">Intruder Photo Evidence Link</label>
                    <input
                      type="text"
                      value={triggerPhotoUrl}
                      onChange={e => setTriggerPhotoUrl(e.target.value)}
                      className="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono"
                    />
                  </div>

                  <div>
                    <label className="block text-[10px] font-semibold text-slate-600 mb-1">GPS Coordinates (Lat, Lng)</label>
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value={triggerLat}
                        onChange={e => setTriggerLat(e.target.value)}
                        placeholder="Lat"
                        className="w-1/2 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono"
                      />
                      <input
                        type="text"
                        value={triggerLng}
                        onChange={e => setTriggerLng(e.target.value)}
                        placeholder="Lng"
                        className="w-1/2 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono"
                      />
                    </div>
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {/* 1. Trigger Real OTP Button */}
                <button
                  type="button"
                  onClick={handleTriggerOtp}
                  disabled={isTriggering}
                  className="p-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold text-left transition-colors flex items-center justify-between shadow-2xs group"
                >
                  <div className="flex items-center gap-2.5">
                    <KeyRound className="w-5 h-5 text-emerald-200 group-hover:scale-110 transition-transform" />
                    <div>
                      <div className="font-bold text-xs">Trigger Real OTP Code</div>
                      <div className="text-[10px] text-emerald-100">Writes to MySQL + Sends FCM & Email</div>
                    </div>
                  </div>
                  <Send className="w-4 h-4 text-emerald-200" />
                </button>

                {/* 2. Trigger Intruder Photo & Location Link Button */}
                <button
                  type="button"
                  onClick={handleTriggerIntruderAlert}
                  disabled={isTriggering}
                  className="p-3.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-semibold text-left transition-colors flex items-center justify-between shadow-2xs group"
                >
                  <div className="flex items-center gap-2.5">
                    <ShieldAlert className="w-5 h-5 text-rose-200 group-hover:scale-110 transition-transform" />
                    <div>
                      <div className="font-bold text-xs">Dispatch Intruder Photo & Map</div>
                      <div className="text-[10px] text-rose-100">FCM Push + Email to Trusted Contacts</div>
                    </div>
                  </div>
                  <Send className="w-4 h-4 text-rose-200" />
                </button>
              </div>

              {/* Result Preview Box */}
              {triggerResult && (
                <div className="p-4 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] space-y-2 border border-slate-800">
                  <div className="flex items-center justify-between text-emerald-400 font-bold">
                    <span>RECEIPT: {triggerResult.type} EXECUTED</span>
                    <span className="text-[10px] text-slate-400">CHANNELS: FCM + EMAIL + MYSQL</span>
                  </div>
                  <pre className="overflow-x-auto text-[10px] leading-relaxed text-slate-300">
                    {JSON.stringify(triggerResult.data, null, 2)}
                  </pre>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Modal Footer */}
        <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs">
          <div className="text-slate-500 text-[11px]">
            Engine: <strong className="text-slate-700">MySQL / phpMyAdmin</strong> • FCM v1 • Nodemailer SMTP
          </div>
          <button
            onClick={onClose}
            className="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg font-semibold transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};
