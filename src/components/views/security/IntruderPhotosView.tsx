import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { Camera, Calendar, Smartphone, MapPin, Eye, Download, ShieldAlert } from 'lucide-react';
import { PhotoModal } from '../../common/PhotoModal';
import { MapModal } from '../../common/MapModal';
import { SecurityAlert } from '../../../types/admin';

export const IntruderPhotosView: React.FC = () => {
  const { securityAlerts, setCurrentRoute } = useAdmin();

  const [selectedPhoto, setSelectedPhoto] = useState<SecurityAlert | null>(null);
  const [selectedMap, setSelectedMap] = useState<SecurityAlert | null>(null);

  const intruderAlerts = securityAlerts.filter(
    a => a.alert_type === 'INTRUDER_PHOTO_CAPTURED' && a.evidence_url
  );

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-xl font-bold text-slate-900 tracking-tight">Intruder Detection Photos</h1>
        <p className="text-xs text-slate-500">
          Surveillance evidence captured on failed passcode entries and unauthorized tamper triggers
        </p>
      </div>

      {intruderAlerts.length === 0 ? (
        <div className="bg-white rounded-xl p-12 text-center border border-slate-200 shadow-2xs">
          <Camera className="w-12 h-12 text-slate-300 mx-auto mb-2" />
          <h3 className="text-sm font-bold text-slate-700">No Intruder Photos Captured</h3>
          <p className="text-xs text-slate-400 mt-1">
            When hardware devices detect unauthorized unlock attempts, front camera snapshots appear here.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {intruderAlerts.map(alert => (
            <div
              key={alert.id}
              className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs hover:shadow-md transition-shadow group flex flex-col justify-between"
            >
              <div>
                {/* Photo Image Frame */}
                <div
                  onClick={() => setSelectedPhoto(alert)}
                  className="relative w-full h-48 bg-slate-900 overflow-hidden cursor-pointer"
                >
                  <img
                    src={alert.evidence_url}
                    alt="Intruder"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                  />
                  <div className="absolute top-2.5 left-2.5 bg-black/75 backdrop-blur-xs text-white text-[10px] px-2 py-0.5 rounded font-mono flex items-center gap-1">
                    <ShieldAlert className="w-3 h-3 text-rose-400" />
                    <span>FRONT CAMERA BURST</span>
                  </div>
                  <div className="absolute bottom-2.5 right-2.5 bg-black/75 backdrop-blur-xs text-white text-[10px] px-2 py-0.5 rounded font-mono">
                    {alert.created_at}
                  </div>
                </div>

                {/* Metadata */}
                <div className="p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <button
                      onClick={() => setCurrentRoute(`/admin/users/${alert.user_id}`)}
                      className="font-bold text-xs text-slate-900 hover:text-emerald-600 text-left"
                    >
                      {alert.user_name}
                    </button>
                    <span className="text-[10px] text-slate-400 font-mono">#{alert.id}</span>
                  </div>

                  <div className="text-[11px] text-slate-600 flex items-center gap-1.5">
                    <Smartphone className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span className="truncate">{alert.device_name} ({alert.platform.toUpperCase()})</span>
                  </div>

                  {alert.location && (
                    <div className="text-[11px] text-slate-500 flex items-center gap-1.5 truncate">
                      <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                      <span className="truncate">{alert.location.address}</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Action Buttons */}
              <div className="p-3 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs">
                <button
                  onClick={() => setSelectedPhoto(alert)}
                  className="px-2.5 py-1 text-slate-700 hover:text-slate-900 font-medium flex items-center gap-1 hover:bg-slate-200/60 rounded transition-colors"
                >
                  <Eye className="w-3.5 h-3.5" />
                  <span>Inspect Snapshot</span>
                </button>

                {alert.location && (
                  <button
                    onClick={() => setSelectedMap(alert)}
                    className="px-2.5 py-1 text-emerald-700 hover:text-emerald-800 font-medium flex items-center gap-1 hover:bg-emerald-100/60 rounded transition-colors"
                  >
                    <MapPin className="w-3.5 h-3.5" />
                    <span>View Map</span>
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <PhotoModal
        isOpen={Boolean(selectedPhoto)}
        onClose={() => setSelectedPhoto(null)}
        alert={selectedPhoto}
      />

      <MapModal
        isOpen={Boolean(selectedMap)}
        onClose={() => setSelectedMap(null)}
        userName={selectedMap?.user_name}
        deviceName={selectedMap?.device_name}
        latitude={selectedMap?.location?.latitude || null}
        longitude={selectedMap?.location?.longitude || null}
        accuracy={selectedMap?.location?.accuracy}
        address={selectedMap?.location?.address}
        lastUpdated={selectedMap?.created_at}
      />
    </div>
  );
};
