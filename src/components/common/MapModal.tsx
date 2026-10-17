import React from 'react';
import { MapPin, Navigation, ExternalLink, ShieldCheck, Clock } from 'lucide-react';
import { Modal } from './Modal';

interface MapModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  userName?: string;
  deviceName?: string;
  latitude: number | null;
  longitude: number | null;
  accuracy?: number | null;
  address?: string;
  lastUpdated?: string;
  shortUrl?: string;
}

export const MapModal: React.FC<MapModalProps> = ({
  isOpen,
  onClose,
  title = 'Live GPS Location Tracking',
  userName,
  deviceName,
  latitude,
  longitude,
  accuracy,
  address,
  lastUpdated,
  shortUrl
}) => {
  if (!latitude || !longitude) return null;

  const mapsUrl = `https://www.google.com/maps?q=${latitude},${longitude}`;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={title} subtitle={userName ? `Tracking target: ${userName}` : undefined} maxWidth="2xl">
      <div className="space-y-4">
        {/* Visual Map Render Container */}
        <div className="relative w-full h-72 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 flex items-center justify-center">
          {/* Simulated Satellite/Map Canvas */}
          <div className="absolute inset-0 bg-gradient-to-br from-slate-200 via-slate-100 to-slate-200 opacity-80" />
          
          {/* Subtle Grid pattern representing roads & geographic blocks */}
          <div className="absolute inset-0 opacity-25 bg-[radial-gradient(#475569_1px,transparent_1px)] [background-size:16px_16px]" />
          
          {/* Target Pulse Marker */}
          <div className="relative z-10 flex flex-col items-center">
            <div className="relative flex items-center justify-center">
              <span className="absolute w-14 h-14 rounded-full bg-emerald-500/25 animate-ping" />
              <span className="absolute w-8 h-8 rounded-full bg-emerald-500/40" />
              <div className="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-lg border-2 border-white">
                <MapPin className="w-5 h-5 fill-white text-emerald-600" />
              </div>
            </div>
            <div className="mt-2 bg-slate-900/90 text-white text-xs px-2.5 py-1 rounded-full shadow-md font-mono flex items-center gap-1.5 backdrop-blur-xs">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
              <span>{latitude.toFixed(6)}, {longitude.toFixed(6)}</span>
            </div>
          </div>

          <div className="absolute bottom-3 left-3 bg-white/90 backdrop-blur-xs px-2.5 py-1 rounded-md border border-slate-200 text-[11px] text-slate-600 flex items-center gap-1.5 shadow-xs">
            <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
            <span>SATA Precision Telemetry (±{accuracy ? accuracy.toFixed(1) : '4.0'}m)</span>
          </div>
        </div>

        {/* Location Telemetry Details */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
          <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-1">
            <span className="text-slate-500 font-medium">Recorded Address / Coordinates</span>
            <p className="font-semibold text-slate-900 text-sm">
              {address || `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`}
            </p>
            {deviceName && <p className="text-slate-500">Transmitted from: {deviceName}</p>}
          </div>

          <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-1">
            <span className="text-slate-500 font-medium">Telemetry Timestamp & Short Link</span>
            <div className="flex items-center gap-1 text-slate-700">
              <Clock className="w-3.5 h-3.5 text-slate-400" />
              <span>{lastUpdated || 'Recently synchronized'}</span>
            </div>
            {shortUrl && (
              <p className="text-emerald-700 font-mono break-all text-[11px] pt-1">
                Emergency link: {shortUrl}
              </p>
            )}
          </div>
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-between pt-3 border-t border-slate-100">
          <div className="text-xs text-slate-500 flex items-center gap-1">
            <Navigation className="w-3.5 h-3.5 text-slate-400" />
            <span>Geodetic Datum: WGS84</span>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
            >
              Close
            </button>
            <a
              href={mapsUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="px-3.5 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-1.5 shadow-xs"
            >
              <span>Open in Google Maps</span>
              <ExternalLink className="w-3.5 h-3.5" />
            </a>
          </div>
        </div>
      </div>
    </Modal>
  );
};
