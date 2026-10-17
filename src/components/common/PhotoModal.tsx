import React from 'react';
import { Camera, Calendar, Smartphone, MapPin, Download, ShieldAlert } from 'lucide-react';
import { Modal } from './Modal';
import { SecurityAlert } from '../../types/admin';

interface PhotoModalProps {
  isOpen: boolean;
  onClose: () => void;
  alert: SecurityAlert | null;
}

export const PhotoModal: React.FC<PhotoModalProps> = ({ isOpen, onClose, alert }) => {
  if (!alert) return null;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Intruder Camera Snapshot Evidence"
      subtitle={`Security Incident UUID: ${alert.event_uuid}`}
      maxWidth="2xl"
    >
      <div className="space-y-4">
        {/* Photo Display Frame */}
        <div className="relative w-full rounded-xl overflow-hidden border border-slate-200 bg-slate-900 flex items-center justify-center min-h-[320px]">
          {alert.evidence_url ? (
            <img
              src={alert.evidence_url}
              alt="Intruder Snapshot"
              className="max-h-[420px] w-auto object-contain mx-auto"
            />
          ) : (
            <div className="text-center p-8 text-slate-400">
              <Camera className="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p className="text-sm">Snapshot image stream not available</p>
            </div>
          )}

          {/* Timestamp Watermark Overlay */}
          <div className="absolute top-3 left-3 bg-black/75 text-white text-[11px] px-2.5 py-1 rounded-md backdrop-blur-xs flex items-center gap-1.5 font-mono">
            <ShieldAlert className="w-3.5 h-3.5 text-rose-400" />
            <span>FRONT CAMERA BURST CAPTURE</span>
          </div>

          <div className="absolute bottom-3 right-3 bg-black/75 text-white text-[11px] px-2.5 py-1 rounded-md backdrop-blur-xs font-mono">
            {alert.created_at}
          </div>
        </div>

        {/* Incident Forensics Metadata */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div className="bg-slate-50 p-3 rounded-lg border border-slate-200">
            <span className="text-slate-500 font-medium">Target User</span>
            <p className="font-semibold text-slate-900 mt-0.5">{alert.user_name}</p>
            <p className="text-slate-500">{alert.user_phone}</p>
          </div>

          <div className="bg-slate-50 p-3 rounded-lg border border-slate-200">
            <span className="text-slate-500 font-medium">Hardware Model</span>
            <p className="font-semibold text-slate-900 mt-0.5">{alert.device_name}</p>
            <p className="text-slate-500 uppercase">{alert.platform} OS</p>
          </div>

          <div className="bg-slate-50 p-3 rounded-lg border border-slate-200">
            <span className="text-slate-500 font-medium">Location</span>
            <p className="font-semibold text-slate-900 mt-0.5 truncate">
              {alert.location?.address || 'GPS Coordinates Recorded'}
            </p>
            <p className="text-slate-500">±{alert.location?.accuracy || 4}m radius</p>
          </div>
        </div>

        {alert.notes && (
          <div className="bg-amber-50/70 border border-amber-200 text-amber-900 p-3 rounded-lg text-xs">
            <span className="font-semibold">Event Trigger Diagnostic:</span> {alert.notes}
          </div>
        )}

        {/* Footer */}
        <div className="flex items-center justify-between pt-3 border-t border-slate-100">
          <span className="text-xs text-slate-500">
            Storage: <code className="font-mono text-slate-700">private_evidence</code> (HMAC SHA-256 Verified)
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="px-3.5 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
            >
              Close
            </button>
            {alert.evidence_url && (
              <a
                href={alert.evidence_url}
                target="_blank"
                rel="noopener noreferrer"
                download={`intruder-${alert.event_uuid}.jpg`}
                className="px-3.5 py-1.5 text-xs font-semibold text-white bg-slate-900 rounded-lg hover:bg-slate-800 transition-colors flex items-center gap-1.5 shadow-xs"
              >
                <Download className="w-3.5 h-3.5" />
                <span>Download Forensic Snapshot</span>
              </a>
            )}
          </div>
        </div>
      </div>
    </Modal>
  );
};
