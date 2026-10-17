import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import {
  AlertTriangle,
  Search,
  Filter,
  CheckCircle,
  Trash2,
  Eye,
  Camera,
  MapPin,
  Download,
  ShieldAlert
} from 'lucide-react';
import { Badge } from '../../common/Badge';
import { ConfirmationModal } from '../../common/ConfirmationModal';
import { PhotoModal } from '../../common/PhotoModal';
import { MapModal } from '../../common/MapModal';
import { SecurityAlert, AlertType, AlertSeverity } from '../../../types/admin';

export const SecurityAlertsView: React.FC = () => {
  const {
    securityAlerts,
    resolveSecurityAlert,
    deleteSecurityAlert,
    setCurrentRoute,
    searchTerm,
    setSearchTerm,
    exportToCSV
  } = useAdmin();

  const [typeFilter, setTypeFilter] = useState<string>('ALL');
  const [severityFilter, setSeverityFilter] = useState<string>('ALL');
  const [statusFilter, setStatusFilter] = useState<string>('ALL');

  // Modals
  const [photoAlert, setPhotoAlert] = useState<SecurityAlert | null>(null);
  const [mapAlert, setMapAlert] = useState<SecurityAlert | null>(null);
  const [resolvingAlert, setResolvingAlert] = useState<SecurityAlert | null>(null);
  const [deletingAlert, setDeletingAlert] = useState<SecurityAlert | null>(null);

  const filteredAlerts = useMemo(() => {
    return securityAlerts.filter(a => {
      const matchSearch =
        searchTerm === '' ||
        a.user_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        a.device_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        a.alert_type.toLowerCase().includes(searchTerm.toLowerCase());

      const matchType = typeFilter === 'ALL' || a.alert_type === typeFilter;
      const matchSeverity = severityFilter === 'ALL' || a.severity === severityFilter;
      const matchStatus = statusFilter === 'ALL' || a.status === statusFilter;

      return matchSearch && matchType && matchSeverity && matchStatus;
    });
  }, [securityAlerts, searchTerm, typeFilter, severityFilter, statusFilter]);

  const handleExport = () => {
    const headers = ['ID', 'Event UUID', 'User', 'Alert Type', 'Severity', 'Device', 'Status', 'Location', 'Occurred At'];
    const rows = filteredAlerts.map(a => [
      a.id,
      a.event_uuid,
      a.user_name,
      a.alert_type,
      a.severity,
      a.device_name,
      a.status,
      a.location?.address || 'N/A',
      a.created_at
    ]);
    exportToCSV('sata_security_alerts', headers, rows);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Security Incident Alerts</h1>
          <p className="text-xs text-slate-500">
            Real-time notifications for intruder detections, SIM removal, and PIN failure events
          </p>
        </div>

        <button
          onClick={handleExport}
          className="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 self-start"
        >
          <Download className="w-3.5 h-3.5" />
          <span>Export Alerts</span>
        </button>
      </div>

      {/* Filter Bar */}
      <div className="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search by user, device, or event type..."
            value={searchTerm}
            onChange={e => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
        </div>

        <div className="flex items-center gap-1.5 text-xs">
          <span className="text-slate-500 font-medium">Type:</span>
          <select
            value={typeFilter}
            onChange={e => setTypeFilter(e.target.value)}
            className="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:bg-white"
          >
            <option value="ALL">All Event Types</option>
            <option value="INTRUDER_PHOTO_CAPTURED">Intruder Photo</option>
            <option value="WRONG_PIN_ATTEMPT">Wrong PIN Attempt</option>
            <option value="SIM_CARD_REMOVED">SIM Removed</option>
            <option value="POWER_OFF_ATTEMPT">Power Off Attempt</option>
          </select>
        </div>

        <div className="flex items-center gap-1.5 text-xs">
          <span className="text-slate-500 font-medium">Severity:</span>
          <select
            value={severityFilter}
            onChange={e => setSeverityFilter(e.target.value)}
            className="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:bg-white"
          >
            <option value="ALL">All Severities</option>
            <option value="CRITICAL">Critical</option>
            <option value="HIGH">High</option>
            <option value="MEDIUM">Medium</option>
            <option value="LOW">Low</option>
          </select>
        </div>

        <div className="flex items-center gap-1.5 text-xs">
          <span className="text-slate-500 font-medium">Status:</span>
          <select
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
            className="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:bg-white"
          >
            <option value="ALL">All Statuses</option>
            <option value="UNRESOLVED">Unresolved</option>
            <option value="RESOLVED">Resolved</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[11px] tracking-wider">
                <th className="py-3 px-4">Event ID</th>
                <th className="py-3 px-4">User</th>
                <th className="py-3 px-4">Alert Type</th>
                <th className="py-3 px-4">Severity</th>
                <th className="py-3 px-4">Hardware Device</th>
                <th className="py-3 px-4">Location</th>
                <th className="py-3 px-4">Created At</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredAlerts.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-8 text-center text-slate-400">
                    No security alerts matching filter
                  </td>
                </tr>
              ) : (
                filteredAlerts.map(alert => (
                  <tr key={alert.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3 px-4 font-mono font-medium text-slate-500">#{alert.id}</td>
                    <td className="py-3 px-4">
                      <button
                        onClick={() => setCurrentRoute(`/admin/users/${alert.user_id}`)}
                        className="font-bold text-slate-900 hover:text-emerald-600 text-left"
                      >
                        {alert.user_name}
                      </button>
                      <div className="text-[11px] text-slate-400">{alert.user_phone}</div>
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex items-center gap-1.5 font-semibold text-slate-900">
                        {alert.evidence_url && <Camera className="w-3.5 h-3.5 text-emerald-600" />}
                        <span>{alert.alert_type.replace(/_/g, ' ')}</span>
                      </div>
                    </td>
                    <td className="py-3 px-4">
                      <Badge
                        variant={
                          alert.severity === 'CRITICAL'
                            ? 'danger'
                            : alert.severity === 'HIGH'
                            ? 'warning'
                            : 'info'
                        }
                      >
                        {alert.severity}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 text-slate-700">
                      <div>{alert.device_name}</div>
                      <span className="uppercase text-[9px] font-bold text-slate-400">{alert.platform}</span>
                    </td>
                    <td className="py-3 px-4 text-slate-600 max-w-xs truncate">
                      {alert.location?.address || 'GPS Coordinates Recorded'}
                    </td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">{alert.created_at}</td>
                    <td className="py-3 px-4">
                      <Badge variant={alert.status === 'UNRESOLVED' ? 'danger' : 'success'}>
                        {alert.status}
                      </Badge>
                    </td>
                    <td className="py-3 px-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        {alert.evidence_url && (
                          <button
                            onClick={() => setPhotoAlert(alert)}
                            title="Inspect Intruder Photo"
                            className="p-1.5 text-slate-600 hover:text-emerald-600 hover:bg-slate-100 rounded-md transition-colors"
                          >
                            <Camera className="w-3.5 h-3.5" />
                          </button>
                        )}
                        {alert.location && (
                          <button
                            onClick={() => setMapAlert(alert)}
                            title="View GPS on Map"
                            className="p-1.5 text-slate-600 hover:text-blue-600 hover:bg-slate-100 rounded-md transition-colors"
                          >
                            <MapPin className="w-3.5 h-3.5" />
                          </button>
                        )}
                        {alert.status === 'UNRESOLVED' && (
                          <button
                            onClick={() => setResolvingAlert(alert)}
                            title="Mark Resolved"
                            className="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-md transition-colors"
                          >
                            <CheckCircle className="w-3.5 h-3.5" />
                          </button>
                        )}
                        <button
                          onClick={() => setDeletingAlert(alert)}
                          title="Delete Alert"
                          className="p-1.5 text-rose-500 hover:bg-rose-50 rounded-md transition-colors"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modals */}
      <PhotoModal
        isOpen={Boolean(photoAlert)}
        onClose={() => setPhotoAlert(null)}
        alert={photoAlert}
      />

      <MapModal
        isOpen={Boolean(mapAlert)}
        onClose={() => setMapAlert(null)}
        userName={mapAlert?.user_name}
        deviceName={mapAlert?.device_name}
        latitude={mapAlert?.location?.latitude || null}
        longitude={mapAlert?.location?.longitude || null}
        accuracy={mapAlert?.location?.accuracy}
        address={mapAlert?.location?.address}
        lastUpdated={mapAlert?.created_at}
      />

      <ConfirmationModal
        isOpen={Boolean(resolvingAlert)}
        onClose={() => setResolvingAlert(null)}
        onConfirm={() => {
          if (resolvingAlert) {
            resolveSecurityAlert(resolvingAlert.id);
            setResolvingAlert(null);
          }
        }}
        title="Resolve Security Incident"
        description={`Mark alert #${resolvingAlert?.id} (${resolvingAlert?.alert_type}) as reviewed and resolved?`}
        confirmLabel="Mark as Resolved"
        variant="info"
      />

      <ConfirmationModal
        isOpen={Boolean(deletingAlert)}
        onClose={() => setDeletingAlert(null)}
        onConfirm={() => {
          if (deletingAlert) {
            deleteSecurityAlert(deletingAlert.id);
            setDeletingAlert(null);
          }
        }}
        title="Delete Alert"
        description="Permanently delete this security incident report from the forensic database?"
        confirmLabel="Delete Alert"
        variant="danger"
      />
    </div>
  );
};
