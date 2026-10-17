import React from 'react';
import { AlertTriangle, AlertOctagon, CheckCircle2 } from 'lucide-react';
import { Modal } from './Modal';

interface ConfirmationModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info';
  deviceInfo?: {
    userName: string;
    deviceName: string;
    platform: string;
  };
  isLoading?: boolean;
}

export const ConfirmationModal: React.FC<ConfirmationModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  description,
  confirmLabel = 'Confirm Action',
  cancelLabel = 'Cancel',
  variant = 'danger',
  deviceInfo,
  isLoading = false
}) => {
  const icon = {
    danger: <AlertOctagon className="w-6 h-6 text-rose-600" />,
    warning: <AlertTriangle className="w-6 h-6 text-amber-600" />,
    info: <CheckCircle2 className="w-6 h-6 text-blue-600" />
  }[variant];

  const confirmBtnStyles = {
    danger: 'bg-rose-600 hover:bg-rose-700 text-white focus:ring-rose-500',
    warning: 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500',
    info: 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500'
  }[variant];

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={title} maxWidth="md">
      <div className="space-y-4">
        <div className="flex items-start gap-3">
          <div className="shrink-0 p-2.5 rounded-full bg-slate-100 border border-slate-200">
            {icon}
          </div>
          <div className="text-sm text-slate-600 leading-relaxed pt-1">
            {description}
          </div>
        </div>

        {deviceInfo && (
          <div className="bg-slate-50 rounded-lg p-3 border border-slate-200 text-xs space-y-1">
            <div className="font-semibold text-slate-700 mb-1">Target Hardware Verification:</div>
            <div className="flex justify-between text-slate-600">
              <span>Account Owner:</span>
              <span className="font-medium text-slate-900">{deviceInfo.userName}</span>
            </div>
            <div className="flex justify-between text-slate-600">
              <span>Device Model:</span>
              <span className="font-medium text-slate-900">{deviceInfo.deviceName}</span>
            </div>
            <div className="flex justify-between text-slate-600">
              <span>Operating System:</span>
              <span className="font-medium uppercase text-slate-900">{deviceInfo.platform}</span>
            </div>
          </div>
        )}

        <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <button
            type="button"
            onClick={onClose}
            disabled={isLoading}
            className="px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            onClick={() => {
              onConfirm();
            }}
            disabled={isLoading}
            className={`px-4 py-2 text-xs font-semibold rounded-lg shadow-xs transition-colors focus:ring-2 focus:ring-offset-1 ${confirmBtnStyles}`}
          >
            {isLoading ? 'Processing...' : confirmLabel}
          </button>
        </div>
      </div>
    </Modal>
  );
};
