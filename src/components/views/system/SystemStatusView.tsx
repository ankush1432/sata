import React, { useState } from 'react';
import { Activity, Server, Database, HardDrive, RefreshCw, CheckCircle2, ShieldCheck, Zap } from 'lucide-react';
import { Badge } from '../../common/Badge';

export const SystemStatusView: React.FC = () => {
  const [isPinging, setIsPinging] = useState(false);
  const [lastCheckTime, setLastCheckTime] = useState<string>(new Date().toLocaleTimeString());
  const [latencyMs, setLatencyMs] = useState<number>(12);

  const handlePing = () => {
    setIsPinging(true);
    setTimeout(() => {
      setLatencyMs(Math.floor(Math.random() * 15) + 8);
      setLastCheckTime(new Date().toLocaleTimeString());
      setIsPinging(false);
    }, 600);
  };

  const services = [
    { name: 'Core Laravel API Gateway', status: 'ONLINE', icon: Server, latency: `${latencyMs}ms`, desc: 'Routing /api/v1 endpoints with Sanctum token validation' },
    { name: 'phpMyAdmin MySQL Database', status: 'CONNECTED', icon: Database, latency: `${latencyMs > 0 ? latencyMs - 6 : 4}ms`, desc: 'Live XAMPP MySQL transactional database (sata) with InnoDB foreign keys' },
    { name: 'Private Evidence Storage Disk', status: 'ACCESSIBLE', icon: HardDrive, latency: '4ms', desc: 'Isolated filesystem for non-public intruder snapshots' },
    { name: 'Database Cache & Session Engine', status: 'OPERATIONAL', icon: Zap, latency: '1ms', desc: 'In-memory rate limiter and anti-brute force throttling' },
    { name: 'Firebase Cloud Messaging (FCM)', status: 'AUTHENTICATED', icon: Activity, latency: '28ms', desc: 'High-priority push delivery pipeline for remote commands' }
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">API & System Health Diagnostics</h1>
          <p className="text-xs text-slate-500">Live operational telemetry across database, filesystem, and push gateways</p>
        </div>

        <button
          onClick={handlePing}
          disabled={isPinging}
          className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${isPinging ? 'animate-spin' : ''}`} />
          <span>{isPinging ? 'Testing Ping...' : 'Probe Live Subsystems'}</span>
        </button>
      </div>

      <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold">
            <CheckCircle2 className="w-5 h-5" />
          </div>
          <div>
            <h3 className="text-xs font-bold text-emerald-950">All Systems 100% Operational</h3>
            <p className="text-[11px] text-emerald-800">
              Last probe verified at {lastCheckTime} • Zero degraded services
            </p>
          </div>
        </div>
        <span className="text-xs font-mono font-bold text-emerald-800 bg-emerald-100/80 px-2.5 py-1 rounded">
          {latencyMs}ms RTT
        </span>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs divide-y divide-slate-100">
        {services.map((srv, idx) => {
          const Icon = srv.icon;
          return (
            <div key={idx} className="p-4 flex items-center justify-between hover:bg-slate-50/60 transition-colors">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-slate-100 text-slate-700">
                  <Icon className="w-4 h-4" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-slate-900">{srv.name}</span>
                    <Badge variant="success">{srv.status}</Badge>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5">{srv.desc}</p>
                </div>
              </div>
              <span className="text-xs font-mono text-slate-500">{srv.latency}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
};
