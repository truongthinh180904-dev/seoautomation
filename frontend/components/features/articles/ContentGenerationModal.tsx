"use client";

import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Sparkles,
  Building2,
  Target,
  Sliders,
  Link2,
  Loader2,
  CheckCircle2,
} from 'lucide-react';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';
import { useBrandKnowledgeList } from '@/hooks/useBrandKnowledge';

export const CONTENT_TYPE_OPTIONS = [
  { value: 'auto', label: '✨ AI Tự động đề xuất theo Từ khóa & Intent' },
  { value: 'pillar_page', label: 'Pillar Page (Trang trụ cột chuyên sâu)' },
  { value: 'topic_cluster', label: 'Topic Cluster (Bài viết vệ tinh chuyên đề)' },
  { value: 'informational_article', label: 'Informational Article (Bài viết kiến thức)' },
  { value: 'commercial_article', label: 'Commercial Article (Bài viết tư vấn chọn mua/dịch vụ)' },
  { value: 'transactional_article', label: 'Transactional Article (Bài viết chuyển đổi/Báo giá)' },
  { value: 'service_landing_page', label: 'Service Landing Page (Trang đích dịch vụ)' },
  { value: 'product_review', label: 'Product Review (Đánh giá sản phẩm)' },
  { value: 'product_description', label: 'Product Description (Mô tả sản phẩm)' },
  { value: 'comparison_article', label: 'Comparison Article (Bài viết so sánh)' },
  { value: 'how_to_guide', label: 'How-to Guide (Hướng dẫn từng bước)' },
  { value: 'local_seo_article', label: 'Local SEO Article (Bài viết SEO địa phương)' },
  { value: 'news_industry_update', label: 'News / Industry Update (Bản tin cập nhật ngành)' },
  { value: 'case_study', label: 'Case Study (Phân tích tình huống thực tế)' },
  { value: 'faq_article', label: 'FAQ Article (Bài viết giải đáp A-Z)' },
];

export const SEARCH_INTENT_OPTIONS = [
  { value: 'auto', label: '✨ AI Tự động phân tích Primary & Secondary Intents' },
  { value: 'informational', label: 'Informational (Tìm hiểu thông tin, kiến thức)' },
  { value: 'commercial_investigation', label: 'Commercial Investigation (So sánh, đánh giá trước khi mua)' },
  { value: 'transactional', label: 'Transactional (Sẵn sàng mua hàng, đặt lịch, nhận báo giá)' },
  { value: 'local_intent', label: 'Local Intent (Tìm kiếm dịch vụ theo khu vực địa lý)' },
  { value: 'navigational', label: 'Navigational (Tìm kiếm thương hiệu/trang cụ thể)' },
];

export const DESIRED_LENGTH_OPTIONS = [
  { value: 'short', label: 'Bài ngắn (800–1200 từ) - Tin ngắn, FAQ, giới thiệu cơ bản' },
  { value: 'standard', label: 'Bài tiêu chuẩn (1200–1800 từ) - Blog SEO, tư vấn dịch vụ' },
  { value: 'comprehensive', label: 'Bài chuyên sâu (1800–2500 từ) - So sánh, hướng dẫn chi tiết' },
  { value: 'advanced', label: 'Bài chuyên khảo / Pillar (2500–3500+ từ) - Toàn diện, cạnh tranh cao' },
];

interface ContentGenerationModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  initialKeywordId?: number | null;
  initialPrimaryKeyword?: string;
  onSuccess?: (articleId?: number) => void;
}

export function ContentGenerationModal({
  open,
  onOpenChange,
  initialKeywordId = null,
  initialPrimaryKeyword = '',
  onSuccess,
}: ContentGenerationModalProps) {
  const { data: brandProfiles = [] } = useBrandKnowledgeList();
  const [activeTab, setActiveTab] = useState<'required' | 'strategy' | 'links'>('required');
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);

  // Form states (Section 4)
  const [brandKnowledgeId, setBrandKnowledgeId] = useState<number | ''>('');
  const [primaryKeyword, setPrimaryKeyword] = useState<string>(initialPrimaryKeyword);
  const [topicDescription, setTopicDescription] = useState<string>('');
  const [contentLanguage, setContentLanguage] = useState<string>('vi');

  // Strategy & Content Type (4.1 & 4.2)
  const [contentType, setContentType] = useState<string>('auto');
  const [searchIntent, setSearchIntent] = useState<string>('auto');
  const [desiredLength, setDesiredLength] = useState<string>('standard');
  const [secondaryKeywords, setSecondaryKeywords] = useState<string>('');
  const [relatedKeywords, setRelatedKeywords] = useState<string>('');
  const [localSeoLocation, setLocalSeoLocation] = useState<string>('');
  const [targetProductService, setTargetProductService] = useState<string>('');
  const [targetAudience, setTargetAudience] = useState<string>('');
  const [articleGoal, setArticleGoal] = useState<string>('');
  const [writingStyle, setWritingStyle] = useState<string>('');
  const [desiredCta, setDesiredCta] = useState<string>('');

  // Links, Media & Special Requirements
  const [referenceUrls, setReferenceUrls] = useState<string>('');
  const [internalLinkUrls, setInternalLinkUrls] = useState<string>('');
  const [competitorUrls, setCompetitorUrls] = useState<string>('');
  const [desiredImageCount, setDesiredImageCount] = useState<number>(3);
  const [specialRequirements, setSpecialRequirements] = useState<string>('');

  useEffect(() => {
    if (initialPrimaryKeyword) {
      setPrimaryKeyword(initialPrimaryKeyword);
    }
  }, [initialPrimaryKeyword]);

  useEffect(() => {
    if (brandProfiles.length > 0 && brandKnowledgeId === '') {
      const def = brandProfiles.find((p) => p.is_default) || brandProfiles[0];
      setBrandKnowledgeId(def.id);
    }
  }, [brandProfiles, brandKnowledgeId]);

  useEffect(() => {
    const recommendedByTier: Record<string, number> = {
      short: 2,
      standard: 3,
      comprehensive: 5,
      advanced: 6,
    };
    if (recommendedByTier[desiredLength]) {
      setDesiredImageCount(recommendedByTier[desiredLength]);
    }
  }, [desiredLength]);

  const selectedBrand = brandProfiles.find((b) => b.id === Number(brandKnowledgeId));

  const handleSubmit = async () => {
    if (!primaryKeyword.trim()) {
      toast.error('Vui lòng nhập Từ khóa chính (Primary Keyword).');
      setActiveTab('required');
      return;
    }
    if (!topicDescription.trim()) {
      toast.error('Vui lòng nhập Chủ đề hoặc mô tả nội dung muốn viết.');
      setActiveTab('required');
      return;
    }

    setIsSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        keyword_id: initialKeywordId || undefined,
        brand_knowledge_id: brandKnowledgeId ? Number(brandKnowledgeId) : undefined,
        website_id: selectedBrand?.website_id || undefined,
        primary_keyword: primaryKeyword.trim(),
        topic_description: topicDescription.trim(),
        content_language: contentLanguage,
        content_type: contentType,
        search_intent: searchIntent === 'auto' ? undefined : searchIntent,
        desired_length: desiredLength,
        secondary_keywords: secondaryKeywords.trim() || undefined,
        related_keywords: relatedKeywords.trim() || undefined,
        local_seo_location: localSeoLocation.trim() || undefined,
        target_product_service: targetProductService.trim() || undefined,
        target_audience: targetAudience.trim() || undefined,
        article_goal: articleGoal.trim() || undefined,
        writing_style: writingStyle.trim() || undefined,
        desired_cta: desiredCta.trim() || undefined,
        reference_urls: referenceUrls.trim() || undefined,
        internal_link_urls: internalLinkUrls.trim() || undefined,
        competitor_urls: competitorUrls.trim() || undefined,
        desired_image_count: desiredImageCount,
        special_requirements: specialRequirements.trim() || undefined,
      };

      const res = await apiClient.post('/articles/generate', payload);
      const createdArticleId = res.data?.article?.id || res.data?.data?.id;
      toast.success('Đã kích hoạt AI Content Strategy Engine (Gemini Pro 3.1 Thinking)!');
      onOpenChange(false);
      if (onSuccess) {
        onSuccess(createdArticleId);
      }
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        (err instanceof Error ? err.message : 'Không thể khởi tạo bài viết AI');
      toast.error(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] overflow-y-auto max-w-3xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-lg">
            <Sparkles className="h-5 w-5 text-primary" />
            Tạo Bài Viết SEO Chuyên Sâu (Universal AI Content Engine)
          </DialogTitle>
          <DialogDescription>
            Hệ thống sử dụng <strong>Gemini Pro 3.1 Thinking</strong> thực hiện 5 bước phân tích chiến lược, lập dàn ý, viết bài, tạo ảnh và tự kiểm duyệt chất lượng qua 4 vòng.
          </DialogDescription>
        </DialogHeader>

        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'required' | 'strategy' | 'links')}>
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="required" className="gap-1.5 text-xs">
              <Building2 className="h-3.5 w-3.5" />
              1. Thông tin Bắt buộc
            </TabsTrigger>
            <TabsTrigger value="strategy" className="gap-1.5 text-xs">
              <Target className="h-3.5 w-3.5" />
              2. Chiến lược &amp; Chuyển đổi
            </TabsTrigger>
            <TabsTrigger value="links" className="gap-1.5 text-xs">
              <Link2 className="h-3.5 w-3.5" />
              3. Liên kết, Ảnh &amp; Yêu cầu
            </TabsTrigger>
          </TabsList>

          {/* TAB 1: TRƯỜNG BẮT BUỘC & PHÂN LOẠI CỐT LÕI */}
          <TabsContent value="required" className="mt-4 space-y-4">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">
                1. Chọn Hồ sơ Doanh nghiệp đã khai báo <span className="text-destructive">*</span>
              </label>
              <select
                value={brandKnowledgeId}
                onChange={(e) => setBrandKnowledgeId(e.target.value ? Number(e.target.value) : '')}
                className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
              >
                {brandProfiles.length === 0 ? (
                  <option value="">-- Mặc định theo Website hiện tại --</option>
                ) : (
                  brandProfiles.map((bp) => (
                    <option key={bp.id} value={bp.id}>
                      {bp.brand_name} ({bp.industry_profile?.name || bp.industry_key || 'Đa ngành'}) -{' '}
                      {bp.business_model || 'B2C'} {bp.is_default ? '★ Mặc định' : ''}
                    </option>
                  ))
                )}
              </select>
              {selectedBrand && (
                <div className="flex flex-wrap items-center gap-2 pt-1 text-xs text-muted-foreground">
                  <Badge variant="secondary" className="text-[11px]">
                    Ngành: {selectedBrand.industry_profile?.name || selectedBrand.industry_key || 'Đa ngành'}
                  </Badge>
                  <Badge variant="outline" className="text-[11px]">
                    YMYL: {(selectedBrand.industry_profile?.ymyl_level || 'low').toUpperCase()}
                  </Badge>
                  <Badge variant="outline" className="text-[11px]">
                    Giọng văn: {selectedBrand.tone_of_voice}
                  </Badge>
                </div>
              )}
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              <div className="space-y-1.5 sm:col-span-2">
                <label className="text-sm font-medium">
                  2. Từ khóa chính (Primary Keyword) <span className="text-destructive">*</span>
                </label>
                <Input
                  placeholder="Ví dụ: trồng răng implant giá bao nhiêu, phần mềm quản lý nhân sự..."
                  value={primaryKeyword}
                  onChange={(e) => setPrimaryKeyword(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">
                  4. Ngôn ngữ bài viết <span className="text-destructive">*</span>
                </label>
                <select
                  value={contentLanguage}
                  onChange={(e) => setContentLanguage(e.target.value)}
                  className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                >
                  <option value="vi">Tiếng Việt (vi)</option>
                  <option value="en">Tiếng Anh (en)</option>
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-medium">
                3. Chủ đề hoặc mô tả nội dung muốn viết <span className="text-destructive">*</span>
              </label>
              <Textarea
                rows={3}
                placeholder="Mô tả trọng tâm bài viết cần giải quyết, góc tiếp cận mong muốn hoặc các ý chính cần làm rõ cho người đọc..."
                value={topicDescription}
                onChange={(e) => setTopicDescription(e.target.value)}
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">4.1. Loại bài viết (Content Type)</label>
                <select
                  value={contentType}
                  onChange={(e) => setContentType(e.target.value)}
                  className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                >
                  {CONTENT_TYPE_OPTIONS.map((ct) => (
                    <option key={ct.value} value={ct.value}>
                      {ct.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">4.2. Ý định tìm kiếm (Search Intent)</label>
                <select
                  value={searchIntent}
                  onChange={(e) => setSearchIntent(e.target.value)}
                  className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                >
                  {SEARCH_INTENT_OPTIONS.map((si) => (
                    <option key={si.value} value={si.value}>
                      {si.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-medium">Độ dài bài viết mong muốn (Section 6.2)</label>
              <select
                value={desiredLength}
                onChange={(e) => setDesiredLength(e.target.value)}
                className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
              >
                {DESIRED_LENGTH_OPTIONS.map((dl) => (
                  <option key={dl.value} value={dl.value}>
                    {dl.label}
                  </option>
                ))}
              </select>
            </div>
          </TabsContent>

          {/* TAB 2: TỪ KHÓA PHỤ, ĐỐI TƯỢNG & MỤC TIÊU CHUYỂN ĐỔI */}
          <TabsContent value="strategy" className="mt-4 space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Từ khóa phụ (Secondary Keywords)</label>
                <Input
                  placeholder="Cách nhau bởi dấu phẩy..."
                  value={secondaryKeywords}
                  onChange={(e) => setSecondaryKeywords(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">Từ khóa liên quan / Semantic Keywords</label>
                <Input
                  placeholder="Các khái niệm, thực thể liên quan..."
                  value={relatedKeywords}
                  onChange={(e) => setRelatedKeywords(e.target.value)}
                />
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Địa điểm SEO Local (nếu có)</label>
                <Input
                  placeholder="Ví dụ: Quận 1 TP.HCM, Hà Nội, Đà Nẵng..."
                  value={localSeoLocation}
                  onChange={(e) => setLocalSeoLocation(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">Sản phẩm / Dịch vụ cần quảng bá trong bài</label>
                <Input
                  placeholder="Tên gói dịch vụ hoặc sản phẩm cụ thể..."
                  value={targetProductService}
                  onChange={(e) => setTargetProductService(e.target.value)}
                />
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Đối tượng khách hàng mục tiêu của bài viết</label>
                <Input
                  placeholder="Ví dụ: Chủ doanh nghiệp SME, người mất răng lâu năm..."
                  value={targetAudience}
                  onChange={(e) => setTargetAudience(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">Mục tiêu bài viết (Article Goal)</label>
                <Input
                  placeholder="Ví dụ: Thu hút organic traffic & tăng đăng ký tư vấn"
                  value={articleGoal}
                  onChange={(e) => setArticleGoal(e.target.value)}
                />
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Phong cách viết (Ghi đè tùy chọn)</label>
                <Input
                  placeholder="Để trống để dùng giọng văn của Hồ sơ Doanh nghiệp"
                  value={writingStyle}
                  onChange={(e) => setWritingStyle(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">CTA mong muốn cuối bài</label>
                <Input
                  placeholder="Ví dụ: Đặt lịch tư vấn miễn phí cùng chuyên gia"
                  value={desiredCta}
                  onChange={(e) => setDesiredCta(e.target.value)}
                />
              </div>
            </div>
          </TabsContent>

          {/* TAB 3: LIÊN KẾT, TÀI LIỆU THAM KHẢO, HÌNH ẢNH & YÊU CẦU ĐẶC BIỆT */}
          <TabsContent value="links" className="mt-4 space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">URL bài viết nội bộ cần liên kết (Internal Links)</label>
                <Textarea
                  rows={3}
                  placeholder={"Mỗi dòng 1 link hoặc cú pháp Anchor | URL:\nBảng giá dịch vụ | https://domain.com/bang-gia"}
                  value={internalLinkUrls}
                  onChange={(e) => setInternalLinkUrls(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">URL hoặc tài liệu tham khảo chính thống (Tier B)</label>
                <Textarea
                  rows={3}
                  placeholder={"Mỗi dòng 1 nguồn tài liệu / URL tham khảo đáng tin cậy..."}
                  value={referenceUrls}
                  onChange={(e) => setReferenceUrls(e.target.value)}
                />
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Đối thủ hoặc Website tham khảo dàn ý</label>
                <Input
                  placeholder="URL bài viết đối thủ (cách nhau bởi dấu phẩy)..."
                  value={competitorUrls}
                  onChange={(e) => setCompetitorUrls(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">Số lượng hình ảnh minh họa trong bài (1–10)</label>
                <Input
                  type="number"
                  min={1}
                  max={10}
                  value={desiredImageCount}
                  onChange={(e) => setDesiredImageCount(Math.max(1, Math.min(10, Number(e.target.value) || 3)))}
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-medium">Yêu cầu đặc biệt cho AI (Special Instructions)</label>
              <Textarea
                rows={3}
                placeholder="Ví dụ: Có bảng so sánh chi tiết 3 phương án; nhấn mạnh quy trình bảo mật dữ liệu ISO 27001; không đề cập tên đối thủ cụ thể..."
                value={specialRequirements}
                onChange={(e) => setSpecialRequirements(e.target.value)}
              />
            </div>

            <div className="rounded-lg border bg-emerald-50/60 p-3 text-xs text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200 flex items-start gap-2">
              <CheckCircle2 className="h-4 w-4 text-emerald-600 mt-0.5 flex-shrink-0" />
              <div>
                <strong>Quy trình 6 giai đoạn tự động:</strong> Phân tích 5 bước chiến lược &rarr; Dựng dàn ý chuyên sâu &rarr; Viết nội dung chuẩn E-E-A-T &amp; On-page SEO &rarr; Kiểm duyệt 4 vai trò (SEO, Biên tập, Fact-check, Kỹ thuật) &rarr; Lập kế hoạch &amp; sinh ảnh AI/Stock chuẩn ALT SEO &rarr; Chấm điểm chất lượng 100 điểm.
              </div>
            </div>
          </TabsContent>
        </Tabs>

        <DialogFooter className="gap-2 pt-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={isSubmitting}
          >
            Hủy
          </Button>
          <Button type="button" onClick={handleSubmit} disabled={isSubmitting} className="gap-2">
            {isSubmitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sliders className="h-4 w-4" />}
            Khởi tạo Chiến lược &amp; Viết bài ngay
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
