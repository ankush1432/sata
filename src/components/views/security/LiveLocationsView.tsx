import React, { useState } from 'react';
import { useAdmin } from '../../../context/AdminContext';
import { MapPin, ExternalLink, Smartphone, Clock, Battery, Wifi, ShieldCheck, Navigation } from 'lucide-react';
import { MapModal } from '../../common/MapModal';
import { User } from '../../../types/admin';

export const LiveLocationsView: React.FC = () => {
  const { users, setCurrentRoute } = useAdmin();

  const [selectedUserForMap, setSelectedUserForMap] = useState<User | null>(null);

  const usersWithLocation = users.filter(u => u.location !== undefined);

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">Active Live Locations</h1>
          <p className="text-xs text-slate-500">
            Real-time GPS tracking sessions and continuous geodetic telemetry feeds
          </p>
        </div>
      </div>

      {usersWithLocation.length === 0 ? (
        <div className="bg-white rounded-xl p-12 text-center border border-slate-200">
          <MapPin className="w-12 h-12 text-slate-300 mx-auto mb-2" />
          <h3 className="text-sm font-bold text-slate-700">No Active GPS Sessions</h3>
          <p className="text-xs text-slate-400 mt-1">
            When hardware devices synchronize location telemetry, active coordinates will appear here.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {usersWithLocation.map(user => {
            const loc = user.location!;
            const device = user.devices[0];

            return (
              <div
                key={user.id}
                className="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs flex flex-col justify-between"
              >
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <button
                        onClick={() => setCurrentRoute(`/admin/users/${user.id}`)}
                        className="font-bold text-xs text-slate-900 hover:text-emerald-600 text-left"
                      >
                        {user.name}
                      </button>
                      <p className="text-[11px] text-slate-400">{user.phone}</p>
                    </div>
                    <span className="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full flex items-center gap-1">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                      <span>LIVE</span>
                    </span>
                  </div>

                  <div className="bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-1 text-xs">
                    <div className="text-slate-500 font-medium">Recorded Address</div>
                    <div className="font-semibold text-slate-900 line-clamp-1">{loc.address}</div>
                    <div className="font-mono text-[11px] text-slate-600">
                      {loc.latitude.toFixed(6)}, {loc.longitude.toFixed(6)} (±{loc.accuracy}m)
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-[11px] text-slate-600">
                    <div>
                      <span className="text-slate-400 block">Device:</span>
                      <span className="font-medium text-slate-800 truncate block">
                        {device ? device.device_name : 'Primary Mobile'}
                      </span>
                    </div>
                    <div>
                      <span className="text-slate-400 block">Updated:</span>
                      <span className="font-medium text-slate-800">{loc.last_updated.split(' ')[1]}</span>
                    </div>
                  </div>
                </div>

                <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                  <span className="text-[11px] text-slate-400 font-mono truncate max-w-[150px]">
                    {loc.short_url}
                  </span>
                  <button
                    onClick={() => setSelectedUserForMap(user)}
                    className="px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-1.5 shadow-2xs"
                  >
                    <span>View on Map</span>
                    <ExternalLink className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {selectedUserForMap && (
        <MapModal
          isOpen={Boolean(selectedUserForMap)}
          onClose={() => setSelectedUserForMap(null)}
          userName={selectedUserForMap.name}
          deviceName={selectedUserForMap.devices[0]?.device_name}
          latitude={selectedUserForMap.location?.latitude || null}
          longitude={selectedUserForMap.location?.longitude || null}
          accuracy={selectedUserForMap.location?.accuracy}
          address={selectedUserForMap.location?.address}
          lastUpdated={selectedUserForMap.location?.last_updated}
          shortUrl={selectedUserForMap.location?.short_url}
        />
      )}
    </div>
  );
};
