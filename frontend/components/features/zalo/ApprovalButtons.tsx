"use client";

import { useState } from 'react';
import apiClient from '@/lib/api/client';

interface ApprovalButtonsProps {
  token: string;
  onSuccess: (message: string) => void;
}

function getErrorMessage(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: Partial<ApiError> } }).response;
    return response?.data?.message || 'An error occurred while processing your request.';
  }

  return error instanceof Error ? error.message : 'An error occurred while processing your request.';
}

export default function ApprovalButtons({ token, onSuccess }: ApprovalButtonsProps) {
  const [isRejecting, setIsRejecting] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleAction = async (action: 'approve' | 'reject') => {
    if (action === 'reject' && !isRejecting) {
      setIsRejecting(true);
      return;
    }

    if (action === 'reject' && isRejecting && !rejectReason.trim()) {
      setError('Please provide a reason for rejection so the system can improve.');
      return;
    }

    if (action === 'approve' && !window.confirm('Are you sure you want to approve and publish this article?')) {
      return;
    }

    setLoading(true);
    setError('');

    try {
      await apiClient.post(`/articles/review/${token}/action`, {
        action,
        reason: action === 'reject' ? rejectReason : undefined,
      });

      onSuccess(
        action === 'approve' 
          ? 'Article approved successfully! It is now queued for publishing.' 
          : 'Article rejected. The system has recorded your feedback.'
      );
    } catch (err: unknown) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-medium text-slate-900">Review Decision</h3>
      
      {error && <div className="text-red-500 text-sm bg-red-50 p-3 rounded-md">{error}</div>}

      {isRejecting ? (
        <div className="space-y-4 animate-in slide-in-from-bottom-2 fade-in">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">
              Rejection Notes (Required)
            </label>
            <textarea
              className="w-full border border-slate-300 rounded-lg shadow-sm p-3 text-sm focus:ring-blue-500 focus:border-blue-500"
              rows={4}
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              placeholder="Explain what needs to be changed..."
            />
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => setIsRejecting(false)}
              className="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              onClick={() => handleAction('reject')}
              className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
              disabled={loading}
            >
              {loading ? 'Processing...' : 'Confirm Rejection'}
            </button>
          </div>
        </div>
      ) : (
        <div className="flex flex-col sm:flex-row gap-4">
          <button
            onClick={() => handleAction('approve')}
            className="flex-1 bg-green-600 text-white py-3 px-4 rounded-xl font-medium hover:bg-green-700 transition-all shadow-sm hover:shadow-md active:scale-[0.98]"
            disabled={loading}
          >
            {loading ? 'Processing...' : '✓ Approve & Publish'}
          </button>
          <button
            onClick={() => handleAction('reject')}
            className="flex-1 bg-white border-2 border-red-100 text-red-600 py-3 px-4 rounded-xl font-medium hover:bg-red-50 transition-all active:scale-[0.98]"
            disabled={loading}
          >
            ✕ Reject / Request Changes
          </button>
        </div>
      )}
    </div>
  );
}
