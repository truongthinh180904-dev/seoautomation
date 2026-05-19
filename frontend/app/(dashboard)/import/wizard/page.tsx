"use client";

import KeywordImportPage from '../../keywords/import/page';

const steps = [
  'Upload Excel',
  'Map columns',
  'Validate rows',
  'Campaign planning',
  'Cost estimate',
  'Confirm import',
  'Start processing',
];

export default function ImportWizardPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Import Wizard</h1>
        <p className="mt-1 text-slate-500">Luồng 7 bước cho Excel campaign content plan v2.</p>
      </div>

      <div className="grid grid-cols-1 gap-2 md:grid-cols-7">
        {steps.map((step, index) => (
          <div key={step} className="rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm">
            <div className="mx-auto flex h-7 w-7 items-center justify-center rounded-full bg-blue-50 text-xs font-black text-blue-700">
              {index + 1}
            </div>
            <div className="mt-2 text-xs font-bold text-slate-600">{step}</div>
          </div>
        ))}
      </div>

      <KeywordImportPage />
    </div>
  );
}
