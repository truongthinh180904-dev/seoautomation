import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface ArticleFilter {
  page: number;
  status?: string;
  search?: string;
  wp_site_id?: number;
  per_page?: number;
}

export function useArticles(filters: ArticleFilter) {
  return useQuery({
    queryKey: ['articles', filters],
    queryFn: async () => {
      const { data } = await apiClient.get('/articles', { params: filters });
      return data;
    },
  });
}

export function useArticle(id: number) {
  return useQuery({
    queryKey: ['article', id],
    queryFn: async () => {
      const { data } = await apiClient.get(`/articles/${id}`);
      return data;
    },
    enabled: !!id,
  });
}

export function useArticleActions() {
  const queryClient = useQueryClient();

  const updateArticle = useMutation({
    mutationFn: async ({ id, data }: { id: number, data: ArticleUpdatePayload }) => {
      await apiClient.put(`/articles/${id}`, data);
    },
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['article', id] });
      queryClient.invalidateQueries({ queryKey: ['articles'] });
    },
  });

  const deleteArticle = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/articles/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['articles'] });
    },
  });

  const retryArticle = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.post(`/articles/${id}/retry`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['articles'] });
    },
  });

  const generateArticle = useMutation({
    mutationFn: async (keywordId: number) => {
      await apiClient.post('/articles/generate', { keyword_id: keywordId });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['articles'] });
      queryClient.invalidateQueries({ queryKey: ['keywords'] });
    },
  });

  return { updateArticle, deleteArticle, retryArticle, generateArticle };
}
