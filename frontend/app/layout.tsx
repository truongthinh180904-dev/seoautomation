import type { Metadata } from "next";
import "./globals.css";
import Providers from "./providers";
import { Toaster } from "@/components/ui/sonner";

export const metadata: Metadata = {
  title: "AuraSEO - AI Content & SEO Suite",
  description: "Nền tảng Tự động hóa Nội dung & Chiến dịch SEO Thông minh",
  icons: {
    icon: "/logo.png",
    shortcut: "/logo.png",
    apple: "/logo.png",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="vi"
      className="h-full antialiased font-sans"
      suppressHydrationWarning
      style={
        {
          "--font-roboto": 'Roboto, -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif',
          "--font-geist-mono": 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
        } as React.CSSProperties
      }
    >
      <body className="min-h-full flex flex-col font-sans">
        <Providers>
          {children}
          <Toaster />
        </Providers>
      </body>
    </html>
  );
}
