"use client";

import { useState } from 'react';
import KeywordImportDropzone from '@/components/features/keywords/KeywordImportDropzone';
import { useKeywordImport } from '@/hooks/useKeywords';
import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import Link from 'next/link';

export default function KeywordImportPage() {
  const [parsedData, setParsedData] = useState<any[]>([]);
  const [importResult, setImportResult] = useState<any>(null);
  
  const importMutation = useKeywordImport();

  const handleConfirm = () => {
    importMutation.mutate({ keywords: parsedData, tenant_id: 1 }, {
      onSuccess: (data) => {
        setImportResult(data);
      }
    });
  };

  if (importResult) {
    return (
      <div className="max-w-2xl mx-auto mt-12 animate-in zoom-in-95 duration-500">
        <div className="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200 p-10 text-center space-y-8 relative overflow-hidden">
          <div className="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
          <div className="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-inner border-4 border-white ring-4 ring-emerald-50">
            <CheckCircle2 className="w-10 h-10" />
          </div>
          <div>
            <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight">Import Thành Công!</h2>
            <p className="text-slate-500 mt-3 text-lg">Hệ thống đang đẩy dữ liệu vào Background Job để AI xử lý.</p>
          </div>
          
          <div className="bg-slate-50 rounded-2xl p-8 text-left grid grid-cols-3 gap-6 border border-slate-100 shadow-sm">
            <div className="text-center">
              <div className="text-4xl font-black text-emerald-600">{importResult.imported_count || parsedData.length}</div>
              <div className="text-sm font-bold text-slate-500 mt-2 uppercase tracking-wide">Đã thêm mới</div>
            </div>
            <div className="text-center border-l border-r border-slate-200">
              <div className="text-4xl font-black text-orange-500">{importResult.skipped_count || 0}</div>
              <div className="text-sm font-bold text-slate-500 mt-2 uppercase tracking-wide">Bỏ qua (Trùng)</div>
            </div>
            <div className="text-center">
              <div className="text-4xl font-black text-red-500">{importResult.error_count || 0}</div>
              <div className="text-sm font-bold text-slate-500 mt-2 uppercase tracking-wide">Lỗi</div>
            </div>
          </div>

          <Link href="/keywords" className="inline-block mt-4 px-8 py-3.5 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 transition-all active:scale-95 shadow-md">
            Quay lại quản lý từ khoá
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-5xl mx-auto space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex items-center gap-5">
        <Link href="/keywords" className="p-3 border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 bg-white shadow-sm transition-all active:scale-95">
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">Import Từ Khoá (Excel)</h1>
          <p className="text-slate-500 mt-1 font-medium">Đẩy hàng loạt từ khoá vào Pipeline tự động SEO</p>
        </div>
      </div>

      {!parsedData.length ? (
        <div className="bg-white p-10 rounded-3xl shadow-sm border border-slate-200">
          <KeywordImportDropzone onFileParsed={setParsedData} />
          
          <div className="mt-10 bg-blue-50/50 border border-blue-100 rounded-2xl p-6">
            <h3 className="font-bold text-blue-900 mb-3 flex items-center gap-2">
              <span className="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">i</span>
              Hướng dẫn định dạng file
            </h3>
            <ul className="list-disc pl-6 text-sm text-blue-800/80 space-y-2 font-medium">
              <li>Dòng 1 luôn luôn là <strong>Header</strong> (Tên cột).</li>
              <li>Cột A (Đầu tiên) phải chứa dữ liệu <strong>Từ khoá (Keyword)</strong>.</li>
              <li>Các cột khác hệ thống sẽ tự động bỏ qua để đảm bảo tính tinh gọn.</li>
            </ul>
          </div>
        </div>
      ) : (
        <div className="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
          <div className="p-8 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 bg-slate-50/80">
            <div>
              <h2 className="text-xl font-extrabold text-slate-900">Xác nhận dữ liệu Preview</h2>
              <p className="text-slate-500 mt-1.5 font-medium">Tìm thấy <strong className="text-blue-600">{parsedData.length}</strong> từ khoá hợp lệ. Dưới đây là 5 dòng mẫu.</p>
            </div>
            <div className="flex gap-3 w-full sm:w-auto">
              <button 
                onClick={() => setParsedData([])}
                className="flex-1 sm:flex-none px-6 py-3 bg-white border border-slate-300 rounded-xl font-bold text-slate-700 hover:bg-slate-50 transition-all active:scale-95"
                disabled={importMutation.isPending}
              >
                Hủy & Tải file khác
              </button>
              <button 
                onClick={handleConfirm}
                className="flex-1 sm:flex-none px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2 transition-all active:scale-95 shadow-md shadow-blue-600/20"
                disabled={importMutation.isPending}
              >
                {importMutation.isPending ? 'Đang Import...' : 'Xác nhận đẩy vào DB'}
              </button>
            </div>
          </div>
          
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-white">
              <tr>
                <th className="px-8 py-4 text-left text-xs font-extrabold text-slate-400 uppercase tracking-widest w-24">STT</th>
                <th className="px-8 py-4 text-left text-xs font-extrabold text-slate-400 uppercase tracking-widest">Từ khoá nhận diện</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-slate-100">
              {parsedData.slice(0, 5).map((row, idx) => (
                <tr key={idx} className="hover:bg-slate-50 transition-colors">
                  <td className="px-8 py-5 text-sm font-bold text-slate-400">{idx + 1}</td>
                  <td className="px-8 py-5 text-base font-bold text-slate-900">{row.keyword}</td>
                </tr>
              ))}
            </tbody>
          </table>
          
          {parsedData.length > 5 && (
            <div className="p-5 text-center text-sm font-bold text-slate-500 border-t border-slate-200 bg-slate-50/50">
              ...và {parsedData.length - 5} từ khoá khác đang ẩn
            </div>
          )}
        </div>
      )}
    </div>
  );
}
