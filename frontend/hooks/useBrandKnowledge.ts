import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface ProductCatalogItem {
  name: string;
  description?: string | null;
  url?: string | null;
}

export interface BusinessProfileInfo {
  company_name?: string;
  brand_name?: string;
  website_url?: string;
  primary_industry?: string;
  secondary_industry?: string;
  country_market?: string;
  service_area?: string;
  content_language?: string;
  business_model?: 'B2B' | 'B2C' | 'B2B2C' | 'Other' | string;
  company_intro?: string;
  company_history?: string;
  customer_locations?: string;
  problems_solved?: string;
  competitive_differentiation?: string;
  sales_policy?: string;
  warranty_policy?: string;
  contact_info?: string;
}

export interface EeatCredentialsInfo {
  expert_team?: string[];
  verified_qualifications?: string[];
  operating_license?: string;
  verified_achievements?: string[];
  partners_certifications?: string[];
  reference_sources?: string[];
  content_reviewer?: string;
}

export interface BrandOrientationInfo {
  writing_style?: string;
  brand_voice?: string;
  pronouns_addressing?: string;
  brand_positioning?: string;
  priority_cta?: string;
}

export interface IndustryProfileConfig {
  key: string;
  name: string;
  ymyl_level: 'high' | 'medium' | 'low';
  verification_requirement: 'strict' | 'high' | 'moderate' | 'standard';
  content_rules: string[];
  domain_terminology: string[];
  common_search_intents: string[];
  recommended_structures: Record<string, string[]>;
  sensitive_claims_to_control: string[];
  appropriate_ctas: string[];
  recommended_schemas: string[];
  required_disclaimer?: string | null;
}

export interface BrandKnowledgeBase {
  id: number;
  workspace_id: number;
  website_id: number;
  brand_name: string;
  tagline?: string | null;
  tone_of_voice: string;
  industry_key?: string;
  business_model?: string;
  target_personas?: string[] | null;
  unique_selling_points?: string[] | null;
  product_catalog?: ProductCatalogItem[] | null;
  taboo_words?: string[] | null;
  preferred_terms?: string[] | null;
  custom_rules?: string | null;
  business_profile?: BusinessProfileInfo | null;
  eeat_credentials?: EeatCredentialsInfo | null;
  brand_orientation?: BrandOrientationInfo | null;
  industry_profile?: IndustryProfileConfig | null;
  is_default: boolean;
  created_at: string;
  updated_at: string;
  website?: {
    id: number;
    name: string;
    domain: string;
    url?: string;
  };
}

export interface BrandKnowledgePayload {
  website_id: number;
  brand_name: string;
  tagline?: string;
  tone_of_voice?: string;
  industry_key?: string;
  business_model?: string;
  target_personas?: string[];
  unique_selling_points?: string[];
  product_catalog?: ProductCatalogItem[];
  taboo_words?: string[];
  preferred_terms?: string[];
  custom_rules?: string;
  business_profile?: BusinessProfileInfo;
  eeat_credentials?: EeatCredentialsInfo;
  brand_orientation?: BrandOrientationInfo;
  industry_profile?: Partial<IndustryProfileConfig>;
  is_default?: boolean;
}

export interface TabooValidationResult {
  clean: boolean;
  violations: Record<string, number>;
  taboo_count: number;
}

export interface InjectContextResult {
  context_text: string;
  injected_prompt: string;
}

export function useBrandKnowledgeList(websiteId?: number, filters?: { search?: string; tone_of_voice?: string }) {
  return useQuery({
    queryKey: ['brand-knowledge', websiteId, filters],
    queryFn: async (): Promise<BrandKnowledgeBase[]> => {
      const response = await apiClient.get<{ success: boolean; data: BrandKnowledgeBase[] }>('/brand-knowledge', {
        params: {
          website_id: websiteId,
          search: filters?.search,
          tone_of_voice: filters?.tone_of_voice,
        },
      });
      return response.data.data;
    },
  });
}

export function useIndustryProfiles() {
  return useQuery({
    queryKey: ['brand-knowledge-industries'],
    queryFn: async (): Promise<IndustryProfileConfig[]> => {
      const response = await apiClient.get<{ success: boolean; data: IndustryProfileConfig[] }>('/brand-knowledge/industries');
      return response.data.data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useDetectIndustry() {
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>): Promise<{ industry_key: string; industry_profile: IndustryProfileConfig }> => {
      const response = await apiClient.post<{ success: boolean; data: { industry_key: string; industry_profile: IndustryProfileConfig } }>(
        '/brand-knowledge/detect-industry',
        payload
      );
      return response.data.data;
    },
  });
}

export function useBrandKnowledgeDetail(id?: number) {
  return useQuery({
    queryKey: ['brand-knowledge', id],
    queryFn: async (): Promise<BrandKnowledgeBase | null> => {
      if (!id) return null;
      const response = await apiClient.get<{ success: boolean; data: BrandKnowledgeBase }>(`/brand-knowledge/${id}`);
      return response.data.data;
    },
    enabled: Boolean(id),
  });
}

export function useBrandKnowledgeActions() {
  const queryClient = useQueryClient();

  const invalidateBrandKnowledge = () => {
    queryClient.invalidateQueries({ queryKey: ['brand-knowledge'] });
    queryClient.invalidateQueries({ queryKey: ['websites'] });
  };

  return {
    create: useMutation({
      mutationFn: async (payload: BrandKnowledgePayload): Promise<BrandKnowledgeBase> => {
        const response = await apiClient.post<{ success: boolean; data: BrandKnowledgeBase }>('/brand-knowledge', payload);
        return response.data.data;
      },
      onSuccess: invalidateBrandKnowledge,
    }),

    update: useMutation({
      mutationFn: async ({ id, payload }: { id: number; payload: Partial<BrandKnowledgePayload> }): Promise<BrandKnowledgeBase> => {
        const response = await apiClient.put<{ success: boolean; data: BrandKnowledgeBase }>(`/brand-knowledge/${id}`, payload);
        return response.data.data;
      },
      onSuccess: invalidateBrandKnowledge,
    }),

    remove: useMutation({
      mutationFn: async (id: number): Promise<void> => {
        await apiClient.delete(`/brand-knowledge/${id}`);
      },
      onSuccess: invalidateBrandKnowledge,
    }),

    setDefault: useMutation({
      mutationFn: async (id: number): Promise<BrandKnowledgeBase> => {
        const response = await apiClient.post<{ success: boolean; data: BrandKnowledgeBase }>(`/brand-knowledge/${id}/set-default`);
        return response.data.data;
      },
      onSuccess: invalidateBrandKnowledge,
    }),
  };
}

export function useInjectBrandContext() {
  return useMutation({
    mutationFn: async (params: { website_id: number; brand_knowledge_id?: number; prompt?: string }): Promise<InjectContextResult> => {
      const response = await apiClient.post<{ success: boolean; data: InjectContextResult }>('/brand-knowledge/inject-context', params);
      return response.data.data;
    },
  });
}

export function useValidateTabooWords() {
  return useMutation({
    mutationFn: async (params: { website_id: number; content: string; brand_knowledge_id?: number }): Promise<TabooValidationResult> => {
      const response = await apiClient.post<{ success: boolean; data: TabooValidationResult }>('/brand-knowledge/validate-taboo', params);
      return response.data.data;
    },
  });
}
