import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export type ChatMode = 'general' | 'research' | 'writing' | 'audit' | 'bulk';
export type MessageRole = 'user' | 'assistant' | 'system' | 'tool';
export type MessageStatus = 'completed' | 'pending_action' | 'running' | 'failed';

export interface ToolCallPayload {
  id: string;
  tool: string;
  parameters?: Record<string, unknown>;
  status: 'pending' | 'running' | 'completed' | 'failed';
  result?: Record<string, unknown>;
}

export interface MessageImagePayload {
  id?: string;
  previewUrl?: string;
  name?: string;
  size?: number;
  mimeType?: string;
}

export interface MessageMetadata {
  actionType?: 'confirmation_needed' | 'task_progress' | 'article_preview';
  taskId?: string;
  taskProgress?: number;
  taskStep?: string;
  articleId?: number;
  articleTitle?: string;
  seoScore?: number;
  wordCount?: number;
  creditCost?: number;
  targetWebsite?: string;
  images?: MessageImagePayload[];
  [key: string]: unknown;
}

export interface ApiConversation {
  id: number;
  uuid: string;
  workspace_id: number;
  website_id: number | null;
  user_id: number;
  title: string;
  context_summary: string | null;
  current_mode: ChatMode;
  pinned: boolean;
  last_message_at: string | null;
  messages_count?: number;
  created_at: string;
  updated_at: string;
  website?: {
    id: number;
    name: string;
    url: string;
  } | null;
  user?: {
    id: number;
    name: string;
    email: string;
  } | null;
}

export interface ApiConversationMessage {
  id: number;
  uuid: string;
  conversation_id: number;
  workspace_id: number;
  user_id: number | null;
  role: MessageRole;
  content: string;
  tool_calls: ToolCallPayload[] | null;
  tool_results: Record<string, unknown>[] | null;
  metadata: MessageMetadata | null;
  action_type: string | null;
  status: MessageStatus;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  } | null;
}

export interface CreateConversationPayload {
  title?: string;
  website_id?: number | null;
  current_mode?: ChatMode;
  context_summary?: string | null;
  pinned?: boolean;
}

export interface UpdateConversationPayload {
  title?: string;
  website_id?: number | null;
  current_mode?: ChatMode;
  context_summary?: string | null;
  pinned?: boolean;
}

export interface SendMessagePayload {
  role?: MessageRole;
  content: string;
  images?: MessageImagePayload[];
  tool_calls?: ToolCallPayload[];
  tool_results?: Record<string, unknown>[];
  metadata?: MessageMetadata;
  action_type?: string;
  status?: MessageStatus;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  meta?: Record<string, unknown>;
}

export function useConversations(params?: {
  website_id?: number;
  search?: string;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['conversations', params?.website_id, params?.search, params?.per_page],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<ApiConversation[]>>(
        '/conversations',
        { params }
      );
      return data;
    },
    staleTime: 5000,
  });
}

export function useConversation(uuid?: string | null) {
  return useQuery({
    queryKey: ['conversation', uuid],
    queryFn: async () => {
      if (!uuid) throw new Error('Missing conversation UUID');
      const { data } = await apiClient.get<ApiResponse<{
        conversation: ApiConversation;
        context_summary: string | null;
        current_mode: ChatMode;
        recent_messages: ApiConversationMessage[];
      }>>(`/conversations/${uuid}`);
      return data;
    },
    enabled: Boolean(uuid),
  });
}

export function useConversationMessages(uuid?: string | null, limit = 50) {
  return useQuery({
    queryKey: ['conversation-messages', uuid, limit],
    queryFn: async () => {
      if (!uuid) throw new Error('Missing conversation UUID');
      const { data } = await apiClient.get<ApiResponse<ApiConversationMessage[]>>(
        `/conversations/${uuid}/messages`,
        { params: { limit } }
      );
      return data;
    },
    enabled: Boolean(uuid),
    staleTime: 2000,
  });
}

export function useChatbotActions() {
  const queryClient = useQueryClient();

  const createConversation = useMutation({
    mutationFn: async (payload: CreateConversationPayload) => {
      const response = await apiClient.post<ApiResponse<ApiConversation>>(
        '/conversations',
        payload
      );
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
    },
  });

  const updateConversation = useMutation({
    mutationFn: async ({
      uuid,
      data,
    }: {
      uuid: string;
      data: UpdateConversationPayload;
    }) => {
      const response = await apiClient.put<ApiResponse<ApiConversation>>(
        `/conversations/${uuid}`,
        data
      );
      return response.data;
    },
    onSuccess: (_, { uuid }) => {
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
      queryClient.invalidateQueries({ queryKey: ['conversation', uuid] });
    },
  });

  const deleteConversation = useMutation({
    mutationFn: async (uuid: string) => {
      const response = await apiClient.delete<ApiResponse<null>>(
        `/conversations/${uuid}`
      );
      return response.data;
    },
    onSuccess: (_, uuid) => {
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
      queryClient.removeQueries({ queryKey: ['conversation', uuid] });
      queryClient.removeQueries({ queryKey: ['conversation-messages', uuid] });
    },
  });

  const sendMessage = useMutation({
    mutationFn: async ({
      uuid,
      payload,
    }: {
      uuid: string;
      payload: SendMessagePayload;
    }) => {
      const response = await apiClient.post<ApiResponse<ApiConversationMessage>>(
        `/conversations/${uuid}/messages`,
        payload
      );
      return response.data;
    },
    onSuccess: (_, { uuid }) => {
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
      queryClient.invalidateQueries({
        queryKey: ['conversation-messages', uuid],
      });
      queryClient.invalidateQueries({ queryKey: ['conversation', uuid] });
    },
  });

  const clearMessages = useMutation({
    mutationFn: async (uuid: string) => {
      const response = await apiClient.post<ApiResponse<null>>(
        `/conversations/${uuid}/clear`
      );
      return response.data;
    },
    onSuccess: (_, uuid) => {
      queryClient.invalidateQueries({
        queryKey: ['conversation-messages', uuid],
      });
      queryClient.invalidateQueries({ queryKey: ['conversation', uuid] });
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
    },
  });

  return {
    createConversation,
    updateConversation,
    deleteConversation,
    sendMessage,
    clearMessages,
  };
}
