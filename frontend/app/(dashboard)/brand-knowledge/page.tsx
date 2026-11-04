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
  Tag,
  AlertTriangle,
  Package,
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
  ProductCatalogItem,
  useBrandKnowledgeList,
  useBrandKnowledgeActions,
  useInjectBrandContext,
  useValidateTabooWords,
} from '@/hooks/useBrandKnowledge';

interface WebsiteOption {
  id: number;
  name: string;
  domain: string;
}

const TONE_OPTIONS = [
  { value: 'professional', label: 'Chuyên nghiệp (Professional)' },
  { value: 'conversational', label: 'Gần gũi, Trò chuyện (Conversational)' },
  { value: 'authoritative', label: 'Chuyên gia uy tín (Authoritative)' },
  { value: 'friendly', label: 'Thân thiện, Nhiệt tình (Friendly)' },
  { value: 'inspiring', label: 'Truyền cảm hứng (Inspiring)' },
  { value: 'technical', label: 'Kỹ thuật chuyên sâu (Technical)' },
];

export default function BrandKnowledgePage() {
  const [websites, setWebsites] = useState<WebsiteOption[]>([]);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [toneFilter, setToneFilter] = useState<string>('all');
  const [activeTab, setActiveTab] = useState<'profiles' | 'tester'>('profiles');

  // Dialog & Modal States
  const [isDialogOpen, setIsDialogOpen] = useState<boolean>(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [isCopied, setIsCopied] = useState<boolean>(false);

  // Form State
  const [formData, setFormData] = useState<{
    brand_name: string;
    tagline: string;
    tone_of_voice: string;
    target_personas: string[];
    unique_selling_points: string[];
    product_catalog: ProductCatalogItem[];
    taboo_words: string[];
    preferred_terms: string[];
    custom_rules: string;
    is_default: boolean;
  }>({
    brand_name: '',
    tagline: '',
    tone_of_voice: 'professional',
    target_personas: [],
    unique_selling_points: [],
    product_catalog: [],
    taboo_words: [],
    preferred_terms: [],
    custom_rules: '',
    is_default: true,
  });

  // Tag Inputs
  const [personaInput, setPersonaInput] = useState<string>('');
  const [uspInput, setUspInput] = useState<string>('');
  const [tabooInput, setTabooInput] = useState<string>('');
  const [preferredInput, setPreferredInput] = useState<string>('');

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

  const { create, update, remove, setDefault } = useBrandKnowledgeActions();
  const injectMutation = useInjectBrandContext();
  const validateTabooMutation = useValidateTabooWords();

  // Load Websites ban đầu
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
    setFormData({
      brand_name: '',
      tagline: '',
      tone_of_voice: 'professional',
      target_personas: [],
      unique_selling_points: [],
      product_catalog: [],
      taboo_words: [],
      preferred_terms: [],
      custom_rules: '',
      is_default: brandProfiles.length === 0,
    });
    setPersonaInput('');
    setUspInput('');
    setTabooInput('');
    setPreferredInput('');
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
    setFormData({
      brand_name: profile.brand_name,
      tagline: profile.tagline || '',
      tone_of_voice: profile.tone_of_voice || 'professional',
      target_personas: profile.target_personas || [],
      unique_selling_points: profile.unique_selling_points || [],
      product_catalog: profile.product_catalog || [],
      taboo_words: profile.taboo_words || [],
      preferred_terms: profile.preferred_terms || [],
      custom_rules: profile.custom_rules || '',
      is_default: profile.is_default,
    });
    setIsDialogOpen(true);
  };

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
          target_personas: formData.target_personas,
          unique_selling_points: formData.unique_selling_points,
          product_catalog: formData.product_catalog,
          taboo_words: formData.taboo_words,
          preferred_terms: formData.preferred_terms,
          custom_rules: formData.custom_rules.trim() || undefined,
          is_default: formData.is_default,
        };

        if (editingId) {
          await update.mutateAsync({ id: editingId, payload });
          toast.success('Cập nhật hồ sơ thương hiệu thành công!');
        } else {
          await create.mutateAsync(payload);
          toast.success('Tạo hồ sơ thương hiệu thành công!');
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

  return (
    <div className="container mx-auto space-y-6 p-6">
      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-primary/10 p-2 text-primary">
              <BookOpen className="h-6 w-6" />
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Brand Knowledge Base</h1>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Quản lý kho tri thức thương hiệu, giọng văn (Tone of Voice), USP và bộ từ khóa cấm kỵ để AI tự động nhúng vào mọi bài viết.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button onClick={openCreateDialog} className="gap-2">
            <Plus className="h-4 w-4" />
            Thêm hồ sơ thương hiệu
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
        <TabsList className="grid w-full grid-cols-2 md:w-96">
          <TabsTrigger value="profiles" className="gap-2">
            <BookOpen className="h-4 w-4" />
            Hồ sơ thương hiệu ({brandProfiles.length})
          </TabsTrigger>
          <TabsTrigger value="tester" className="gap-2">
            <Sparkles className="h-4 w-4" />
            Thử nghiệm Context & Từ cấm
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
                  <BookOpen className="h-6 w-6" />
                </div>
                <div className="space-y-1">
                  <h3 className="font-semibold text-lg">Chưa có hồ sơ tri thức thương hiệu</h3>
                  <p className="text-sm text-muted-foreground">
                    Tạo hồ sơ đầu tiên để AI hiểu rõ bản sắc, USP và các quy tắc viết bài cho website của bạn.
                  </p>
                </div>
                <Button onClick={openCreateDialog} className="gap-2">
                  <Plus className="h-4 w-4" /> Tạo hồ sơ ngay
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-6 md:grid-cols-2">
              {brandProfiles.map((profile) => (
                <Card
                  key={profile.id}
                  className={`relative flex flex-col justify-between transition-all hover:shadow-md ${
                    profile.is_default ? 'border-primary shadow-sm ring-1 ring-primary/20' : ''
                  }`}
                >
                  <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <div className="flex items-center gap-2">
                          <CardTitle className="text-xl font-bold">{profile.brand_name}</CardTitle>
                          {profile.is_default && (
                            <Badge className="bg-primary hover:bg-primary/90 text-primary-foreground gap-1">
                              <Star className="h-3 w-3 fill-current" />
                              Mặc định
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
                    {/* Tone of Voice */}
                    <div className="flex items-center gap-2 text-sm">
                      <span className="font-semibold text-muted-foreground">Giọng văn:</span>
                      <Badge variant="secondary" className="font-medium">
                        {profile.tone_of_voice}
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
                            <Badge key={idx} variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800 text-xs">
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
              ))}
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
                  Xem trước cách hệ thống tự động đưa hồ sơ thương hiệu mặc định vào Prompt hệ thống của AI.
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

      {/* Modal Dialog: Thêm / Sửa Hồ sơ thương hiệu */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto max-w-3xl">
          <DialogHeader>
            <DialogTitle>{editingId ? 'Chỉnh sửa hồ sơ thương hiệu' : 'Tạo hồ sơ thương hiệu mới'}</DialogTitle>
            <DialogDescription>
              Cấu hình các thông điệp cốt lõi để AI viết bài đúng văn phong và bám sát lợi thế cạnh tranh của doanh nghiệp.
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-4 py-3">
            {/* Tên & Tagline */}
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">
                  Tên thương hiệu <span className="text-destructive">*</span>
                </label>
                <Input
                  placeholder="Ví dụ: Nha Khoa Aura, SaaS Seo..."
                  value={formData.brand_name}
                  onChange={(e) => setFormData({ ...formData, brand_name: e.target.value })}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-medium">Khẩu hiệu / Slogan</label>
                <Input
                  placeholder="Ví dụ: Kiến tạo nụ cười rạng rỡ"
                  value={formData.tagline}
                  onChange={(e) => setFormData({ ...formData, tagline: e.target.value })}
                />
              </div>
            </div>

            {/* Tone of Voice */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Giọng văn chủ đạo (Tone of Voice)</label>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
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

            {/* Target Personas */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Khách hàng mục tiêu (Personas)</label>
              <div className="flex gap-2">
                <Input
                  placeholder="Nhập chân dung khách hàng rồi nhấn Thêm hoặc Enter..."
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

            {/* USPs */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Lợi thế cạnh tranh độc nhất (USPs)</label>
              <div className="flex gap-2">
                <Input
                  placeholder="Nhập USP nổi bật rồi nhấn Thêm hoặc Enter..."
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
                  <Badge key={idx} variant="secondary" className="gap-1 pr-1 text-xs bg-emerald-50 text-emerald-800 border-emerald-200">
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

            {/* Product Catalog */}
            <div className="space-y-2 rounded-lg border p-3">
              <div className="flex items-center gap-2">
                <Package className="h-4 w-4 text-primary" />
                <span className="text-sm font-medium">Sản phẩm / Dịch vụ nổi bật</span>
              </div>
              <div className="grid gap-2 sm:grid-cols-3">
                <Input
                  placeholder="Tên sản phẩm *"
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
                    placeholder="Link URL sản phẩm"
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

            {/* Taboo Words & Preferred Terms */}
            <div className="grid gap-3 sm:grid-cols-2">
              {/* Taboo Words */}
              <div className="space-y-1.5">
                <label className="text-sm font-medium text-destructive flex items-center gap-1">
                  <ShieldAlert className="h-4 w-4" />
                  Từ ngữ cấm kỵ (Taboo Words)
                </label>
                <div className="flex gap-2">
                  <Input
                    placeholder="Từ ngữ cấm xuất hiện..."
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

              {/* Preferred Terms */}
              <div className="space-y-1.5">
                <label className="text-sm font-medium text-primary flex items-center gap-1">
                  <ShieldCheck className="h-4 w-4" />
                  Thuật ngữ ưu tiên (Preferred Terms)
                </label>
                <div className="flex gap-2">
                  <Input
                    placeholder="Thuật ngữ nên dùng thường xuyên..."
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

            {/* Custom Rules */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Quy tắc viết văn riêng biệt (Custom Rules)</label>
              <Textarea
                rows={3}
                placeholder="Ví dụ: Luôn xưng hô Nha khoa Aura và Quý khách. Cuối bài luôn có lời kêu gọi đặt lịch khám..."
                value={formData.custom_rules}
                onChange={(e) => setFormData({ ...formData, custom_rules: e.target.value })}
              />
            </div>

            {/* Default Switch */}
            <div className="flex items-center gap-2 pt-1">
              <input
                type="checkbox"
                id="is_default"
                checked={formData.is_default}
                onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })}
                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
              />
              <label htmlFor="is_default" className="text-sm font-medium cursor-pointer">
                Đặt làm hồ sơ thương hiệu mặc định cho Website này
              </label>
            </div>
          </div>

          <DialogFooter className="gap-2">
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
              {editingId ? 'Lưu thay đổi' : 'Tạo hồ sơ'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
