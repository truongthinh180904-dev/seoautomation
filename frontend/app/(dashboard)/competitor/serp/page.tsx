"use client";

import React, { Suspense, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import {
  Search,
  Sparkles,
  BarChart3,
  HelpCircle,
  Link2,
  FileCheck,
  Coins,
  Loader2,
  ShieldCheck,
  CheckCircle2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';
import {
  SerpBenchmarkTable,
  type BenchmarkSummary,
  type CompetitorItem,
} from '@/components/features/competitor/SerpBenchmarkTable';

interface SerpAnalysisApiResponse {
  success: boolean;
  message: string;
  data: {
    keyword: string;
    language: string;
    country: string;
    credits_deducted: number;
    total_competitors: number;
    benchmark: BenchmarkSummary;
    competitors: CompetitorItem[];
    related_searches: string[];
    people_also_ask: Array<{
      question: string;
      snippet: string;
    }>;
  };
}

function CompetitorSerpContent() {
  const searchParams = useSearchParams();
  const initialKeyword = searchParams.get('q') || searchParams.get('keyword') || '';

  const [keyword, setKeyword] = useState<string>(initialKeyword);
  const [language, setLanguage] = useState<'vi' | 'en'>('vi');
  const [isAnalyzing, setIsAnalyzing] = useState<boolean>(false);
  const [analysisData, setAnalysisData] = useState<SerpAnalysisApiResponse['data'] | null>(null);

  const handleAnalyze = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = keyword.trim();
    if (!trimmed) {
      toast.error('Vui lòng nhập từ khóa cần phân tích SERP đối thủ.');
      return;
    }

    setIsAnalyzing(true);
    try {
      const response = await apiClient.get<SerpAnalysisApiResponse>('/competitor/serp-analysis', {
        params: {
          keyword: trimmed,
          language,
          country: language === 'vi' ? 'vn' : 'us',
          limit: 10,
        },
      });

      if (response.data?.success && response.data?.data) {
        setAnalysisData(response.data.data);
        toast.success(
          `Đã phân tích xong Top 10 đối thủ cho "${trimmed}" (-${response.data.data.credits_deducted} credits)`
        );
      } else {
        toast.error('Không nhận được dữ liệu phân tích từ máy chủ.');
      }
    } catch (err: unknown) {
      const errorObj = err as { response?: { status?: number; data?: { message?: string } }; message?: string };
      if (errorObj?.response?.status === 402) {
        toast.error('Không đủ credit! Vui lòng nạp thêm credit để phân tích sâu đối thủ.');
      } else {
        const msg = errorObj?.response?.data?.message || errorObj?.message || 'Có lỗi xảy ra khi phân tích SERP đối thủ.';
        toast.error(msg);
      }
    } finally {
      setIsAnalyzing(false);
    }
  };

  return (
    <div className="space-y-6 p-4 sm:p-6 lg:p-8">
      {/* Header & Page Title */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center space-x-2">
            <span>Phân tích SERP & Đối thủ Top 10</span>
            <Badge variant="outline" className="border-indigo-200 bg-indigo-50 text-indigo-700 text-xs font-semibold">
              <Sparkles className="mr-1 h-3 w-3" /> P2.3 Engine
            </Badge>
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Bóc tách cấu trúc Heading H1–H4, Schema Markup, số từ và mật độ từ khóa của Top 10 Google.
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <Badge variant="secondary" className="bg-amber-50 text-amber-800 border-amber-200 text-xs px-2.5 py-1">
            <Coins className="mr-1.5 h-3.5 w-3.5 text-amber-600 inline" />
            Chi phí: 3 Credits / lượt phân tích sâu
          </Badge>
        </div>
      </div>

      {/* Search Input Card */}
      <Card className="border-slate-200 bg-white shadow-xs">
        <CardContent className="p-4 sm:p-6">
          <form onSubmit={handleAnalyze} className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                type="text"
                placeholder="Nhập từ khóa mục tiêu (ví dụ: tai nghe bluetooth pin trâu)..."
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                className="pl-9 text-sm h-10"
                disabled={isAnalyzing}
              />
            </div>

            <div className="flex items-center space-x-2">
              <select
                value={language}
                onChange={(e) => setLanguage(e.target.value as 'vi' | 'en')}
                disabled={isAnalyzing}
                aria-label="Chọn ngôn ngữ tìm kiếm"
                className="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              >
                <option value="vi">Tiếng Việt (Google VN)</option>
                <option value="en">English (Google US)</option>
              </select>

              <Button
                type="submit"
                disabled={isAnalyzing || !keyword.trim()}
                className="h-10 px-5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs"
              >
                {isAnalyzing ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Đang bóc tách Top 10...
                  </>
                ) : (
                  <>
                    <Sparkles className="mr-2 h-4 w-4" />
                    Phân tích Đối thủ
                  </>
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      {/* Results View with Tabs */}
      {analysisData ? (
        <Tabs defaultValue="matrix" className="space-y-4">
          <div className="flex items-center justify-between border-b border-slate-200 pb-2">
            <TabsList className="bg-slate-100 p-1">
              <TabsTrigger value="matrix" className="text-xs">
                <BarChart3 className="mr-1.5 h-3.5 w-3.5" />
                Bảng Benchmark Top 10
              </TabsTrigger>
              <TabsTrigger value="recommendations" className="text-xs">
                <FileCheck className="mr-1.5 h-3.5 w-3.5" />
                Khuyến nghị SEO Vượt Trội
              </TabsTrigger>
              <TabsTrigger value="serp_queries" className="text-xs">
                <HelpCircle className="mr-1.5 h-3.5 w-3.5" />
                Truy vấn liên quan & FAQs
              </TabsTrigger>
            </TabsList>
            <span className="text-xs text-slate-500 font-medium">
              Từ khóa: <strong>{analysisData.keyword}</strong> ({analysisData.total_competitors} đối thủ)
            </span>
          </div>

          {/* Tab 1: Benchmark Matrix */}
          <TabsContent value="matrix" className="space-y-4 pt-1">
            <SerpBenchmarkTable
              benchmark={analysisData.benchmark}
              competitors={analysisData.competitors}
              isLoading={isAnalyzing}
            />
          </TabsContent>

          {/* Tab 2: Actionable SEO Recommendations */}
          <TabsContent value="recommendations" className="space-y-4 pt-1">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              {/* Length & Headings Advice */}
              <Card className="border-slate-200 bg-white shadow-xs">
                <CardHeader>
                  <CardTitle className="text-sm font-bold flex items-center space-x-2 text-slate-900">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                    <span>Chiến lược độ dài & Bố cục bài viết</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-xs text-slate-700">
                  <div className="rounded-lg bg-emerald-50/60 p-3 border border-emerald-100">
                    <p className="font-semibold text-emerald-900">Mục tiêu độ dài:</p>
                    <p className="mt-1 text-slate-600">
                      Bài viết nên đạt ít nhất{' '}
                      <strong className="text-emerald-700">
                        {analysisData.benchmark.recommended_word_count.toLocaleString()} từ
                      </strong>{' '}
                      để vượt qua độ dài trung bình ({analysisData.benchmark.avg_word_count.toLocaleString()} từ) của đối thủ.
                    </p>
                  </div>
                  <div className="rounded-lg bg-indigo-50/60 p-3 border border-indigo-100">
                    <p className="font-semibold text-indigo-900">Cấu trúc thẻ Heading đề xuất:</p>
                    <p className="mt-1 text-slate-600">
                      Thiết kế tối thiểu <strong>{analysisData.benchmark.recommended_h2_count} mục H2</strong> và{' '}
                      <strong>{Math.max(3, Math.round(analysisData.benchmark.avg_h3_count))} mục H3</strong> để bao quát mọi khía cạnh nội dung.
                    </p>
                  </div>
                  <div className="rounded-lg bg-slate-50 p-3 border border-slate-200">
                    <p className="font-semibold text-slate-800">Mật độ từ khóa chuẩn:</p>
                    <p className="mt-1 text-slate-600">
                      Duy trì mật độ từ khóa chính ở mức <strong>1.2% – 1.8%</strong>, tránh nhồi nhét vượt ngưỡng 2.5%.
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Schema & Entity Advice */}
              <Card className="border-slate-200 bg-white shadow-xs">
                <CardHeader>
                  <CardTitle className="text-sm font-bold flex items-center space-x-2 text-slate-900">
                    <ShieldCheck className="h-4 w-4 text-blue-600" />
                    <span>Chiến lược Schema Markup & Thực thể LSI</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-xs text-slate-700">
                  <div>
                    <p className="font-semibold text-slate-800 mb-1.5">Các Schema phổ biến đối thủ đang dùng:</p>
                    <div className="flex flex-wrap gap-2">
                      {analysisData.benchmark.common_schemas.map((s) => (
                        <Badge
                          key={s.name}
                          variant="outline"
                          className="bg-blue-50 border-blue-200 text-blue-800 text-xs px-2 py-0.5"
                        >
                          {s.name} ({s.percentage}% đối thủ)
                        </Badge>
                      ))}
                    </div>
                  </div>

                  <div className="pt-2 border-t border-slate-100">
                    <p className="font-semibold text-slate-800 mb-1.5">Thực thể ngữ nghĩa LSI cần bổ sung vào bài:</p>
                    <div className="flex flex-wrap gap-1.5">
                      {analysisData.benchmark.common_lsi_keywords.map((lsi) => (
                        <span
                          key={lsi}
                          className="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700 font-medium"
                        >
                          {lsi}
                        </span>
                      ))}
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          {/* Tab 3: Related Searches & People Also Ask */}
          <TabsContent value="serp_queries" className="space-y-4 pt-1">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              {/* Related Searches */}
              <Card className="border-slate-200 bg-white shadow-xs">
                <CardHeader>
                  <CardTitle className="text-sm font-bold flex items-center space-x-2 text-slate-900">
                    <Link2 className="h-4 w-4 text-blue-600" />
                    <span>Tìm kiếm liên quan từ Google (Related Searches)</span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-2 text-xs">
                    {analysisData.related_searches.map((query, idx) => (
                      <li
                        key={`${query}-${idx}`}
                        className="flex items-center space-x-2 rounded-md bg-slate-50 p-2 text-slate-700 hover:bg-slate-100 transition-colors"
                      >
                        <Search className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                        <span className="font-medium">{query}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>

              {/* People Also Ask */}
              <Card className="border-slate-200 bg-white shadow-xs">
                <CardHeader>
                  <CardTitle className="text-sm font-bold flex items-center space-x-2 text-slate-900">
                    <HelpCircle className="h-4 w-4 text-amber-500" />
                    <span>Câu hỏi thường gặp từ người dùng (People Also Ask)</span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-2.5 text-xs">
                    {analysisData.people_also_ask.map((item, idx) => (
                      <div key={`${item.question}-${idx}`} className="rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                        <p className="font-semibold text-slate-800">{item.question}</p>
                        <p className="mt-1 text-slate-600 leading-relaxed">{item.snippet}</p>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      ) : (
        <SerpBenchmarkTable
          benchmark={{
            total_analyzed: 0,
            avg_word_count: 0,
            min_word_count: 0,
            max_word_count: 0,
            avg_h2_count: 0,
            avg_h3_count: 0,
            avg_h4_count: 0,
            avg_headings_count: 0,
            avg_image_count: 0,
            avg_keyword_density: 0,
            recommended_word_count: 0,
            recommended_h2_count: 0,
            recommended_image_count: 0,
            common_schemas: [],
            common_lsi_keywords: [],
          }}
          competitors={[]}
          isLoading={isAnalyzing}
        />
      )}
    </div>
  );
}

export default function CompetitorSerpPage() {
  return (
    <Suspense
      fallback={
        <div className="flex h-64 items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
        </div>
      }
    >
      <CompetitorSerpContent />
    </Suspense>
  );
}
