"use client";

import React, { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { UploadCloud, AlertCircle } from 'lucide-react';
import * as XLSX from 'xlsx';

interface KeywordImportDropzoneProps {
  onFileParsed: (file: File, data: KeywordImportPreviewRow[]) => void;
}

export default function KeywordImportDropzone({ onFileParsed }: KeywordImportDropzoneProps) {
  const [error, setError] = useState('');

  const onDrop = useCallback((acceptedFiles: File[]) => {
    setError('');
    const file = acceptedFiles[0];
    
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const data = new Uint8Array(e.target?.result as ArrayBuffer);
        const workbook = XLSX.read(data, { type: 'array' });
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        
        const rawJson = XLSX.utils.sheet_to_json(worksheet, { header: 1 }) as unknown[][];
        
        if (rawJson.length < 2) {
          setError('File Excel rỗng hoặc không đúng định dạng (cần Header ở dòng 1).');
          return;
        }

        const mappedData = rawJson
          .slice(1)
          .map((row) => ({
            keyword: String(row[0] ?? '').trim(),
          }))
          .filter((item) => item.keyword);

        onFileParsed(file, mappedData);
      } catch {
        setError('Lỗi khi đọc file Excel. Đảm bảo file không bị lỗi định dạng.');
      }
    };
    reader.readAsArrayBuffer(file);
  }, [onFileParsed]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({ 
    onDrop,
    accept: {
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
      'application/vnd.ms-excel': ['.xls'],
      'text/csv': ['.csv']
    },
    maxFiles: 1
  });

  return (
    <div className="w-full">
      <div 
        {...getRootProps()} 
        className={`border-2 border-dashed rounded-2xl p-12 text-center cursor-pointer transition-all duration-300
          ${isDragActive ? 'border-blue-500 bg-blue-50 scale-[1.02]' : 'border-slate-300 hover:border-blue-400 bg-slate-50/50 hover:bg-slate-50'}`}
      >
        <input {...getInputProps()} />
        <div className="flex flex-col items-center justify-center space-y-5">
          <div className={`p-5 rounded-full shadow-sm border transition-colors ${isDragActive ? 'bg-blue-100 border-blue-200' : 'bg-white border-slate-200'}`}>
            <UploadCloud className={`w-12 h-12 ${isDragActive ? 'text-blue-600' : 'text-slate-400'}`} />
          </div>
          <div>
            <p className="text-xl font-bold text-slate-800">
              {isDragActive ? 'Thả file Excel vào đây...' : 'Kéo thả file Excel hoặc click để chọn'}
            </p>
            <p className="text-slate-500 mt-2 font-medium">Hệ thống hỗ trợ .xlsx, .xls, .csv</p>
          </div>
        </div>
      </div>
      {error && (
        <div className="mt-6 flex items-center gap-3 text-red-700 bg-red-50 border border-red-200 p-4 rounded-xl font-medium animate-in fade-in">
          <AlertCircle className="w-5 h-5 flex-shrink-0" />
          {error}
        </div>
      )}
    </div>
  );
}
