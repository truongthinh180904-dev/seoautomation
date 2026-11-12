"use client";

import React, { useEffect, useState, useTransition } from 'react';
import {
  BookOpen,
  Sparkles,
  Plus,
  Search,
  CheckCircle2,
  Trash2,
  Edit3,
  ShieldAlert,
  ShieldCheck,
  Star,
  ExternalLink,
  Loader2,
  Copy,
  Check,
  Globe,
  AlertTriangle,
  Package,
  Building2,
  Award,
  Compass,
  Briefcase,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';
import apiClient from '@/lib/api/client';
import {
  BrandKnowledgeBase,
  BrandKnowledgePayload,
  BusinessProfileInfo,
  EeatCredentialsInfo,
  BrandOrientationInfo,
  ProductCatalogItem,
  useBrandKnowledgeList,
  useBrandKnowledgeActions,
  useIndustryProfiles,
  useDetectIndustry,
  useInjectBrandContext,
  useValidateTabooWords,
} from '@/hooks/useBrandKnowledge';

interface WebsiteOption {
  id: number;
  name: string;
  domain: string;
  url?: string;
}

const TONE_OPTIONS = [
  { value: 'professional', label: 'Chuyên nghiệp (Professional)' },
  { value: 'conversational', label: 'Gần gũi, Trò chuyện (Conversational)' },
  { value: 'authoritative', label: 'Chuyên gia uy tín (Authoritative)' },
  { value: 'friendly', label: 'Thân thiện, Nhiệt tình (Friendly)' },
  { value: 'inspiring', label: 'Truyền cảm hứng (Inspiring)' },
  { value: 'technical', label: 'Kỹ thuật chuyên sâu (Technical)' },
  { value: 'luxury', label: 'Cao cấp, Sang trọng (Luxury)' },
];

const BUSINESS_MODEL_OPTIONS = [
  { value: 'B2C', label: 'B2C (Khách hàng cá nhân)' },
  { value: 'B2B', label: 'B2B (Khách hàng doanh nghiệp)' },
  { value: 'B2B2C', label: 'B2B2C (Kết hợp đối tác & người tiêu dùng)' },
  { value: 'Other', label: 'Khác (Tổ chức / D2C / Agency)' },
];

export default function BrandKnowledgePage() {
  const [websites, setWebsites] = useState<WebsiteOption[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [toneFilter, setToneFilter] = useState<string>('all');
  const [activeTab, setActiveTab] = useState<'profiles' | 'tester'>('profiles');
  const [dialogTab, setDialogTab] = useState<'basic' | 'operations' | 'eeat' | 'brand'>('basic');

  // Dialog & Modal States
  const [isDialogOpen, setIsDialogOpen] = useState<boolean>(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [isCopied, setIsCopied] = useState<boolean>(false);

  // Form State (3.1 - 3.5)
  const [formData, setFormData] = useState<{
    brand_name: string;
    tagline: string;
    tone_of_voice: string;
    industry_key: string;
    business_model: string;
    target_personas: string[];
    unique_selling_points: string[];
    product_catalog: ProductCatalogItem[];
    taboo_words: string[];
    preferred_terms: string[];
    custom_rules: string;
    business_profile: BusinessProfileInfo;
    eeat_credentials: EeatCredentialsInfo;
    brand_orientation: BrandOrientationInfo;
    is_default: boolean;
  }>({
    brand_name: '',
    tagline: '',
    tone_of_voice: 'professional',
    industry_key: '',
    business_model: 'B2C',
    target_personas: [],
    unique_selling_points: [],
    product_catalog: [],
    taboo_words: [],
    preferred_terms: [],
    custom_rules: '',
    business_profile: {
      company_name: '',
      brand_name: '',
      website_url: '',
      primary_industry: '',
      secondary_industry: '',
      country_market: 'Việt Nam',
      service_area: 'Toàn quốc',
      content_language: 'vi',
      business_model: 'B2C',
      company_intro: '',
      company_history: '',
      customer_locations: '',
      problems_solved: '',
      competitive_differentiation: '',
      sales_policy: '',
      warranty_policy: '',
      contact_info: '',
    },
    eeat_credentials: {
      expert_team: [],
      verified_qualifications: [],
      operating_license: '',
      verified_achievements: [],
      partners_certifications: [],
      reference_sources: [],
      content_reviewer: '',
    },
    brand_orientation: {
      writing_style: 'Chuyên nghiệp, rõ ràng, dễ hiểu',
      brand_voice: 'professional',
      pronouns_addressing: 'Chúng tôi / Quý khách (hoặc Bạn)',
      brand_positioning: '',
      priority_cta: '',
    },
    is_default: true,
  });

  // Tag Inputs
  const [personaInput, setPersonaInput] = useState<string>('');
  const [uspInput, setUspInput] = useState<string>('');
  const [tabooInput, setTabooInput] = useState<string>('');
  const [preferredInput, setPreferredInput] = useState<string>('');

  // E-E-A-T Inputs (newline separated for convenience in form)
  const [expertTeamText, setExpertTeamText] = useState<string>('');
  const [qualificationsText, setQualificationsText] = useState<string>('');
  const [achievementsText, setAchievementsText] = useState<string>('');
  const [partnersText, setPartnersText] = useState<string>('');
  const [referencesText, setReferencesText] = useState<string>('');

  // Product Inputs
  const [newProdName, setNewProdName] = useState<string>('');
  const [newProdDesc, setNewProdDesc] = useState<string>('');
  const [newProdUrl, setNewProdUrl] = useState<string>('');

  // Tester State
  const [testContent, setTestContent] = useState<string>('');
  const [testPrompt, setTestPrompt] = useState<string>(
    "Viết bài giới thiệu về dịch vụ của chúng tôi.\n{{brand_context}}\nYêu cầu viết tối thiểu 1500 chữ có cấu trúc H2, H3 rõ ràng."
  );
  const [previewContext, setPreviewContext] = useState<string>('');
  const [tabooViolations, setTabooViolations] = useState<Record<string, number> | null>(null);

  const [isSubmitting, startTransition] = useTransition();

  // Queries & Mutations
  const { data: brandProfiles = [], isLoading, refetch } = useBrandKnowledgeList(
    selectedWebsiteId || undefined,
    {
      search: searchQuery || undefined,
      tone_of_voice: toneFilter === 'all' ? undefined : toneFilter,
    }
  );
  const { data: industryProfiles = [] } = useIndustryProfiles();
  const detectIndustryMutation = useDetectIndustry();

  const { create, update, remove, setDefault } = useBrandKnowledgeActions();
  const injectMutation = useInjectBrandContext();
  const validateTabooMutation = useValidateTabooWords();

  useEffect(() => {
    fetchWebsites();
  }, []);

  const fetchWebsites = async () => {
    try {
      const res = await apiClient.get<{ success: boolean; data: WebsiteOption[] }>('/websites');
      if (res.data?.data && res.data.data.length > 0) {
        setWebsites(res.data.data);
        setSelectedWebsiteId(res.data.data[0].id);
      }
    } catch {
      const defaultWebsites: WebsiteOption[] = [
        { id: 1, name: 'Website Mặc Định', domain: 'mywebsite.vn' },
      ];
      setWebsites(defaultWebsites);
      setSelectedWebsiteId(1);
    }
  };

  const resetForm = () => {
    setEditingId(null);
    setDialogTab('basic');
    const currentWebsite = websites.find((w) => w.id === selectedWebsiteId);
    setFormData({
      brand_name: '',
      tagline: '',
      tone_of_voice: 'professional',
      industry_key: '',
      business_model: 'B2C',
      target_personas: [],
      unique_selling_points: [],
      product_catalog: [],
      taboo_words: [],
      preferred_terms: [],
      custom_rules: '',
      business_profile: {
        company_name: '',
        brand_name: '',
        website_url: currentWebsite?.url || currentWebsite?.domain || '',
        primary_industry: '',
        secondary_industry: '',
        country_market: 'Việt Nam',
        service_area: 'Toàn quốc',
        content_language: 'vi',
        business_model: 'B2C',
        company_intro: '',
        company_history: '',
        customer_locations: '',
        problems_solved: '',
        competitive_differentiation: '',
        sales_policy: '',
        warranty_policy: '',
        contact_info: '',
      },
      eeat_credentials: {
        expert_team: [],
        verified_qualifications: [],
        operating_license: '',
        verified_achievements: [],
        partners_certifications: [],
        reference_sources: [],
        content_reviewer: '',
      },
      brand_orientation: {
        writing_style: 'Chuyên nghiệp, rõ ràng, dễ hiểu',
        brand_voice: 'professional',
        pronouns_addressing: 'Chúng tôi / Quý khách (hoặc Bạn)',
        brand_positioning: '',
        priority_cta: '',
      },
      is_default: brandProfiles.length === 0,
    });
    setPersonaInput('');
    setUspInput('');
    setTabooInput('');
    setPreferredInput('');
    setExpertTeamText('');
    setQualificationsText('');
    setAchievementsText('');
    setPartnersText('');
    setReferencesText('');
    setNewProdName('');
    setNewProdDesc('');
    setNewProdUrl('');
  };

  const openCreateDialog = () => {
    resetForm();
    setIsDialogOpen(true);
  };

  const openEditDialog = (profile: BrandKnowledgeBase) => {
    setEditingId(profile.id);
    setDialogTab('basic');
    const bp = profile.business_profile || {};
    const eeat = profile.eeat_credentials || {};
    const orient = profile.brand_orientation || {};

    setFormData({
      brand_name: profile.brand_name,
      tagline: profile.tagline || '',
      tone_of_voice: profile.tone_of_voice || 'professional',
      industry_key: profile.industry_key || '',
      business_model: profile.business_model || bp.business_model || 'B2C',
      target_personas: profile.target_personas || [],
      unique_selling_points: profile.unique_selling_points || [],
      product_catalog: profile.product_catalog || [],
      taboo_words: profile.taboo_words || [],
      preferred_terms: profile.preferred_terms || [],
      custom_rules: profile.custom_rules || '',
      business_profile: {
        company_name: bp.company_name || profile.brand_name,
        brand_name: bp.brand_name || profile.brand_name,
        website_url: bp.website_url || profile.website?.url || profile.website?.domain || '',
        primary_industry: bp.primary_industry || '',
        secondary_industry: bp.secondary_industry || '',
        country_market: bp.country_market || 'Việt Nam',
        service_area: bp.service_area || 'Toàn quốc',
        content_language: bp.content_language || 'vi',
        business_model: bp.business_model || profile.business_model || 'B2C',
        company_intro: bp.company_intro || '',
        company_history: bp.company_history || '',
        customer_locations: bp.customer_locations || '',
        problems_solved: bp.problems_solved || '',
        competitive_differentiation: bp.competitive_differentiation || '',
        sales_policy: bp.sales_policy || '',
        warranty_policy: bp.warranty_policy || '',
        contact_info: bp.contact_info || '',
      },
      eeat_credentials: {
        expert_team: eeat.expert_team || [],
        verified_qualifications: eeat.verified_qualifications || [],
        operating_license: eeat.operating_license || '',
        verified_achievements: eeat.verified_achievements || [],
        partners_certifications: eeat.partners_certifications || [],
        reference_sources: eeat.reference_sources || [],
        content_reviewer: eeat.content_reviewer || '',
      },
      brand_orientation: {
        writing_style: orient.writing_style || profile.tone_of_voice || 'professional',
        brand_voice: orient.brand_voice || profile.tone_of_voice || 'professional',
        pronouns_addressing: orient.pronouns_addressing || 'Chúng tôi / Quý khách',
        brand_positioning: orient.brand_positioning || profile.tagline || '',
        priority_cta: orient.priority_cta || '',
      },
      is_default: profile.is_default,
    });

    setExpertTeamText((eeat.expert_team || []).join('\n'));
    setQualificationsText((eeat.verified_qualifications || []).join('\n'));
    setAchievementsText((eeat.verified_achievements || []).join('\n'));
    setPartnersText((eeat.partners_certifications || []).join('\n'));
    setReferencesText((eeat.reference_sources || []).join('\n'));

    setIsDialogOpen(true);
  };

  const handleAutoDetectIndustry = async () => {
    try {
      const res = await detectIndustryMutation.mutateAsync({
        primary_industry: formData.business_profile.primary_industry,
        secondary_industry: formData.business_profile.secondary_industry,
        brand_name: formData.brand_name,
        tagline: formData.tagline,
        company_intro: formData.business_profile.company_intro,
        product_catalog: formData.product_catalog,
      });
      setFormData((prev) => ({
        ...prev,
        industry_key: res.industry_key,
      }));
      toast.success(`Đã phân loại tự động: ${res.industry_profile.name}`);
    } catch {
      toast.error('Không thể tự động phân loại ngành nghề');
    }
  };

  const splitLines = (text: string): string[] =>
    text
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line.length > 0);

  const handleSave = () => {
    if (!formData.brand_name.trim()) {
      toast.error('Vui lòng nhập Tên thương hiệu');
      return;
    }

    if (!selectedWebsiteId) {
      toast.error('Vui lòng chọn Website');
      return;
    }

    startTransition(async () => {
      try {
        const payload: BrandKnowledgePayload = {
          website_id: selectedWebsiteId,
          brand_name: formData.brand_name.trim(),
          tagline: formData.tagline.trim() || undefined,
          tone_of_voice: formData.tone_of_voice,
          industry_key: formData.industry_key || undefined,
          business_model: formData.business_model,
          target_personas: formData.target_personas,
          unique_selling_points: formData.unique_selling_points,
          product_catalog: formData.product_catalog,
          taboo_words: formData.taboo_words,
          preferred_terms: formData.preferred_terms,
          custom_rules: formData.custom_rules.trim() || undefined,
          business_profile: {
            ...formData.business_profile,
            brand_name: formData.brand_name.trim(),
            business_model: formData.business_model,
          },
          eeat_credentials: {
            expert_team: splitLines(expertTeamText),
            verified_qualifications: splitLines(qualificationsText),
            operating_license: formData.eeat_credentials.operating_license?.trim() || '',
            verified_achievements: splitLines(achievementsText),
            partners_certifications: splitLines(partnersText),
            reference_sources: splitLines(referencesText),
            content_reviewer: formData.eeat_credentials.content_reviewer?.trim() || '',
          },
          brand_orientation: {
            ...formData.brand_orientation,
            brand_voice: formData.tone_of_voice,
          },
          is_default: formData.is_default,
        };

        if (editingId) {
          await update.mutateAsync({ id: editingId, payload });
          toast.success('Cập nhật hồ sơ doanh nghiệp & thương hiệu thành công!');
        } else {
          await create.mutateAsync(payload);
          toast.success('Tạo hồ sơ doanh nghiệp & thương hiệu thành công!');
        }

        setIsDialogOpen(false);
        resetForm();
        refetch();
      } catch (err: unknown) {
        const errMsg = err instanceof Error ? err.message : 'Đã có lỗi xảy ra';
        toast.error(errMsg);
      }
    });
  };

  const handleDelete = (profile: BrandKnowledgeBase) => {
    if (!confirm(`Bạn có chắc chắn muốn xóa hồ sơ thương hiệu "${profile.brand_name}" không?`)) {
      return;
    }

    startTransition(async () => {
      try {
        await remove.mutateAsync(profile.id);
        toast.success('Đã xóa hồ sơ thương hiệu');
        refetch();
      } catch (err: unknown) {
        const errMsg = err instanceof Error ? err.message : 'Không thể xóa hồ sơ';
        toast.error(errMsg);
      }
    });
  };

  const handleSetDefault = (profile: BrandKnowledgeBase) => {
    startTransition(async () => {
      try {
        await setDefault.mutateAsync(profile.id);
        toast.success(`Đã đặt "${profile.brand_name}" làm hồ sơ mặc định!`);
        refetch();
      } catch (err: unknown) {
        const errMsg = err instanceof Error ? err.message : 'Không thể đặt mặc định';
        toast.error(errMsg);
      }
    });
  };

  // Helper quản lý Tags
  const addTag = (
    field: 'target_personas' | 'unique_selling_points' | 'taboo_words' | 'preferred_terms',
    value: string,
    clearFn: (v: string) => void
  ) => {
    const trimmed = value.trim();
    if (!trimmed) return;
    if (!formData[field].includes(trimmed)) {
      setFormData((prev) => ({
        ...prev,
        [field]: [...prev[field], trimmed],
      }));
    }
    clearFn('');
  };

  const removeTag = (
    field: 'target_personas' | 'unique_selling_points' | 'taboo_words' | 'preferred_terms',
    indexToRemove: number
  ) => {
    setFormData((prev) => ({
      ...prev,
      [field]: prev[field].filter((_, idx) => idx !== indexToRemove),
    }));
  };

  // Helper quản lý Products
  const addProduct = () => {
    if (!newProdName.trim()) {
      toast.error('Vui lòng nhập tên sản phẩm/dịch vụ');
      return;
    }
    const item: ProductCatalogItem = {
      name: newProdName.trim(),
      description: newProdDesc.trim() || undefined,
      url: newProdUrl.trim() || undefined,
    };
    setFormData((prev) => ({
      ...prev,
      product_catalog: [...prev.product_catalog, item],
    }));
    setNewProdName('');
    setNewProdDesc('');
    setNewProdUrl('');
  };

  const removeProduct = (idx: number) => {
    setFormData((prev) => ({
      ...prev,
      product_catalog: prev.product_catalog.filter((_, i) => i !== idx),
    }));
  };

  // Tester Actions
  const handleTestInjectContext = () => {
    if (!selectedWebsiteId) return;

    startTransition(async () => {
      try {
        const res = await injectMutation.mutateAsync({
          website_id: selectedWebsiteId,
          prompt: testPrompt,
        });
        setPreviewContext(res.injected_prompt || res.context_text);
        toast.success('Đã tạo ngữ cảnh AI thành công');
      } catch (err: unknown) {
        const errMsg = err instanceof Error ? err.message : 'Lỗi khi tạo ngữ cảnh';
        toast.error(errMsg);
      }
    });
  };

  const handleTestTabooValidation = () => {
    if (!selectedWebsiteId) return;
    if (!testContent.trim()) {
      toast.error('Vui lòng nhập nội dung cần kiểm tra');
      return;
    }

    startTransition(async () => {
      try {
        const res = await validateTabooMutation.mutateAsync({
          website_id: selectedWebsiteId,
          content: testContent,
        });
        setTabooViolations(res.violations);
        if (res.clean) {
          toast.success('An toàn! Nội dung không chứa từ ngữ cấm kỵ.');
        } else {
          toast.error(`Cảnh báo: Phát hiện ${res.taboo_count} từ cấm kỵ vi phạm!`);
        }
      } catch (err: unknown) {
        const errMsg = err instanceof Error ? err.message : 'Lỗi khi kiểm tra từ cấm';
        toast.error(errMsg);
      }
    });
  };

  const handleCopyContext = () => {
    if (!previewContext) return;
    navigator.clipboard.writeText(previewContext);
    setIsCopied(true);
    toast.success('Đã sao chép nội dung vào Clipboard!');
    setTimeout(() => setIsCopied(false), 2000);
  };

  const activeIndustryProfile = industryProfiles.find((p) => p.key === formData.industry_key);

  return (
    <div className="container mx-auto space-y-6 p-6">
      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-primary/10 p-2 text-primary">
              <BookOpen className="h-6 w-6" />
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Business Profile &amp; Brand Knowledge</h1>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Khai báo hồ sơ doanh nghiệp, chứng thực chuyên môn E-E-A-T, định hướng thương hiệu và Industry Profile đa ngành cho AI Content Engine.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button onClick={openCreateDialog} className="gap-2">
            <Plus className="h-4 w-4" />
            Thêm Hồ sơ Doanh nghiệp
          </Button>
        </div>
      </div>

      {/* Control Bar: Website selector & Filters */}
      <div className="flex flex-col gap-3 rounded-lg border bg-card p-4 shadow-sm md:flex-row md:items-center md:justify-between">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2">
            <Globe className="h-4 w-4 text-muted-foreground" />
            <span className="text-sm font-medium">Website:</span>
            <select
              value={selectedWebsiteId || ''}
              onChange={(e) => setSelectedWebsiteId(Number(e.target.value))}
              className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              {websites.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name} ({w.domain})
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Giọng văn:</span>
            <select
              value={toneFilter}
              onChange={(e) => setToneFilter(e.target.value)}
              className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="all">Tất cả phong cách</option>
              {TONE_OPTIONS.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="relative w-full md:w-64">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Tìm theo tên, tagline..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-8"
          />
        </div>
      </div>

      {/* Main Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'profiles' | 'tester')}>
        <TabsList className="grid w-full grid-cols-2 md:w-[440px]">
          <TabsTrigger value="profiles" className="gap-2">
            <BookOpen className="h-4 w-4" />
            Hồ sơ Doanh nghiệp ({brandProfiles.length})
          </TabsTrigger>
          <TabsTrigger value="tester" className="gap-2">
            <Sparkles className="h-4 w-4" />
            Thử nghiệm Context &amp; Từ cấm
          </TabsTrigger>
        </TabsList>

        {/* Tab 1: Danh sách hồ sơ */}
        <TabsContent value="profiles" className="mt-6 space-y-4">
          {isLoading ? (
            <div className="grid gap-4 md:grid-cols-2">
              <Skeleton className="h-64 rounded-xl" />
              <Skeleton className="h-64 rounded-xl" />
            </div>
          ) : brandProfiles.length === 0 ? (
            <Card className="border-dashed py-12 text-center">
              <CardContent className="space-y-4">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                  <Building2 className="h-6 w-6" />
                </div>
                <div className="space-y-1">
                  <h3 className="font-semibold text-lg">Chưa có Hồ sơ Doanh nghiệp</h3>
                  <p className="text-sm text-muted-foreground">
                    Khai báo thông tin doanh nghiệp, chuyên môn E-E-A-T và định hướng thương hiệu để tái sử dụng cho mọi bài viết SEO.
                  </p>
                </div>
                <Button onClick={openCreateDialog} className="gap-2">
                  <Plus className="h-4 w-4" /> Khai báo Hồ sơ Doanh nghiệp
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-6 md:grid-cols-2">
              {brandProfiles.map((profile) => {
                const indProfile = profile.industry_profile;
                const eeatCount =
                  (profile.eeat_credentials?.expert_team?.length || 0) +
                  (profile.eeat_credentials?.verified_qualifications?.length || 0) +
                  (profile.eeat_credentials?.verified_achievements?.length || 0) +
                  (profile.eeat_credentials?.operating_license ? 1 : 0);

                return (
                  <Card
                    key={profile.id}
                    className={`relative flex flex-col justify-between transition-all hover:shadow-md ${
                      profile.is_default ? 'border-primary shadow-sm ring-1 ring-primary/20' : ''
                    }`}
                  >
                    <CardHeader className="pb-3">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <div className="flex flex-wrap items-center gap-2">
                            <CardTitle className="text-xl font-bold">{profile.brand_name}</CardTitle>
                            {profile.is_default && (
                              <Badge className="bg-primary hover:bg-primary/90 text-primary-foreground gap-1">
                                <Star className="h-3 w-3 fill-current" />
                                Mặc định
                              </Badge>
                            )}
                            {profile.business_model && (
                              <Badge variant="outline" className="text-xs font-semibold">
                                {profile.business_model}
                              </Badge>
                            )}
                          </div>
                          {profile.tagline && (
                            <CardDescription className="mt-1 font-medium italic text-foreground/80">
                              &ldquo;{profile.tagline}&rdquo;
                            </CardDescription>
                          )}
                        </div>

                        <div className="flex items-center gap-1">
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => openEditDialog(profile)}
                            title="Chỉnh sửa"
                          >
                            <Edit3 className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleDelete(profile)}
                            className="text-destructive hover:bg-destructive/10"
                            title="Xóa hồ sơ"
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    </CardHeader>

                    <CardContent className="space-y-4 pb-4">
                      {/* Industry Profile & Tone of Voice */}
                      <div className="flex flex-wrap items-center gap-2 text-xs">
                        {indProfile && (
                          <Badge variant="secondary" className="gap-1 font-medium">
                            <Briefcase className="h-3 w-3" />
                            {indProfile.name}
                          </Badge>
                        )}
                        {indProfile?.ymyl_level === 'high' && (
                          <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            YMYL Cao (Kiểm duyệt chặt)
                          </Badge>
                        )}
                        <Badge variant="outline" className="font-medium">
                          Giọng văn: {profile.tone_of_voice}
                        </Badge>
                        <Badge variant="outline" className="gap-1 border-blue-200 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                          <Award className="h-3 w-3" />
                          E-E-A-T: {eeatCount} dữ liệu xác thực
                        </Badge>
                      </div>

                      {/* Personas */}
                      {profile.target_personas && profile.target_personas.length > 0 && (
                        <div className="space-y-1">
                          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Khách hàng mục tiêu ({profile.target_personas.length}):
                          </span>
                          <div className="flex flex-wrap gap-1.5">
                            {profile.target_personas.map((persona, idx) => (
                              <Badge key={idx} variant="outline" className="text-xs">
                                {persona}
                              </Badge>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* USPs */}
                      {profile.unique_selling_points && profile.unique_selling_points.length > 0 && (
                        <div className="space-y-1">
                          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Lợi thế cạnh tranh (USPs):
                          </span>
                          <div className="flex flex-wrap gap-1.5">
                            {profile.unique_selling_points.map((usp, idx) => (
                              <Badge
                                key={idx}
                                variant="outline"
                                className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800 text-xs"
                              >
                                ✓ {usp}
                              </Badge>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Taboo Words & Preferred */}
                      <div className="grid grid-cols-2 gap-3 pt-1">
                        <div>
                          <span className="text-xs font-semibold text-destructive flex items-center gap-1">
                            <ShieldAlert className="h-3.5 w-3.5" />
                            Từ cấm ({profile.taboo_words?.length || 0}):
                          </span>
                          <div className="mt-1 flex flex-wrap gap-1">
                            {profile.taboo_words && profile.taboo_words.length > 0 ? (
                              profile.taboo_words.slice(0, 4).map((w, i) => (
                                <Badge key={i} variant="destructive" className="text-[11px] px-1.5 py-0">
                                  {w}
                                </Badge>
                              ))
                            ) : (
                              <span className="text-xs text-muted-foreground">Không có</span>
                            )}
                            {profile.taboo_words && profile.taboo_words.length > 4 && (
                              <span className="text-xs text-muted-foreground">+{profile.taboo_words.length - 4} từ</span>
                            )}
                          </div>
                        </div>

                        <div>
                          <span className="text-xs font-semibold text-primary flex items-center gap-1">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            Từ ưu tiên ({profile.preferred_terms?.length || 0}):
                          </span>
                          <div className="mt-1 flex flex-wrap gap-1">
                            {profile.preferred_terms && profile.preferred_terms.length > 0 ? (
                              profile.preferred_terms.slice(0, 4).map((w, i) => (
                                <Badge key={i} variant="secondary" className="text-[11px] px-1.5 py-0">
                                  {w}
                                </Badge>
                              ))
                            ) : (
                              <span className="text-xs text-muted-foreground">Không có</span>
                            )}
                          </div>
                        </div>
                      </div>

                      {/* Custom Rules preview */}
                      {profile.custom_rules && (
                        <div className="rounded-md bg-muted/50 p-2.5 text-xs text-muted-foreground">
                          <span className="font-semibold text-foreground">Quy tắc riêng: </span>
                          {profile.custom_rules.length > 120
                            ? `${profile.custom_rules.substring(0, 120)}...`
                            : profile.custom_rules}
                        </div>
                      )}
                    </CardContent>

                    <div className="border-t bg-muted/20 px-6 py-3 flex items-center justify-between">
                      <span className="text-xs text-muted-foreground">
                        Cập nhật: {new Date(profile.updated_at).toLocaleDateString('vi-VN')}
                      </span>
                      {!profile.is_default && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleSetDefault(profile)}
                          disabled={isSubmitting}
                          className="text-xs h-8"
                        >
                          Đặt làm mặc định
                        </Button>
                      )}
                    </div>
                  </Card>
                );
              })}
            </div>
          )}
        </TabsContent>

        {/* Tab 2: Thử nghiệm Context & Kiểm tra từ cấm */}
        <TabsContent value="tester" className="mt-6 space-y-6">
          <div className="grid gap-6 md:grid-cols-2">
            {/* Context Injector Simulator */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <Sparkles className="h-5 w-5 text-primary" />
                  Mô phỏng nhúng ngữ cảnh Prompt
                </CardTitle>
                <CardDescription>
                  Xem trước cách hệ thống tự động đưa Hồ sơ Doanh nghiệp, E-E-A-T &amp; Industry Profile vào Prompt hệ thống.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <label className="text-sm font-medium">Câu Prompt mẫu:</label>
                  <Textarea
                    rows={4}
                    value={testPrompt}
                    onChange={(e) => setTestPrompt(e.target.value)}
                    placeholder="Nhập prompt mẫu chứa {{brand_context}}..."
                  />
                  <span className="text-xs text-muted-foreground">
                    Sử dụng biến placeholder <code>&#123;&#123;brand_context&#125;&#125;</code> để AI tự điền thông tin.
                  </span>
                </div>

                <Button
                  onClick={handleTestInjectContext}
                  disabled={isSubmitting || injectMutation.isPending}
                  className="gap-2"
                >
                  {(isSubmitting || injectMutation.isPending) && <Loader2 className="h-4 w-4 animate-spin" />}
                  Sinh Prompt hoàn chỉnh
                </Button>

                {previewContext && (
                  <div className="space-y-2 pt-2">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-semibold text-muted-foreground">Kết quả nhúng ngữ cảnh:</span>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleCopyContext}
                        className="h-7 text-xs gap-1"
                      >
                        {isCopied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
                        {isCopied ? 'Đã chép' : 'Sao chép'}
                      </Button>
                    </div>
                    <pre className="max-h-72 overflow-y-auto rounded-md bg-muted p-3 text-xs leading-relaxed whitespace-pre-wrap font-mono">
                      {previewContext}
                    </pre>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Taboo Words Scanner */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <ShieldAlert className="h-5 w-5 text-destructive" />
                  Quét phát hiện Từ ngữ cấm kỵ (Taboo Words)
                </CardTitle>
                <CardDescription>
                  Dán nội dung bài viết vào đây để kiểm tra xem có vô tình xuất hiện các từ ngữ bị cấm hay không.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <label className="text-sm font-medium">Đoạn văn bản kiểm tra:</label>
                  <Textarea
                    rows={6}
                    value={testContent}
                    onChange={(e) => setTestContent(e.target.value)}
                    placeholder="Dán nội dung bài viết cần rà soát từ ngữ cấm kỵ..."
                  />
                </div>

                <Button
                  onClick={handleTestTabooValidation}
                  variant="outline"
                  disabled={isSubmitting || validateTabooMutation.isPending}
                  className="gap-2"
                >
                  {(isSubmitting || validateTabooMutation.isPending) && (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  )}
                  <ShieldCheck className="h-4 w-4" />
                  Quét rà soát từ cấm
                </Button>

                {tabooViolations !== null && (
                  <div className="pt-2">
                    {Object.keys(tabooViolations).length === 0 ? (
                      <div className="flex items-center gap-2 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800">
                        <CheckCircle2 className="h-5 w-5 text-emerald-600 flex-shrink-0" />
                        <span>Nội dung hoàn toàn sạch sẽ, không vi phạm bất kỳ từ ngữ cấm kỵ nào.</span>
                      </div>
                    ) : (
                      <div className="space-y-2 rounded-md bg-destructive/10 p-3 text-sm border border-destructive/30">
                        <div className="flex items-center gap-2 font-semibold text-destructive">
                          <AlertTriangle className="h-5 w-5 flex-shrink-0" />
                          <span>Phát hiện {Object.keys(tabooViolations).length} loại từ cấm kỵ vi phạm:</span>
                        </div>
                        <ul className="list-inside list-disc space-y-1 text-xs text-destructive">
                          {Object.entries(tabooViolations).map(([word, count]) => (
                            <li key={word}>
                              Từ &ldquo;<strong>{word}</strong>&rdquo; xuất hiện <strong>{count}</strong> lần
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>

      {/* Modal Dialog: Khai báo / Chỉnh sửa Hồ sơ Doanh nghiệp (Phần I: 3.1 - 3.5) */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-h-[92vh] overflow-y-auto max-w-4xl">
          <DialogHeader>
            <DialogTitle>
              {editingId ? 'Chỉnh sửa Hồ sơ Doanh nghiệp & Thương hiệu' : 'Khai báo Hồ sơ Doanh nghiệp mới'}
            </DialogTitle>
            <DialogDescription>
              Thông tin doanh nghiệp được lưu trữ và tái sử dụng cho những lần viết bài tiếp theo. AI tuyệt đối không tự suy diễn thông tin chưa được xác minh.
            </DialogDescription>
          </DialogHeader>

          <Tabs value={dialogTab} onValueChange={(v) => setDialogTab(v as 'basic' | 'operations' | 'eeat' | 'brand')}>
            <TabsList className="grid w-full grid-cols-2 sm:grid-cols-4">
              <TabsTrigger value="basic" className="gap-1.5 text-xs">
                <Building2 className="h-3.5 w-3.5" />
                1. Cơ bản &amp; Ngành nghề
              </TabsTrigger>
              <TabsTrigger value="operations" className="gap-1.5 text-xs">
                <Briefcase className="h-3.5 w-3.5" />
                2. Hoạt động &amp; Sản phẩm
              </TabsTrigger>
              <TabsTrigger value="eeat" className="gap-1.5 text-xs">
                <Award className="h-3.5 w-3.5" />
                3. Chuyên môn E-E-A-T
              </TabsTrigger>
              <TabsTrigger value="brand" className="gap-1.5 text-xs">
                <Compass className="h-3.5 w-3.5" />
                4. Định hướng &amp; Từ cấm
              </TabsTrigger>
            </TabsList>

            {/* 3.1 & 3.5: THÔNG TIN CƠ BẢN & INDUSTRY PROFILE */}
            <TabsContent value="basic" className="mt-4 space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">
                    Tên thương hiệu (Brand Name) <span className="text-destructive">*</span>
                  </label>
                  <Input
                    placeholder="Ví dụ: Aura Dental, NovaTech SaaS, Luật Minh Khuê..."
                    value={formData.brand_name}
                    onChange={(e) => setFormData({ ...formData, brand_name: e.target.value })}
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Tên pháp nhân / Tên doanh nghiệp</label>
                  <Input
                    placeholder="Ví dụ: Công ty Cổ phần Công nghệ..."
                    value={formData.business_profile.company_name || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, company_name: e.target.value },
                      })
                    }
                  />
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Website chính thức</label>
                  <Input
                    placeholder="https://example.com"
                    value={formData.business_profile.website_url || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, website_url: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Mô hình doanh nghiệp</label>
                  <select
                    value={formData.business_model}
                    onChange={(e) => setFormData({ ...formData, business_model: e.target.value })}
                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                  >
                    {BUSINESS_MODEL_OPTIONS.map((m) => (
                      <option key={m.value} value={m.value}>
                        {m.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Ngôn ngữ nội dung mặc định</label>
                  <select
                    value={formData.business_profile.content_language || 'vi'}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, content_language: e.target.value },
                      })
                    }
                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                  >
                    <option value="vi">Tiếng Việt (vi)</option>
                    <option value="en">Tiếng Anh (en)</option>
                  </select>
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Ngành nghề kinh doanh chính</label>
                  <Input
                    placeholder="Ví dụ: Nha khoa thẩm mỹ, Bất động sản nghỉ dưỡng, Phần mềm CRM..."
                    value={formData.business_profile.primary_industry || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, primary_industry: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Ngành nghề phụ (nếu có)</label>
                  <Input
                    placeholder="Ví dụ: Đào tạo nội bộ, Tư vấn chuyển đổi số..."
                    value={formData.business_profile.secondary_industry || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, secondary_industry: e.target.value },
                      })
                    }
                  />
                </div>
              </div>

              {/* 3.5: Hồ sơ ngành nghề (Industry Profile) */}
              <div className="rounded-lg border bg-muted/30 p-3.5 space-y-2.5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <span className="text-sm font-semibold">3.5. Hồ sơ ngành nghề (Industry Profile)</span>
                    <p className="text-xs text-muted-foreground">
                      Hệ thống tự động phân loại hoặc cho phép chọn cấu hình quy tắc nội dung, YMYL &amp; cấu trúc chuẩn theo ngành.
                    </p>
                  </div>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleAutoDetectIndustry}
                    disabled={detectIndustryMutation.isPending}
                    className="h-8 text-xs gap-1.5"
                  >
                    {detectIndustryMutation.isPending ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Sparkles className="h-3.5 w-3.5 text-primary" />
                    )}
                    Tự động nhận diện ngành
                  </Button>
                </div>

                <select
                  value={formData.industry_key}
                  onChange={(e) => setFormData({ ...formData, industry_key: e.target.value })}
                  className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                >
                  <option value="">-- Tự động phân loại khi lưu --</option>
                  {industryProfiles.map((ind) => (
                    <option key={ind.key} value={ind.key}>
                      {ind.name} (YMYL: {ind.ymyl_level.toUpperCase()} | Xác thực: {ind.verification_requirement})
                    </option>
                  ))}
                </select>

                {activeIndustryProfile && (
                  <div className="rounded-md border bg-background p-3 text-xs space-y-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                      <strong className="text-primary">{activeIndustryProfile.name}</strong>
                      <Badge variant="outline" className="text-[11px]">
                        YMYL: {activeIndustryProfile.ymyl_level.toUpperCase()}
                      </Badge>
                      <Badge variant="outline" className="text-[11px]">
                        Mức xác thực: {activeIndustryProfile.verification_requirement}
                      </Badge>
                    </div>
                    <p className="text-muted-foreground">
                      <strong>Quy tắc trọng tâm:</strong> {activeIndustryProfile.content_rules.slice(0, 2).join(' ')}
                    </p>
                    <p className="text-muted-foreground">
                      <strong>Kiểm soát tuyên bố nhạy cảm:</strong>{' '}
                      {activeIndustryProfile.sensitive_claims_to_control.join('; ')}
                    </p>
                  </div>
                )}
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Quốc gia &amp; Thị trường hoạt động</label>
                  <Input
                    placeholder="Ví dụ: Việt Nam, Đông Nam Á..."
                    value={formData.business_profile.country_market || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, country_market: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Địa chỉ &amp; Khu vực phục vụ</label>
                  <Input
                    placeholder="Ví dụ: Quận 1 TP.HCM, Hà Nội & phục vụ trực tuyến toàn quốc"
                    value={formData.business_profile.service_area || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, service_area: e.target.value },
                      })
                    }
                  />
                </div>
              </div>
            </TabsContent>

            {/* 3.2: MÔ TẢ HOẠT ĐỘNG KINH DOANH */}
            <TabsContent value="operations" className="mt-4 space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Giới thiệu doanh nghiệp</label>
                  <Textarea
                    rows={3}
                    placeholder="Tóm tắt hoạt động cốt lõi, sứ mệnh và quy mô..."
                    value={formData.business_profile.company_intro || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, company_intro: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Lịch sử hình thành &amp; cột mốc (đã xác minh)</label>
                  <Textarea
                    rows={3}
                    placeholder="Năm thành lập, các cột mốc phát triển thực tế..."
                    value={formData.business_profile.company_history || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, company_history: e.target.value },
                      })
                    }
                  />
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Vấn đề doanh nghiệp giải quyết cho khách hàng</label>
                  <Textarea
                    rows={2}
                    placeholder="Nỗi đau / vấn đề thực tế mà sản phẩm hoặc dịch vụ giải quyết..."
                    value={formData.business_profile.problems_solved || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, problems_solved: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Điểm khác biệt so với đối thủ</label>
                  <Textarea
                    rows={2}
                    placeholder="Điểm khác biệt về công nghệ, quy trình, dịch vụ hậu mãi..."
                    value={formData.business_profile.competitive_differentiation || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: {
                          ...formData.business_profile,
                          competitive_differentiation: e.target.value,
                        },
                      })
                    }
                  />
                </div>
              </div>

              {/* Target Personas & USPs */}
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Đối tượng khách hàng (Personas)</label>
                  <div className="flex gap-2">
                    <Input
                      placeholder="Nhập chân dung khách hàng..."
                      value={personaInput}
                      onChange={(e) => setPersonaInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          addTag('target_personas', personaInput, setPersonaInput);
                        }
                      }}
                    />
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={() => addTag('target_personas', personaInput, setPersonaInput)}
                    >
                      Thêm
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-1.5 pt-1">
                    {formData.target_personas.map((item, idx) => (
                      <Badge key={idx} variant="outline" className="gap-1 pr-1 text-xs">
                        {item}
                        <button
                          type="button"
                          onClick={() => removeTag('target_personas', idx)}
                          className="ml-1 rounded-full p-0.5 hover:bg-muted"
                        >
                          ×
                        </button>
                      </Badge>
                    ))}
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Lợi thế cạnh tranh (USPs)</label>
                  <div className="flex gap-2">
                    <Input
                      placeholder="Nhập USP nổi bật..."
                      value={uspInput}
                      onChange={(e) => setUspInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          addTag('unique_selling_points', uspInput, setUspInput);
                        }
                      }}
                    />
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={() => addTag('unique_selling_points', uspInput, setUspInput)}
                    >
                      Thêm
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-1.5 pt-1">
                    {formData.unique_selling_points.map((item, idx) => (
                      <Badge
                        key={idx}
                        variant="secondary"
                        className="gap-1 pr-1 text-xs bg-emerald-50 text-emerald-800 border-emerald-200"
                      >
                        {item}
                        <button
                          type="button"
                          onClick={() => removeTag('unique_selling_points', idx)}
                          className="ml-1 rounded-full p-0.5 hover:bg-emerald-200"
                        >
                          ×
                        </button>
                      </Badge>
                    ))}
                  </div>
                </div>
              </div>

              {/* Product Catalog */}
              <div className="space-y-2 rounded-lg border p-3">
                <div className="flex items-center gap-2">
                  <Package className="h-4 w-4 text-primary" />
                  <span className="text-sm font-medium">Danh mục Sản phẩm &amp; Dịch vụ</span>
                </div>
                <div className="grid gap-2 sm:grid-cols-3">
                  <Input
                    placeholder="Tên sản phẩm / dịch vụ *"
                    value={newProdName}
                    onChange={(e) => setNewProdName(e.target.value)}
                    className="text-xs"
                  />
                  <Input
                    placeholder="Mô tả / Lợi ích chính"
                    value={newProdDesc}
                    onChange={(e) => setNewProdDesc(e.target.value)}
                    className="text-xs"
                  />
                  <div className="flex gap-1.5">
                    <Input
                      placeholder="Link URL đích"
                      value={newProdUrl}
                      onChange={(e) => setNewProdUrl(e.target.value)}
                      className="text-xs"
                    />
                    <Button type="button" size="sm" onClick={addProduct} className="text-xs px-3">
                      +
                    </Button>
                  </div>
                </div>

                {formData.product_catalog.length > 0 && (
                  <div className="mt-2 space-y-1.5 overflow-x-auto">
                    {formData.product_catalog.map((prod, idx) => (
                      <div
                        key={idx}
                        className="flex items-center justify-between rounded-md bg-muted/50 px-2.5 py-1.5 text-xs"
                      >
                        <div className="flex items-center gap-2">
                          <strong className="text-foreground">{prod.name}</strong>
                          {prod.description && <span className="text-muted-foreground">- {prod.description}</span>}
                          {prod.url && (
                            <a
                              href={prod.url}
                              target="_blank"
                              rel="noreferrer"
                              className="flex items-center gap-0.5 text-primary hover:underline"
                            >
                              <ExternalLink className="h-3 w-3" />
                            </a>
                          )}
                        </div>
                        <button
                          type="button"
                          onClick={() => removeProduct(idx)}
                          className="text-destructive hover:underline"
                        >
                          Xóa
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Chính sách bán hàng / báo giá</label>
                  <Input
                    placeholder="Ví dụ: Miễn phí dùng thử 14 ngày..."
                    value={formData.business_profile.sales_policy || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, sales_policy: e.target.value },
                      })
                    }
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Chính sách bảo hành / cam kết</label>
                  <Input
                    placeholder="Ví dụ: Bảo hành chính hãng 5 năm..."
                    value={formData.business_profile.warranty_policy || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, warranty_policy: e.target.value },
                      })
                    }
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Thông tin liên hệ chính thức</label>
                  <Input
                    placeholder="Hotline, Email, Địa chỉ văn phòng..."
                    value={formData.business_profile.contact_info || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        business_profile: { ...formData.business_profile, contact_info: e.target.value },
                      })
                    }
                  />
                </div>
              </div>
            </TabsContent>

            {/* 3.3: THÔNG TIN CHUYÊN MÔN VÀ ĐỘ TIN CẬY (E-E-A-T TIER A VERIFIED DATA) */}
            <TabsContent value="eeat" className="mt-4 space-y-4">
              <div className="rounded-lg border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-200">
                <strong>Nguyên tắc E-E-A-T &amp; Chống Hallucination:</strong> Hệ thống chỉ sử dụng các thông tin chuyên gia, bằng cấp, giấy phép và giải thưởng đã được bạn khai báo tại đây (Dữ liệu hạng A). Nếu để trống, AI sẽ tuyệt đối không tự bịa đặt tên chuyên gia hay chứng chỉ giả.
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Đội ngũ chuyên gia (Mỗi dòng 1 người + chức danh)</label>
                  <Textarea
                    rows={3}
                    placeholder={"Ví dụ:\nBS.CKI Nguyễn Văn A - Giám đốc chuyên môn\nLuật sư Trần Thị B - Đoàn Luật sư TP.HCM"}
                    value={expertTeamText}
                    onChange={(e) => setExpertTeamText(e.target.value)}
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Bằng cấp, chứng chỉ đã xác minh (Mỗi dòng 1 mục)</label>
                  <Textarea
                    rows={3}
                    placeholder={"Ví dụ:\nChứng nhận ISO 27001:2022\nChứng chỉ hành nghề số 01234/BYT-CCHN"}
                    value={qualificationsText}
                    onChange={(e) => setQualificationsText(e.target.value)}
                  />
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Giấy phép hoạt động (nếu ngành nghề yêu cầu)</label>
                  <Input
                    placeholder="Số giấy phép hoạt động / ĐKKD..."
                    value={formData.eeat_credentials.operating_license || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        eeat_credentials: { ...formData.eeat_credentials, operating_license: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Người chịu trách nhiệm kiểm duyệt nội dung</label>
                  <Input
                    placeholder="Ví dụ: Ban Biên Tập Chuyên Môn / Hội đồng Y khoa..."
                    value={formData.eeat_credentials.content_reviewer || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        eeat_credentials: { ...formData.eeat_credentials, content_reviewer: e.target.value },
                      })
                    }
                  />
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Thành tựu &amp; Giải thưởng (Mỗi dòng 1 mục)</label>
                  <Textarea
                    rows={3}
                    placeholder="Giải thưởng Sao Khuê 2025..."
                    value={achievementsText}
                    onChange={(e) => setAchievementsText(e.target.value)}
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Đối tác &amp; Chứng nhận (Mỗi dòng 1 mục)</label>
                  <Textarea
                    rows={3}
                    placeholder="Đối tác chiến lược của Straumann / Google Cloud Partner..."
                    value={partnersText}
                    onChange={(e) => setPartnersText(e.target.value)}
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Nguồn tài liệu chuyên môn tham chiếu</label>
                  <Textarea
                    rows={3}
                    placeholder="Bộ Y tế, Bộ Luật Dân sự 2015, Google Search Central..."
                    value={referencesText}
                    onChange={(e) => setReferencesText(e.target.value)}
                  />
                </div>
              </div>
            </TabsContent>

            {/* 3.4: ĐỊNH HƯỚNG THƯƠNG HIỆU & TỪ VỰNG */}
            <TabsContent value="brand" className="mt-4 space-y-4">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Giọng văn thương hiệu (Tone of Voice)</label>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  {TONE_OPTIONS.map((opt) => (
                    <button
                      key={opt.value}
                      type="button"
                      onClick={() => setFormData({ ...formData, tone_of_voice: opt.value })}
                      className={`rounded-md border p-2 text-left text-xs transition-all ${
                        formData.tone_of_voice === opt.value
                          ? 'border-primary bg-primary/10 font-semibold text-primary'
                          : 'border-input hover:bg-accent'
                      }`}
                    >
                      {opt.label}
                    </button>
                  ))}
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Phong cách viết &amp; Định vị thương hiệu</label>
                  <Input
                    placeholder="Ví dụ: Chuyên gia tận tâm, giải thích khoa học dễ hiểu"
                    value={formData.brand_orientation.writing_style || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        brand_orientation: { ...formData.brand_orientation, writing_style: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Cách xưng hô chuẩn</label>
                  <Input
                    placeholder="Ví dụ: Xưng tên thương hiệu - gọi Bạn hoặc Quý khách hàng"
                    value={formData.brand_orientation.pronouns_addressing || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        brand_orientation: { ...formData.brand_orientation, pronouns_addressing: e.target.value },
                      })
                    }
                  />
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Khẩu hiệu / Định vị thương hiệu (Tagline)</label>
                  <Input
                    placeholder="Ví dụ: Giải pháp quản trị doanh nghiệp toàn diện"
                    value={formData.tagline}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        tagline: e.target.value,
                        brand_orientation: { ...formData.brand_orientation, brand_positioning: e.target.value },
                      })
                    }
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium">CTA ưu tiên (Lời kêu gọi hành động)</label>
                  <Input
                    placeholder="Ví dụ: Đăng ký dùng thử miễn phí / Đặt lịch tư vấn 1-1"
                    value={formData.brand_orientation.priority_cta || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        brand_orientation: { ...formData.brand_orientation, priority_cta: e.target.value },
                      })
                    }
                  />
                </div>
              </div>

              {/* Taboo Words & Preferred Terms */}
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-destructive flex items-center gap-1">
                    <ShieldAlert className="h-4 w-4" />
                    Từ ngữ không được sử dụng (Taboo Words)
                  </label>
                  <div className="flex gap-2">
                    <Input
                      placeholder="Ví dụ: cam kết khỏi 100%, rẻ nhất thị trường..."
                      value={tabooInput}
                      onChange={(e) => setTabooInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          addTag('taboo_words', tabooInput, setTabooInput);
                        }
                      }}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => addTag('taboo_words', tabooInput, setTabooInput)}
                    >
                      Thêm
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-1 pt-1">
                    {formData.taboo_words.map((item, idx) => (
                      <Badge key={idx} variant="destructive" className="gap-1 pr-1 text-xs">
                        {item}
                        <button
                          type="button"
                          onClick={() => removeTag('taboo_words', idx)}
                          className="ml-1 rounded-full p-0.5 hover:bg-destructive/80"
                        >
                          ×
                        </button>
                      </Badge>
                    ))}
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-primary flex items-center gap-1">
                    <ShieldCheck className="h-4 w-4" />
                    Từ ngữ nên sử dụng (Preferred Terms)
                  </label>
                  <div className="flex gap-2">
                    <Input
                      placeholder="Thuật ngữ chuẩn của thương hiệu..."
                      value={preferredInput}
                      onChange={(e) => setPreferredInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          addTag('preferred_terms', preferredInput, setPreferredInput);
                        }
                      }}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => addTag('preferred_terms', preferredInput, setPreferredInput)}
                    >
                      Thêm
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-1 pt-1">
                    {formData.preferred_terms.map((item, idx) => (
                      <Badge key={idx} variant="secondary" className="gap-1 pr-1 text-xs">
                        {item}
                        <button
                          type="button"
                          onClick={() => removeTag('preferred_terms', idx)}
                          className="ml-1 rounded-full p-0.5 hover:bg-muted"
                        >
                          ×
                        </button>
                      </Badge>
                    ))}
                  </div>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">Quy tắc biên tập riêng biệt (Custom Rules)</label>
                <Textarea
                  rows={3}
                  placeholder="Các yêu cầu đặc thù khác của thương hiệu khi viết bài..."
                  value={formData.custom_rules}
                  onChange={(e) => setFormData({ ...formData, custom_rules: e.target.value })}
                />
              </div>

              <div className="flex items-center gap-2 pt-1">
                <input
                  type="checkbox"
                  id="is_default"
                  checked={formData.is_default}
                  onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })}
                  className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                />
                <label htmlFor="is_default" className="text-sm font-medium cursor-pointer">
                  Đặt làm Hồ sơ Doanh nghiệp mặc định cho Website này
                </label>
              </div>
            </TabsContent>
          </Tabs>

          <DialogFooter className="gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setIsDialogOpen(false)}
              disabled={isSubmitting}
            >
              Hủy
            </Button>
            <Button type="button" onClick={handleSave} disabled={isSubmitting} className="gap-2">
              {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
              {editingId ? 'Lưu Hồ sơ Doanh nghiệp' : 'Tạo Hồ sơ Doanh nghiệp'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
