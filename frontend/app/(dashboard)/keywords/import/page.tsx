"use client";

import { useState } from 'react';
import KeywordImportDropzone from '@/components/features/keywords/KeywordImportDropzone';
import { useKeywordImport, useKeywordImportPreview } from '@/hooks/useKeywords';
import { AlertCircle, ArrowLeft, CheckCircle2 } from 'lucide-react';
import Link from 'next/link';

export default function KeywordImportPage() {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [parsedData, setParsedData] = useState<KeywordImportPreviewRow[]>([]);
  const [serverPreview, setServerPreview] = useState<KeywordImportPreviewResult | null>(null);
  const [importResult, setImportResult] = useState<KeywordImportResult | null>(null);
  const [importError, setImportError] = useState('');
  
  const importMutation = useKeywordImport();
  const previewMutation = useKeywordImportPreview();

  const handleConfirm = () => {
    if (!selectedFile) return;

    importMutation.mutate({ file: selectedFile }, {
      onSuccess: (data) => {
        setImportError('');
        setImportResult(data);
      },
      onError: (error) => {
        setImportError(error instanceof Error ? error.message : 'Import thất bại. Vui lòng thử lại.');
      }
    });
  };

  const handleFileParsed = (file: File, rows: KeywordImportPreviewRow[]) => {
    setSelectedFile(file);
    setParsedData(rows);
    setServerPreview(null);
    previewMutation.mutate({ file }, {
      onSuccess: (data) => {
        setServerPreview(data);
        setImportError('');
      },
      onError: (error) => {
        setImportError(error instanceof Error ? error.message : 'Không preview được file import.');
      },
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
              <div className="text-4xl font-black text-emerald-600">{importResult.imported}</div>
              <div className="text-sm font-bold text-slate-500 mt-2 uppercase tracking-wide">Đã thêm mới</div>
            </div>
            <div className="text-center border-l border-r border-slate-200">
              <div className="text-4xl font-black text-orange-500">{importResult.skipped}</div>
              <div className="text-sm font-bold text-slate-500 mt-2 uppercase tracking-wide">Bỏ qua (Trùng)</div>
            </div>
            <div className="text-center">
              <div className="text-4xl font-black text-red-500">{importResult.errors.length}</div>
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
          <KeywordImportDropzone onFileParsed={handleFileParsed} />
          
          <div className="mt-10 bg-blue-50/50 border border-blue-100 rounded-2xl p-6">
            <h3 className="font-bold text-blue-900 mb-3 flex items-center gap-2">
              <span className="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">i</span>
              Hướng dẫn định dạng file
            </h3>
            <ul className="list-disc pl-6 text-sm text-blue-800/80 space-y-2 font-medium">
              <li>Dòng 1 luôn luôn là <strong>Header</strong> (Tên cột).</li>
              <li>Cột A (Đầu tiên) phải chứa dữ liệu <strong>Từ khoá (Keyword)</strong>.</li>
              <li>Hỗ trợ thêm các cột v2: campaign_id, search_intent, target_word_count, internal_links, featured_image_url, image_urls.</li>
            </ul>
          </div>
        </div>
      ) : (
        <div className="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
          <div className="p-8 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 bg-slate-50/80">
            <div>
              <h2 className="text-xl font-extrabold text-slate-900">Xác nhận dữ liệu Preview</h2>
              <p className="text-slate-500 mt-1.5 font-medium">
                {previewMutation.isPending
                  ? 'Đang kiểm tra file bằng backend...'
                  : <>Tìm thấy <strong className="text-blue-600">{serverPreview?.valid_rows ?? parsedData.length}</strong> dòng hợp lệ.</>}
              </p>
            </div>
            <div className="flex gap-3 w-full sm:w-auto">
              <button 
                onClick={() => {
                  setSelectedFile(null);
                  setParsedData([]);
                }}
                className="flex-1 sm:flex-none px-6 py-3 bg-white border border-slate-300 rounded-xl font-bold text-slate-700 hover:bg-slate-50 transition-all active:scale-95"
                disabled={importMutation.isPending}
              >
                Hủy & Tải file khác
              </button>
              <button 
                onClick={handleConfirm}
                className="flex-1 sm:flex-none px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2 transition-all active:scale-95 shadow-md shadow-blue-600/20"
                disabled={importMutation.isPending || previewMutation.isPending || serverPreview?.quota_check.can_proceed === false}
              >
                {importMutation.isPending ? 'Đang Import...' : 'Xác nhận đẩy vào DB'}
              </button>
            </div>
          </div>

          {importError && (
            <div className="mx-8 mt-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 font-medium text-red-700">
              <AlertCircle className="h-5 w-5 flex-shrink-0" />
              {importError}
            </div>
          )}

          {serverPreview && (
            <div className="mx-8 mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div className="text-xs font-extrabold uppercase tracking-widest text-slate-400">Validation</div>
                <div className="mt-2 text-3xl font-black text-slate-900">{serverPreview.valid_rows}/{serverPreview.total_rows}</div>
                <div className="mt-1 text-sm font-semibold text-slate-500">{serverPreview.invalid_rows} dòng lỗi</div>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div className="text-xs font-extrabold uppercase tracking-widest text-slate-400">Estimated cost</div>
                <div className="mt-2 text-3xl font-black text-slate-900">${serverPreview.estimated_cost.total_estimated_usd.toFixed(4)}</div>
                <div className="mt-1 text-sm font-semibold text-slate-500">{serverPreview.estimated_cost.serper_calls} Serper calls</div>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div className="text-xs font-extrabold uppercase tracking-widest text-slate-400">Quota</div>
                <div className="mt-2 text-3xl font-black text-slate-900">
                  {serverPreview.quota_check.articles_remaining ?? '∞'}
                </div>
                <div className="mt-1 text-sm font-semibold text-slate-500">bài còn lại tháng này</div>
              </div>
            </div>
          )}

          {serverPreview && (serverPreview.errors.length > 0 || serverPreview.warnings.length > 0) && (
            <div className="mx-8 mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
              <div className="font-extrabold text-amber-900">Import report</div>
              <div className="mt-3 space-y-2 text-sm font-medium text-amber-800">
                {[...serverPreview.errors, ...serverPreview.warnings].slice(0, 8).map((item, index) => (
                  <div key={index}>Row {item.row}: {item.message}</div>
                ))}
              </div>
            </div>
          )}
          
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-white">
              <tr>
                <th className="px-8 py-4 text-left text-xs font-extrabold text-slate-400 uppercase tracking-widest w-24">STT</th>
                <th className="px-8 py-4 text-left text-xs font-extrabold text-slate-400 uppercase tracking-widest">Từ khoá nhận diện</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-slate-100">
              {(serverPreview?.sample_rows ?? parsedData).slice(0, 5).map((row, idx) => (
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
