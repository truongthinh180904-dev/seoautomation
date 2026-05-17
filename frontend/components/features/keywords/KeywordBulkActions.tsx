import React from 'react';
import { Trash2 } from 'lucide-react';

interface KeywordBulkActionsProps {
  selectedIds: number[];
  onBulkDelete: () => void;
  isDeleting: boolean;
}

export default function KeywordBulkActions({ selectedIds, onBulkDelete, isDeleting }: KeywordBulkActionsProps) {
  if (selectedIds.length === 0) return null;

  return (
    <div className="fixed bottom-8 left-1/2 -translate-x-1/2 bg-slate-900/95 backdrop-blur border border-slate-700 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-6 animate-in slide-in-from-bottom-8 fade-in z-50">
      <div className="flex items-center gap-3">
        <span className="flex items-center justify-center bg-blue-600 text-white font-bold w-6 h-6 rounded-full text-xs">
          {selectedIds.length}
        </span>
        <span className="font-medium">mục được chọn</span>
      </div>
      <div className="w-px h-6 bg-slate-700"></div>
      <button 
        onClick={onBulkDelete}
        disabled={isDeleting}
        className="flex items-center gap-2 text-red-400 hover:text-red-300 font-bold transition-colors active:scale-95"
      >
        <Trash2 className="w-4 h-4" />
        {isDeleting ? 'Đang xoá...' : 'Xóa tất cả'}
      </button>
    </div>
  );
}
