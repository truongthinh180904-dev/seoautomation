'use client';

import React, { useState, useMemo } from 'react';
import {
  PanelLeft,
  PanelRight,
  Sparkles,
  Globe,
  Building2,
  CheckCircle2,
  Clock,
  CreditCard,
  Layers,
  Search,
  FileText,
  TrendingUp,
  Bot,
  ExternalLink,
  ChevronRight,
  ShieldCheck,
  Zap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChatSidebar, ConversationItem } from '@/components/features/chatbot/ChatSidebar';
import { ChatMessageList } from '@/components/features/chatbot/ChatMessageList';
import { ChatMessage } from '@/components/features/chatbot/ChatMessageItem';
import { ChatInputBox } from '@/components/features/chatbot/ChatInputBox';
import { UploadedImage } from '@/components/features/chatbot/ChatImageUploadDropzone';
import { useAuthStore } from '@/stores/authStore';
import { useWordPressSites } from '@/hooks/useWordPressSites';
import { toast } from 'sonner';
import Link from 'next/link';
import { cn } from '@/lib/utils';

type ChatMode = 'general' | 'research' | 'writing' | 'audit' | 'bulk';

interface TaskWidgetInfo {
  id: string;
  name: string;
  progress: number;
  status: 'running' | 'completed' | 'queued';
  step: string;
}

const INITIAL_CONVERSATIONS: ConversationItem[] = [
  {
    id: 'conv-1',
    uuid: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
    title: 'Nghiên cứu từ khóa & Phân tích Top 3 SERP',
    workspaceId: 1,
    websiteId: 1,
    websiteName: 'Nha Khoa Smile Tech',
    websiteUrl: 'https://nhakhoasmile.vn',
    mode: 'research',
    pinned: true,
    lastMessageAt: 'Vừa xong',
    messageCount: 4,
  },
  {
    id: 'conv-2',
    uuid: '8c2cfb3e-4c8e-5cbe-8cee-3c1e8c4edc7e',
    title: 'Viết bài SEO Trồng Răng Implant Toàn Hàm',
    workspaceId: 1,
    websiteId: 1,
    websiteName: 'Nha Khoa Smile Tech',
    websiteUrl: 'https://nhakhoasmile.vn',
    mode: 'writing',
    pinned: false,
    lastMessageAt: '2 giờ trước',
    messageCount: 6,
  },
  {
    id: 'conv-3',
    uuid: '7d3dfa2f-5d9f-6daf-7dff-4d2f9d5fed8f',
    title: 'Audit SEO 100 Điểm & Tối ưu thẻ Heading',
    workspaceId: 1,
    websiteId: 1,
    websiteName: 'Nha Khoa Smile Tech',
    websiteUrl: 'https://nhakhoasmile.vn',
    mode: 'audit',
    pinned: false,
    lastMessageAt: 'Hôm qua',
    messageCount: 3,
  },
];

const INITIAL_MESSAGES: Record<string, ChatMessage[]> = {
  'conv-1': [
    {
      id: 'm-1',
      uuid: 'msg-1',
      role: 'user',
      content:
        'Phân tích Top 3 đối thủ trên Google SERP cho từ khóa "giá bọc răng sứ thẩm mỹ" và đề xuất chiến lược vượt trội đối thủ #1.',
      createdAt: '10:14',
    },
    {
      id: 'm-2',
      uuid: 'msg-2',
      role: 'assistant',
      content: `Dựa trên dữ liệu SERP thời gian thực được thu thập từ Google, tôi đã phân tích chuyên sâu **Top 3 đối thủ đứng đầu** và lấy **Đối thủ #1 (Anchor Benchmark)** làm thước đo chuẩn xác:

### 1. Bảng Đối Soát Top 3 Đối Thủ Google SERP

| Vị Trí | Tiêu Đề URL Đối Thủ | Số Từ | Điểm SEO | Thẻ H2/H3 | Mật Độ Thực Thể LSI |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **#1 (Mỏ Neo)** | benhvienranghammat.vn/gia-boc-rang-su | 2.450 | 91/100 | 8 H2, 14 H3 | 1.8% (răng toàn sứ, zirconia, cercon) |
| **#2** | nhakhoaparis.vn/bang-gia-rang-su | 1.820 | 84/100 | 6 H2, 9 H3 | 1.4% (bảo hành, tuổi thọ răng sứ) |
| **#3** | nhakhoakim.com/chi-phi-boc-rang | 2.100 | 87/100 | 7 H2, 11 H3 | 1.6% (quy trình bọc răng, bác sĩ) |

### 2. Chiến Lược Vượt Trội Đối Thủ #1
- **Độ dài bài viết mục tiêu:** Đạt tối thiểu **2.600 - 2.800 từ** để cung cấp chiều sâu vượt trội đối thủ #1.
- **Thực thể LSI còn thiếu (Content Gap):** Đối thủ #1 chưa đề cập chi tiết đến *quy trình vô trùng chuẩn Bộ Y Tế* và *chính sách trả góp 0%*. Bổ sung 2 thực thể này sẽ nâng điểm EEAT lên mức tối đa.
- **Tối ưu hình ảnh:** Đối thủ dùng ảnh stock chất lượng thấp. Chúng ta sẽ sử dụng ảnh người dùng gửi kèm (qua Vision AI ghim vào H2) kết hợp ảnh stock WebP nén <150KB.

Bạn có muốn tôi tiến hành **Lập dàn ý chi tiết 8 Heading H2** ngay cho bài viết này không?`,
      createdAt: '10:15',
      toolCalls: [
        {
          id: 'tc-1',
          tool: 'competitor_analysis (Top 3 Benchmark)',
          status: 'completed',
        },
        {
          id: 'tc-2',
          tool: 'content_gap (LSI Gap Matrix)',
          status: 'completed',
        },
      ],
      metadata: {
        seoScore: 91,
        wordCount: 2450,
      },
    },
  ],
};

export default function ChatbotPage() {
  const user = useAuthStore((state) => state.user);
  const activeWorkspaceId = useAuthStore((state) => state.activeWorkspaceId);

  // WordPress Sites hook
  const { data: sitesData } = useWordPressSites();
  const sites = sitesData?.data || [];

  const [conversations, setConversations] =
    useState<ConversationItem[]>(INITIAL_CONVERSATIONS);
  const [activeConversationId, setActiveConversationId] =
    useState<string>('conv-1');
  const [messagesByConv, setMessagesByConv] =
    useState<Record<string, ChatMessage[]>>(INITIAL_MESSAGES);
  const [selectedWebsiteId, setSelectedWebsiteId] = useState<number | undefined>(
    sites[0]?.id || 1
  );
  const [currentMode, setCurrentMode] = useState<ChatMode>('general');
  const [isAiLoading, setIsAiLoading] = useState(false);

  // Sidebar visibility
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);
  const [isRightSidebarOpen, setIsRightSidebarOpen] = useState(true);

  // Active conversation object
  const activeConversation = useMemo(() => {
    return (
      conversations.find((c) => c.id === activeConversationId) ||
      conversations[0]
    );
  }, [conversations, activeConversationId]);

  // Current conversation messages
  const currentMessages = useMemo(() => {
    return messagesByConv[activeConversationId] || [];
  }, [messagesByConv, activeConversationId]);

  // Active website
  const activeWebsite = useMemo(() => {
    return sites.find((s) => s.id === selectedWebsiteId) || sites[0];
  }, [sites, selectedWebsiteId]);

  // Mock Active Background Tasks for Right Sidebar
  const activeTasks: TaskWidgetInfo[] = [
    {
      id: 'task-1',
      name: 'Phân tích Top 3 SERP đối thủ',
      progress: 100,
      status: 'completed',
      step: 'Đã hoàn thành phân tích benchmark',
    },
    {
      id: 'task-2',
      name: 'Nén WebP & Gán ALT cho 3 ảnh Vision',
      progress: 75,
      status: 'running',
      step: 'Đang nén WebP dưới 150KB bằng GD',
    },
  ];

  // Handler: Send Message
  const handleSendMessage = (content: string, images: UploadedImage[]) => {
    const userMessageUuid = `msg-${Date.now()}`;
    const userMessage: ChatMessage = {
      id: userMessageUuid,
      uuid: userMessageUuid,
      role: 'user',
      content,
      createdAt: new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
      }),
      images: images.map((img) => ({
        id: img.id,
        previewUrl: img.previewUrl,
        name: img.name,
        size: img.size,
        mimeType: img.mimeType,
      })),
    };

    // Append user message
    setMessagesByConv((prev) => ({
      ...prev,
      [activeConversationId]: [...(prev[activeConversationId] || []), userMessage],
    }));

    // Update conversation metadata
    setConversations((prev) =>
      prev.map((c) =>
        c.id === activeConversationId
          ? {
              ...c,
              lastMessageAt: 'Vừa xong',
              messageCount: c.messageCount + 1,
              title:
                c.messageCount === 0 && content
                  ? content.slice(0, 45) + '...'
                  : c.title,
            }
          : c
      )
    );

    setIsAiLoading(true);

    // Simulate AI Multi-step reasoning & tool execution response
    setTimeout(() => {
      const aiMessageUuid = `msg-${Date.now() + 1}`;
      let aiResponseContent = '';
      let toolCalls: ChatMessage['toolCalls'] = undefined;
      let metadata: ChatMessage['metadata'] = undefined;

      const lowerContent = content.toLowerCase();

      if (images.length > 0 || lowerContent.includes('ảnh') || lowerContent.includes('vision')) {
        aiResponseContent = `Tôi đã tiếp nhận **${images.length || 1} hình ảnh** bạn cung cấp và kích hoạt **AI Gemini Vision** để phân tích:

### 1. Kết Quả Phân Tích Hình Ảnh (Gemini Vision)
- **Nhận diện chủ thể:** Bác sĩ nha khoa đang tư vấn mẫu răng toàn sứ Cercon HT trong phòng điều trị tiêu chuẩn vô trùng.
- **Vị trí đề xuất ghim bài:** **Mục H2: "Quy Trình Khám & Bọc Răng Sứ Tiêu Chuẩn Quốc Tế"**.
- **Thẻ ALT chuẩn SEO đề xuất:** \`alt="Bac si tu van mau rang toan su Cercon HT cao cap tai Nha Khoa Smile"\`
- **Tối ưu dung lượng:** Đã tự động nén định dạng **WebP kích thước 128KB** (đáp ứng tiêu chuẩn tải nhanh Core Web Vitals <150KB).

Hình ảnh đã được ghim thành công vào bài viết nháp trên hệ thống!`;
        toolCalls = [
          { id: 'tc-img-1', tool: 'chat_image_vision (Gemini Vision)', status: 'completed' },
          { id: 'tc-img-2', tool: 'image_optimization (WebP Converter <150KB)', status: 'completed' },
        ];
      } else if (lowerContent.includes('chấm điểm') || lowerContent.includes('audit')) {
        aiResponseContent = `Tôi đã chạy bộ kiểm tra **SeoScoringEngine (100 tiêu chí thuần PHP)** cho nội dung của bạn:

### Kết Quả Kiểm Tra SEO On-Page (Điểm Số: 92/100 - XUẤT SẮC)

| Hạng Mục Kiểm Tra | Đánh Giá | Chi Tiết Kỹ Thuật |
| :--- | :--- | :--- |
| **Tiêu đề (Title Tag)** | Đạt 10/10 | Chứa từ khóa chính ở đầu, độ dài 58 ký tự chuẩn SERP. |
| **Thẻ Heading H1-H3** | Đạt 20/20 | 1 thẻ H1 duy nhất, 7 thẻ H2 và 12 thẻ H3 phân tầng hợp lý. |
| **Mật độ từ khóa LSI** | Đạt 18/20 | Mật độ 1.7% phân bổ tự nhiên, không nhồi nhét. |
| **Hình ảnh & Thẻ ALT** | Đạt 19/20 | 100% ảnh có thẻ ALT, dung lượng bình quân 115KB WebP. |
| **Liên kết nội bộ (SILO)** | Đạt 15/20 | Có 4 internal links dẫn về dịch vụ cấy ghép và bảng giá. |
| **Tính dễ đọc (Flesch-Kincaid)** | Đạt 10/10 | Câu văn ngắn gọn, đoạn văn không quá 4 dòng. |

**Khuyến nghị:** Bạn có thể thêm 1 liên kết ngoại (Outbound link) đến trang nghiên cứu y khoa uy tín để đạt 100/100 điểm.`;
        toolCalls = [{ id: 'tc-score', tool: 'seo_scoring_engine (100 Tiêu chí)', status: 'completed' }];
        metadata = { seoScore: 92, wordCount: 2200 };
      } else if (lowerContent.includes('đăng') || lowerContent.includes('wordpress') || lowerContent.includes('publish')) {
        aiResponseContent = `Bài viết đã hoàn thiện và đạt tiêu chuẩn SEO 92/100. Theo quy trình **Human-in-the-Loop**, tôi đã tạo yêu cầu xuất bản chờ bạn phê duyệt bên dưới:`;
        metadata = {
          actionType: 'confirmation_needed',
          targetWebsite: activeWebsite ? activeWebsite.name : 'Nha Khoa Smile Tech',
          creditCost: 0,
        };
      } else {
        aiResponseContent = `Tôi đã nhận lệnh và tiến hành phân tích theo tiêu chuẩn **Senior SEO Specialist**:

### Kế Hoạch Triển Khai Đề Xuất:
1. **Thu thập dữ liệu:** Quét SERP đối thủ Top 3 hiện tại để lấy cấu trúc bài viết dẫn đầu.
2. **Xây dựng Outline:** Thiết lập 7 phần H2 chính bao trùm toàn bộ search intent của người dùng.
3. **Chiến lược Media 3 tầng:**
   - Ưu tiên #1: Ghim ảnh bạn gửi qua Vision AI vào đúng vị trí H2/H3.
   - Ưu tiên #2: Lấy ảnh stock Pexels/Unsplash không bản quyền.
   - Ưu tiên #3: Sinh ảnh AI Gemini nếu không có ảnh stock.
   - 100% ảnh đều được nén WebP <150KB.

Bạn muốn tôi tạo bài viết nháp ngay bây giờ hay muốn tinh chỉnh thêm từ khóa phụ?`;
      }

      const aiMessage: ChatMessage = {
        id: aiMessageUuid,
        uuid: aiMessageUuid,
        role: 'assistant',
        content: aiResponseContent,
        createdAt: new Date().toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit',
        }),
        toolCalls,
        metadata,
      };

      setMessagesByConv((prev) => ({
        ...prev,
        [activeConversationId]: [...(prev[activeConversationId] || []), aiMessage],
      }));
      setIsAiLoading(false);
    }, 1200);
  };

  // Handler: Create New Conversation
  const handleNewConversation = () => {
    const newId = `conv-${Date.now()}`;
    const newConv: ConversationItem = {
      id: newId,
      uuid: `uuid-${Date.now()}`,
      title: 'Cuộc trò chuyện mới',
      workspaceId: activeWorkspaceId || 1,
      websiteId: selectedWebsiteId,
      websiteName: activeWebsite?.name,
      websiteUrl: activeWebsite?.url,
      mode: currentMode,
      pinned: false,
      lastMessageAt: 'Vừa xong',
      messageCount: 0,
    };

    setConversations((prev) => [newConv, ...prev]);
    setActiveConversationId(newId);
    setMessagesByConv((prev) => ({
      ...prev,
      [newId]: [],
    }));
    setIsMobileSidebarOpen(false);
    toast.success('Đã tạo cuộc trò chuyện mới');
  };

  // Handler: Pin/Unpin
  const handleTogglePin = (id: string) => {
    setConversations((prev) =>
      prev.map((c) => (c.id === id ? { ...c, pinned: !c.pinned } : c))
    );
  };

  // Handler: Delete Conversation
  const handleDeleteConversation = (id: string) => {
    if (conversations.length <= 1) {
      toast.error('Cần giữ lại ít nhất một cuộc trò chuyện.');
      return;
    }

    setConversations((prev) => prev.filter((c) => c.id !== id));
    if (activeConversationId === id) {
      const remaining = conversations.filter((c) => c.id !== id);
      setActiveConversationId(remaining[0]?.id || '');
    }
    toast.success('Đã xóa cuộc trò chuyện');
  };

  // Handler: Approve Sensitive Action (P2.5.7 Human-in-the-Loop)
  const handleApproveAction = (actionId?: string) => {
    toast.success('Đã phê duyệt xuất bản bài viết lên WordPress thành công!');
    setMessagesByConv((prev) => {
      const convMessages = prev[activeConversationId] || [];
      return {
        ...prev,
        [activeConversationId]: convMessages.map((msg) =>
          msg.uuid === actionId
            ? {
                ...msg,
                content:
                  msg.content +
                  '\n\n> [!NOTE]\n> **ĐÃ PHÊ DUYỆT:** Bài viết đã được lên lịch xuất bản thành công lên website WordPress đích.',
                metadata: { ...msg.metadata, actionType: undefined },
              }
            : msg
        ),
      };
    });
  };

  // Handler: Reject Sensitive Action
  const handleRejectAction = (actionId?: string) => {
    toast.info('Đã hủy bỏ hành động xuất bản an toàn.');
    setMessagesByConv((prev) => {
      const convMessages = prev[activeConversationId] || [];
      return {
        ...prev,
        [activeConversationId]: convMessages.map((msg) =>
          msg.uuid === actionId
            ? {
                ...msg,
                content:
                  msg.content +
                  '\n\n> [!NOTE]\n> **ĐÃ HỦY BỎ:** Người dùng đã từ chối thực thi thao tác xuất bản.',
                metadata: { ...msg.metadata, actionType: undefined },
              }
            : msg
        ),
      };
    });
  };

  const modeLabels: Record<ChatMode, string> = {
    general: 'Chế độ: Toàn diện',
    research: 'Chế độ: Nghiên cứu SEO',
    writing: 'Chế độ: Viết bài & Vision',
    audit: 'Chế độ: Audit SEO 100đ',
    bulk: 'Chế độ: Sản xuất hàng loạt',
  };

  return (
    <div className="flex h-[calc(100vh-4.5rem)] w-full overflow-hidden rounded-2xl border border-border bg-background shadow-sm">
      {/* 1. Left Column: Desktop ChatSidebar (w-72 / w-80) */}
      <div className="hidden md:flex md:w-72 lg:w-80 shrink-0">
        <ChatSidebar
          conversations={conversations}
          activeConversationId={activeConversationId}
          onSelectConversation={(id) => setActiveConversationId(id)}
          onNewConversation={handleNewConversation}
          onTogglePin={handleTogglePin}
          onDeleteConversation={handleDeleteConversation}
          selectedWebsiteId={selectedWebsiteId}
          onSelectWebsite={(id) => setSelectedWebsiteId(id)}
        />
      </div>

      {/* Mobile Drawer ChatSidebar */}
      <Sheet open={isMobileSidebarOpen} onOpenChange={setIsMobileSidebarOpen}>
        <SheetContent side="left" className="w-80 p-0">
          <SheetTitle className="sr-only">Lịch sử trò chuyện Chatbot</SheetTitle>
          <ChatSidebar
            conversations={conversations}
            activeConversationId={activeConversationId}
            onSelectConversation={(id) => {
              setActiveConversationId(id);
              setIsMobileSidebarOpen(false);
            }}
            onNewConversation={handleNewConversation}
            onTogglePin={handleTogglePin}
            onDeleteConversation={handleDeleteConversation}
            selectedWebsiteId={selectedWebsiteId}
            onSelectWebsite={(id) => setSelectedWebsiteId(id)}
            onCloseMobile={() => setIsMobileSidebarOpen(false)}
          />
        </SheetContent>
      </Sheet>

      {/* 2. Center Column: Chat Main Feed & Input */}
      <div className="flex flex-1 flex-col min-w-0 bg-background">
        {/* Chat Center Header */}
        <header className="flex h-14 shrink-0 items-center justify-between border-b border-border px-3 md:px-5 bg-card/40 backdrop-blur-xs">
          <div className="flex items-center gap-2.5 truncate">
            {/* Mobile toggle button */}
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              onClick={() => setIsMobileSidebarOpen(true)}
              className="md:hidden shrink-0"
              title="Mở danh sách hội thoại"
            >
              <PanelLeft className="h-4 w-4" />
              <span className="sr-only">Toggle Sidebar</span>
            </Button>

            {/* Conversation Title & Mode */}
            <div className="flex flex-col truncate">
              <div className="flex items-center gap-2 truncate">
                <h1 className="truncate text-xs md:text-sm font-bold text-foreground">
                  {activeConversation?.title || 'Aura SEO Chatbot'}
                </h1>
                <Badge
                  variant="outline"
                  className="hidden sm:inline-flex text-[10px] py-0 px-1.5 h-4 border-purple-200 text-purple-700 bg-purple-50 shrink-0"
                >
                  Phase 2.5
                </Badge>
              </div>
              <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                <span className="flex items-center gap-1 truncate text-emerald-600 dark:text-emerald-400 font-medium">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  {activeWebsite?.name || 'Chưa chọn website'}
                </span>
                <span>•</span>
                <span>{currentMessages.length} tin nhắn</span>
              </div>
            </div>
          </div>

          {/* Header Action Tools */}
          <div className="flex items-center gap-2 shrink-0">
            {/* Mode Switcher */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  size="xs"
                  className="text-[11px] h-7 gap-1 border-border/80 bg-background/80"
                >
                  <Sparkles className="h-3 w-3 text-purple-600" />
                  <span className="hidden sm:inline">{modeLabels[currentMode]}</span>
                  <span className="sm:hidden">Chế độ</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuLabel className="text-xs">Chuyển Chế Độ Hoạt Động</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {(
                  [
                    ['general', 'Toàn diện (Tự động)', Sparkles],
                    ['research', 'Nghiên cứu từ khóa & SERP', Search],
                    ['writing', 'Viết bài SEO & Ghim ảnh Vision', FileText],
                    ['audit', 'Chấm điểm SEO 100 điểm', CheckCircle2],
                    ['bulk', 'Sản xuất bài viết hàng loạt', Layers],
                  ] as const
                ).map(([modeKey, label, Icon]) => (
                  <DropdownMenuItem
                    key={modeKey}
                    onClick={() => setCurrentMode(modeKey as ChatMode)}
                    className="text-xs flex items-center justify-between cursor-pointer"
                  >
                    <div className="flex items-center gap-2">
                      <Icon className="h-3.5 w-3.5 text-muted-foreground" />
                      <span>{label}</span>
                    </div>
                    {currentMode === modeKey && (
                      <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                    )}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>

            {/* Right Widget Toggle */}
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              onClick={() => setIsRightSidebarOpen(!isRightSidebarOpen)}
              className={cn(
                'size-7 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground hidden lg:flex',
                isRightSidebarOpen && 'bg-muted text-foreground'
              )}
              title="Đóng/Mở Bảng Trạng Thái & Ngữ Cảnh SEO"
            >
              <PanelRight className="h-4 w-4" />
              <span className="sr-only">Toggle Right Widget</span>
            </Button>
          </div>
        </header>

        {/* Message Feed Center */}
        <ChatMessageList
          messages={currentMessages}
          isLoading={isAiLoading}
          userName={user?.name || 'Bạn'}
          websiteName={activeWebsite?.name}
          onSelectPrompt={(prompt) => handleSendMessage(prompt, [])}
          onApproveAction={handleApproveAction}
          onRejectAction={handleRejectAction}
        />

        {/* Input Box Bottom */}
        <div className="border-t border-border p-3 md:p-4 bg-background">
          <ChatInputBox
            onSendMessage={handleSendMessage}
            isLoading={isAiLoading}
            placeholder={`Hỏi Aura về ${activeWebsite?.name || 'website của bạn'}, hoặc nhập từ khóa SEO...`}
          />
        </div>
      </div>

      {/* 3. Right Column: Widget Trạng Thái Tác Vụ & Ngữ Cảnh SEO (lg:w-80) */}
      {isRightSidebarOpen && (
        <aside className="hidden lg:flex lg:w-76 xl:w-80 shrink-0 flex-col border-l border-border bg-card/40 backdrop-blur-xs overflow-y-auto">
          {/* Header */}
          <div className="flex h-14 items-center justify-between border-b border-border px-4 bg-muted/20">
            <div className="flex items-center gap-2 text-xs font-bold text-foreground">
              <Zap className="h-4 w-4 text-amber-500" />
              <span>Trung Tâm Tác Vụ SEO</span>
            </div>
            <Badge variant="outline" className="text-[10px] py-0 px-1.5 h-4 bg-amber-50 text-amber-700 border-amber-200">
              Realtime
            </Badge>
          </div>

          <div className="p-4 space-y-5 text-xs">
            {/* Website Context Card */}
            <div className="space-y-2 rounded-xl border border-border bg-background p-3 shadow-2xs">
              <div className="flex items-center justify-between text-muted-foreground">
                <span className="font-semibold text-foreground flex items-center gap-1.5">
                  <Globe className="h-3.5 w-3.5 text-blue-600" />
                  Website Đích
                </span>
                <span className="flex items-center gap-1 text-[11px] text-emerald-600 font-medium">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                  WordPress
                </span>
              </div>
              <div className="pt-1">
                <p className="font-bold text-foreground text-sm truncate">
                  {activeWebsite?.name || 'Nha Khoa Smile Tech'}
                </p>
                <p className="text-[11px] text-muted-foreground truncate">
                  {activeWebsite?.url || 'https://nhakhoasmile.vn'}
                </p>
              </div>
            </div>

            {/* Credit Wallet Summary Card */}
            <div className="rounded-xl border border-purple-200 bg-purple-50/50 p-3 dark:border-purple-900/60 dark:bg-purple-950/30">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-purple-900 dark:text-purple-200 flex items-center gap-1.5">
                  <CreditCard className="h-3.5 w-3.5 text-purple-700" />
                  Số Dư Credit
                </span>
                <Link
                  href="/settings/subscription"
                  className="text-[11px] font-bold text-purple-700 hover:underline flex items-center gap-0.5"
                >
                  Nạp thêm <ChevronRight className="h-3 w-3" />
                </Link>
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-xl font-extrabold text-foreground">
                  250
                </span>
                <span className="text-muted-foreground text-[11px]">
                  Credits khả dụng
                </span>
              </div>
              <p className="mt-1 text-[10px] text-muted-foreground">
                Zero Loss Guarantee: Chat thông thường 0 credit, chỉ trừ khi chạy công cụ.
              </p>
            </div>

            {/* Background Tasks Tracker Widget (P2.5.6 Ready) */}
            <div className="space-y-2.5">
              <div className="flex items-center justify-between">
                <span className="font-bold text-foreground flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5 text-purple-600" />
                  Tiến Trình Tác Vụ Ngầm
                </span>
                <span className="text-[10px] text-muted-foreground font-mono">
                  {activeTasks.length} tác vụ
                </span>
              </div>

              <div className="space-y-2">
                {activeTasks.map((t) => (
                  <div
                    key={t.id}
                    className="rounded-lg border border-border bg-background p-2.5 shadow-2xs space-y-1.5"
                  >
                    <div className="flex items-center justify-between text-[11px]">
                      <span className="font-semibold text-foreground truncate pr-2">
                        {t.name}
                      </span>
                      <span className="font-bold text-primary font-mono text-[10px]">
                        {t.progress}%
                      </span>
                    </div>
                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                      <div
                        className={cn(
                          'h-full transition-all duration-300 rounded-full',
                          t.status === 'completed' ? 'bg-emerald-500' : 'bg-primary'
                        )}
                        style={{ width: `${t.progress}%` }}
                      />
                    </div>
                    <p className="text-[10px] text-muted-foreground truncate">
                      {t.step}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* Integrated SEO Tools Matrix */}
            <div className="space-y-2 pt-1">
              <span className="font-bold text-foreground flex items-center gap-1.5">
                <Layers className="h-3.5 w-3.5 text-indigo-600" />
                10 Công Cụ SEO Tích Hợp
              </span>
              <div className="grid grid-cols-2 gap-1.5">
                {[
                  'Nghiên cứu từ khóa',
                  'Top 3 SERP Anchor',
                  'Topic Cluster SILO',
                  'Content Gap Matrix',
                  'Content Studio AI',
                  'Audit SEO 100đ',
                  'Internal Link Graph',
                  'Gemini Vision AI',
                  'Ảnh Stock WebP',
                  'Xuất bản WordPress',
                ].map((toolName, idx) => (
                  <div
                    key={idx}
                    className="flex items-center gap-1.5 rounded-md border border-border/70 bg-muted/30 px-2 py-1 text-[11px] text-muted-foreground"
                  >
                    <CheckCircle2 className="h-3 w-3 text-emerald-500 shrink-0" />
                    <span className="truncate">{toolName}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </aside>
      )}
    </div>
  );
}
