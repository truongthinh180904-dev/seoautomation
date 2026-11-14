export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-sky-50/50 via-white to-sky-50/40 p-4">
      <div className="mb-6 flex flex-col items-center gap-2.5">
        <div className="relative flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-md">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/logo.png" alt="AuraSEO Logo" className="h-full w-full object-contain p-1" />
        </div>
        <div className="text-center">
          <h1 className="text-xl font-black text-slate-900 leading-none">
            Aura<span className="text-sky-600">SEO</span>
          </h1>
          <p className="text-xs text-sky-600/80 font-semibold tracking-wide mt-1">
            AI Content & SEO Suite
          </p>
        </div>
      </div>
      <div className="w-full max-w-sm">{children}</div>
    </div>
  );
}
