'use client';

import React, { useState, useMemo } from 'react';
import {
  Plus,
  Search,
  MessageSquare,
  Pin,
  Trash2,
  Globe,
  Building2,
  ChevronDown,
  Sparkles,
  Check,
  X,
  Layers,
  PenTool,
  CheckCircle2,
  SearchCode,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthStore } from '@/stores/authStore';
import { useWordPressSites } from '@/hooks/useWordPressSites';
import { cn } from '@/lib/utils';

export interface ConversationItem {
  id: string;
  uuid: string;
  title: string;
  workspaceId: number;
  websiteId?: number;
  websiteName?: string;
  websiteUrl?: string;
  mode: 'general' | 'research' | 'writing' | 'audit' | 'bulk';
  pinned: boolean;
  lastMessageAt: string;
  messageCount: number;
}

interface ChatSidebarProps {
  conversations: ConversationItem[];
  activeConversationId: string | null;
  onSelectConversation: (id: string) => void;
  onNewConversation: () => void;
  onTogglePin: (id: string) => void;
  onDeleteConversation: (id: string) => void;
  selectedWebsiteId?: number;
  onSelectWebsite?: (websiteId: number) => void;
  className?: string;
  onCloseMobile?: () => void;
}

export function ChatSidebar({
  conversations,
  activeConversationId,
  onSelectConversation,
  onNewConversation,
  onTogglePin,
  onDeleteConversation,
  selectedWebsiteId,
  onSelectWebsite,
  className,
  onCloseMobile,
}: ChatSidebarProps) {
  const [searchQuery, setSearchQuery] = useState('');

  // Tenancy Store
  const user = useAuthStore((state) => state.user);
  const activeWorkspaceId = useAuthStore((state) => state.activeWorkspaceId);
  const setActiveWorkspaceId = useAuthStore(
    (state) => state.setActiveWorkspaceId
  );

  // WordPress Sites Hook
  const { data: sitesData, isLoading: isLoadingSites } = useWordPressSites();
  const sites = sitesData?.data || [];

  const activeWorkspace = user?.workspaces?.find(
    (ws) => ws.id === activeWorkspaceId
  ) || {
    id: activeWorkspaceId || 1,
    name: 'Workspace mặc định',
    slug: 'default',
    status: 'active',
  };

  const activeWebsite = sites.find((s) => s.id === selectedWebsiteId) || sites[0];

  // Filter conversations
  const filteredConversations = useMemo(() => {
    return conversations.filter((c) =>
      c.title.toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [conversations, searchQuery]);

  const pinnedConversations = useMemo(
    () => filteredConversations.filter((c) => c.pinned),
    [filteredConversations]
  );

  const unpinnedConversations = useMemo(
    () => filteredConversations.filter((c) => !c.pinned),
    [filteredConversations]
  );

  const getModeBadge = (mode: ConversationItem['mode']) => {
    switch (mode) {
      case 'research':
        return <Badge variant="secondary" className="text-[10px] h-4 py-0">Nghiên cứu</Badge>;
      case 'writing':
        return <Badge variant="secondary" className="text-[10px] h-4 py-0">Viết bài</Badge>;
      case 'audit':
        return <Badge variant="secondary" className="text-[10px] h-4 py-0">SEO Audit</Badge>;
      case 'bulk':
        return <Badge variant="secondary" className="text-[10px] h-4 py-0">Bulk</Badge>;
      default:
        return null;
    }
  };

  return (
    <aside
      className={cn(
        'flex h-full w-full flex-col border-r border-border bg-card/60 backdrop-blur-xs select-none',
        className
      )}
    >
      {/* Top Header: Tenancy Selectors */}
      <div className="flex flex-col gap-2 border-b border-border p-3">
        {/* Mobile close button */}
        {onCloseMobile && (
          <div className="flex items-center justify-between md:hidden pb-1">
            <span className="text-xs font-semibold text-muted-foreground uppercase">
              Menu Hội Thoại
            </span>
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              onClick={onCloseMobile}
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        )}

        {/* Workspace Selector */}
        <div className="flex items-center justify-between">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="flex items-center gap-2 rounded-lg border border-border/80 bg-background/80 px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60 transition-colors w-full justify-between shadow-2xs"
              >
                <div className="flex items-center gap-2 truncate">
                  <Building2 className="h-3.5 w-3.5 text-purple-600 shrink-0" />
                  <span className="truncate">{activeWorkspace.name}</span>
                </div>
                <ChevronDown className="h-3 w-3 text-muted-foreground shrink-0" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="start">
              <DropdownMenuLabel className="text-xs">Chuyển Workspace</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {user?.workspaces && user.workspaces.length > 0 ? (
                user.workspaces.map((ws) => (
                  <DropdownMenuItem
                    key={ws.id}
                    onClick={() => setActiveWorkspaceId(ws.id)}
                    className="text-xs flex items-center justify-between cursor-pointer"
                  >
                    <span>{ws.name}</span>
                    {ws.id === activeWorkspaceId && (
                      <Check className="h-3.5 w-3.5 text-primary" />
                    )}
                  </DropdownMenuItem>
                ))
              ) : (
                <DropdownMenuItem disabled className="text-xs">
                  {activeWorkspace.name} (Hiện tại)
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        {/* Website Selector */}
        <div className="flex items-center justify-between">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="flex items-center gap-2 rounded-lg border border-border/80 bg-background/80 px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60 transition-colors w-full justify-between shadow-2xs"
              >
                <div className="flex items-center gap-2 truncate">
                  <Globe className="h-3.5 w-3.5 text-blue-600 shrink-0" />
                  <span className="truncate">
                    {activeWebsite ? activeWebsite.name : 'Chưa kết nối Website'}
                  </span>
                </div>
                <ChevronDown className="h-3 w-3 text-muted-foreground shrink-0" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-64" align="start">
              <DropdownMenuLabel className="text-xs">
                Website WordPress Đích
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              {isLoadingSites ? (
                <div className="p-2 text-xs text-muted-foreground text-center">
                  Đang tải danh sách site...
                </div>
              ) : sites.length > 0 ? (
                sites.map((site) => (
                  <DropdownMenuItem
                    key={site.id}
                    onClick={() => onSelectWebsite?.(site.id)}
                    className="text-xs flex items-center justify-between cursor-pointer"
                  >
                    <div className="flex flex-col truncate pr-2">
                      <span className="font-medium truncate">{site.name}</span>
                      <span className="text-[10px] text-muted-foreground truncate">
                        {site.url}
                      </span>
                    </div>
                    {site.id === (selectedWebsiteId || sites[0]?.id) && (
                      <Check className="h-3.5 w-3.5 text-primary shrink-0" />
                    )}
                  </DropdownMenuItem>
                ))
              ) : (
                <DropdownMenuItem disabled className="text-xs">
                  Chưa có website WordPress nào
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        {/* New Chat Button */}
        <Button
          type="button"
          onClick={onNewConversation}
          className="w-full gap-2 text-xs font-semibold h-8.5 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 mt-1"
        >
          <Plus className="h-4 w-4" />
          Tạo Cuộc Trò Chuyện Mới
        </Button>
      </div>

      {/* Search Input */}
      <div className="p-3 pb-2">
        <div className="relative">
          <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
          <Input
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Tìm kiếm hội thoại..."
            className="h-8 pl-8 text-xs bg-background/60"
          />
          {searchQuery && (
            <button
              type="button"
              onClick={() => setSearchQuery('')}
              className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
            >
              <X className="h-3 w-3" />
            </button>
          )}
        </div>
      </div>

      {/* Conversation List Scroll Area */}
      <div className="flex-1 overflow-y-auto px-2 py-1 space-y-4">
        {/* Pinned Section */}
        {pinnedConversations.length > 0 && (
          <div className="space-y-1">
            <div className="flex items-center gap-1 px-2 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
              <Pin className="h-3 w-3 text-amber-500 rotate-45" />
              <span>Đã Ghim ({pinnedConversations.length})</span>
            </div>
            {pinnedConversations.map((conv) => {
              const isActive = conv.id === activeConversationId;
              return (
                <div
                  key={conv.id}
                  onClick={() => onSelectConversation(conv.id)}
                  className={cn(
                    'group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-2 text-xs transition-colors',
                    isActive
                      ? 'bg-primary text-primary-foreground font-medium shadow-2xs'
                      : 'text-foreground hover:bg-muted/80'
                  )}
                >
                  <div className="flex items-center gap-2 truncate pr-2">
                    <MessageSquare
                      className={cn(
                        'h-3.5 w-3.5 shrink-0',
                        isActive ? 'text-primary-foreground' : 'text-purple-600'
                      )}
                    />
                    <div className="truncate">
                      <p className="truncate leading-snug">{conv.title}</p>
                      <span
                        className={cn(
                          'text-[10px]',
                          isActive ? 'text-primary-foreground/80' : 'text-muted-foreground'
                        )}
                      >
                        {conv.lastMessageAt}
                      </span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        onTogglePin(conv.id);
                      }}
                      className={cn(
                        'p-1 rounded-md hover:bg-black/10',
                        isActive ? 'text-primary-foreground' : 'text-muted-foreground'
                      )}
                      title="Bỏ ghim"
                    >
                      <Pin className="h-3 w-3 text-amber-500 fill-amber-500" />
                    </button>
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        onDeleteConversation(conv.id);
                      }}
                      className={cn(
                        'p-1 rounded-md hover:bg-destructive/20 hover:text-destructive',
                        isActive ? 'text-primary-foreground' : 'text-muted-foreground'
                      )}
                      title="Xóa hội thoại"
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Recent Conversations */}
        <div className="space-y-1">
          <div className="flex items-center gap-1 px-2 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
            <span>Hội Thoại Gần Đây ({unpinnedConversations.length})</span>
          </div>

          {unpinnedConversations.length === 0 && pinnedConversations.length === 0 ? (
            <div className="py-6 text-center text-xs text-muted-foreground">
              {searchQuery ? 'Không tìm thấy cuộc trò chuyện phù hợp.' : 'Chưa có cuộc trò chuyện nào.'}
            </div>
          ) : (
            unpinnedConversations.map((conv) => {
              const isActive = conv.id === activeConversationId;
              return (
                <div
                  key={conv.id}
                  onClick={() => onSelectConversation(conv.id)}
                  className={cn(
                    'group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-2 text-xs transition-colors',
                    isActive
                      ? 'bg-primary text-primary-foreground font-medium shadow-2xs'
                      : 'text-foreground hover:bg-muted/80'
                  )}
                >
                  <div className="flex items-center gap-2 truncate pr-2">
                    <MessageSquare
                      className={cn(
                        'h-3.5 w-3.5 shrink-0',
                        isActive ? 'text-primary-foreground' : 'text-muted-foreground'
                      )}
                    />
                    <div className="truncate">
                      <p className="truncate leading-snug">{conv.title}</p>
                      <div className="flex items-center gap-1.5">
                        <span
                          className={cn(
                            'text-[10px]',
                            isActive ? 'text-primary-foreground/80' : 'text-muted-foreground'
                          )}
                        >
                          {conv.lastMessageAt}
                        </span>
                        {!isActive && getModeBadge(conv.mode)}
                      </div>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        onTogglePin(conv.id);
                      }}
                      className={cn(
                        'p-1 rounded-md hover:bg-black/10',
                        isActive ? 'text-primary-foreground' : 'text-muted-foreground'
                      )}
                      title="Ghim hội thoại"
                    >
                      <Pin className="h-3 w-3" />
                    </button>
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        onDeleteConversation(conv.id);
                      }}
                      className={cn(
                        'p-1 rounded-md hover:bg-destructive/20 hover:text-destructive',
                        isActive ? 'text-primary-foreground' : 'text-muted-foreground'
                      )}
                      title="Xóa hội thoại"
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>

      {/* Footer Model info */}
      <div className="border-t border-border p-3 text-[11px] text-muted-foreground bg-muted/20 flex items-center justify-between">
        <div className="flex items-center gap-1.5">
          <Sparkles className="h-3.5 w-3.5 text-purple-600" />
          <span className="font-semibold text-foreground">Aura Assistant v2.5</span>
        </div>
        <Badge variant="outline" className="text-[9px] py-0 px-1.5 h-4 border-purple-300 text-purple-700 bg-purple-50">
          Gemini Flash
        </Badge>
      </div>
    </aside>
  );
}
