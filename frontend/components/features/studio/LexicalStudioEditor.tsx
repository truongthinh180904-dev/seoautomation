'use client';

import React, { useEffect, useRef, useState, useCallback } from 'react';
import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { HeadingNode, QuoteNode, $createHeadingNode, $createQuoteNode } from '@lexical/rich-text';
import { ListItemNode, ListNode, INSERT_ORDERED_LIST_COMMAND, INSERT_UNORDERED_LIST_COMMAND } from '@lexical/list';
import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html';
import { $setBlocksType } from '@lexical/selection';
import {
  $getRoot,
  $getSelection,
  $isRangeSelection,
  $createParagraphNode,
  FORMAT_TEXT_COMMAND,
  UNDO_COMMAND,
  REDO_COMMAND,
  EditorState,
  LexicalEditor,
  type RangeSelection,
} from 'lexical';
import { Button } from '@/components/ui/button';
import {
  Bold,
  Italic,
  Underline,
  Strikethrough,
  Heading1,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Undo,
  Redo,
  Sparkles,
  Pilcrow,
} from 'lucide-react';
import { FloatingAiMenu } from './FloatingAiMenu';

interface LexicalStudioEditorProps {
  articleId: number;
  initialHtml: string;
  onChange: (html: string, wordCount: number) => void;
  placeholder?: string;
}

// 1. Plugin khởi tạo nội dung HTML ban đầu vào Lexical
function HtmlInitializerPlugin({ initialHtml }: { initialHtml: string }) {
  const [editor] = useLexicalComposerContext();
  const hasInitialized = useRef(false);

  useEffect(() => {
    if (!hasInitialized.current && initialHtml) {
      editor.update(() => {
        const root = $getRoot();
        root.clear();
        const parser = new DOMParser();
        const dom = parser.parseFromString(initialHtml, 'text/html');
        const nodes = $generateNodesFromDOM(editor, dom);
        root.append(...nodes);
      });
      hasInitialized.current = true;
    }
  }, [editor, initialHtml]);

  return null;
}

// 2. Toolbar công cụ định dạng Rich-Text
function StudioToolbarPlugin({ onOpenAiMenu }: { onOpenAiMenu: () => void }) {
  const [editor] = useLexicalComposerContext();

  const formatText = (format: 'bold' | 'italic' | 'underline' | 'strikethrough') => {
    editor.dispatchCommand(FORMAT_TEXT_COMMAND, format);
  };

  const setBlockType = (type: 'h1' | 'h2' | 'h3' | 'paragraph' | 'quote') => {
    editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) {
        if (type === 'h1') {
          $setBlocksType(selection, () => $createHeadingNode('h1'));
        } else if (type === 'h2') {
          $setBlocksType(selection, () => $createHeadingNode('h2'));
        } else if (type === 'h3') {
          $setBlocksType(selection, () => $createHeadingNode('h3'));
        } else if (type === 'quote') {
          $setBlocksType(selection, () => $createQuoteNode());
        } else {
          $setBlocksType(selection, () => $createParagraphNode());
        }
      }
    });
  };

  return (
    <div className="flex items-center gap-0.5 p-2 border-b border-border bg-muted/30 flex-wrap select-none">
      {/* Block Type */}
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => setBlockType('paragraph')}
        title="Đoạn văn (Paragraph)"
      >
        <Pilcrow className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => setBlockType('h1')}
        title="Tiêu đề H1"
      >
        <Heading1 className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => setBlockType('h2')}
        title="Tiêu đề H2"
      >
        <Heading2 className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => setBlockType('h3')}
        title="Tiêu đề H3"
      >
        <Heading3 className="w-3.5 h-3.5" />
      </Button>

      <div className="w-[1px] h-4 bg-border mx-1" />

      {/* Inline Formatting */}
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => formatText('bold')}
        title="In đậm (Ctrl+B)"
      >
        <Bold className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => formatText('italic')}
        title="In nghiêng (Ctrl+I)"
      >
        <Italic className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => formatText('underline')}
        title="Gạch chân (Ctrl+U)"
      >
        <Underline className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => formatText('strikethrough')}
        title="Gạch ngang"
      >
        <Strikethrough className="w-3.5 h-3.5" />
      </Button>

      <div className="w-[1px] h-4 bg-border mx-1" />

      {/* Lists & Quotes */}
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined)}
        title="Danh sách gạch đầu dòng"
      >
        <List className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined)}
        title="Danh sách đánh số"
      >
        <ListOrdered className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => setBlockType('quote')}
        title="Trích dẫn"
      >
        <Quote className="w-3.5 h-3.5" />
      </Button>

      <div className="w-[1px] h-4 bg-border mx-1" />

      {/* Undo / Redo */}
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => editor.dispatchCommand(UNDO_COMMAND, undefined)}
        title="Hoàn tác (Undo)"
      >
        <Undo className="w-3.5 h-3.5" />
      </Button>
      <Button
        variant="ghost"
        size="icon-xs"
        onClick={() => editor.dispatchCommand(REDO_COMMAND, undefined)}
        title="Làm lại (Redo)"
      >
        <Redo className="w-3.5 h-3.5" />
      </Button>

      <div className="flex-1" />

      {/* Floating AI Copilot Trigger */}
      <Button
        size="xs"
        onClick={onOpenAiMenu}
        className="gap-1 text-xs h-7 bg-amber-500/10 text-amber-600 border border-amber-500/30 hover:bg-amber-500/20"
      >
        <Sparkles className="w-3.5 h-3.5 fill-amber-500" />
        AI Copilot
      </Button>
    </div>
  );
}

// 3. Fallback error boundary tối giản cho Lexical
function LexicalErrorFallback() {
  return (
    <div className="p-4 text-xs text-destructive bg-destructive/10">
      Đã xảy ra lỗi trong trình soạn thảo văn bản.
    </div>
  );
}

export function LexicalStudioEditor({
  articleId,
  initialHtml,
  onChange,
  placeholder = 'Bắt đầu soạn thảo nội dung bài viết...',
}: LexicalStudioEditorProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const editorRef = useRef<LexicalEditor | null>(null);

  const [selectedText, setSelectedText] = useState('');
  const [menuPosition, setMenuPosition] = useState<{ top: number; left: number } | null>(null);
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [showPill, setShowPill] = useState(false);
  const [pillPosition, setPillPosition] = useState<{ top: number; left: number } | null>(null);

  const initialConfig: React.ComponentProps<typeof LexicalComposer>['initialConfig'] = {
    namespace: 'ContentStudioLexicalEditor',
    theme: {
      paragraph: 'mb-4 leading-relaxed text-foreground',
      heading: {
        h1: 'text-2xl font-bold mb-4 mt-6 text-foreground',
        h2: 'text-xl font-bold mb-3 mt-5 text-foreground',
        h3: 'text-lg font-semibold mb-2 mt-4 text-foreground',
      },
      list: {
        ul: 'list-disc pl-6 mb-4 space-y-1',
        ol: 'list-decimal pl-6 mb-4 space-y-1',
        listitem: 'text-foreground',
      },
      quote: 'border-l-4 border-primary pl-4 italic my-4 text-muted-foreground',
      text: {
        bold: 'font-bold',
        italic: 'italic',
        underline: 'underline',
        strikethrough: 'line-through',
      },
    },
    nodes: [HeadingNode, QuoteNode, ListNode, ListItemNode],
    onError: () => {
      // Lexical internal error handler
    },
  };

  // Lắng nghe sự kiện bôi đen văn bản để tính tọa độ Floating AI Menu
  const handleMouseUp = () => {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed || !selection.toString().trim()) {
      setShowPill(false);
      return;
    }

    const text = selection.toString().trim();
    if (text.length > 2) {
      setSelectedText(text);

      const range = selection.getRangeAt(0);
      const rect = range.getBoundingClientRect();
      const containerRect = containerRef.current?.getBoundingClientRect();

      if (containerRect) {
        const top = rect.bottom - containerRect.top + 8;
        const left = Math.max(10, rect.left - containerRect.left);

        setPillPosition({ top, left });
        setShowPill(true);
      }
    } else {
      setShowPill(false);
    }
  };

  const handleOpenAiFromPill = () => {
    if (pillPosition) {
      setMenuPosition(pillPosition);
      setIsMenuOpen(true);
      setShowPill(false);
    }
  };

  const handleOpenAiFromToolbar = () => {
    // Nếu chưa chọn đoạn text nào, lấy đoạn văn bản hiện tại hoặc hiển thị thông báo
    if (!selectedText) {
      const editor = editorRef.current;
      if (editor) {
        editor.getEditorState().read(() => {
          const root = $getRoot();
          const firstParagraph = root.getTextContent().slice(0, 300);
          if (firstParagraph) {
            setSelectedText(firstParagraph);
          }
        });
      }
    }
    setMenuPosition({ top: 50, left: 200 });
    setIsMenuOpen(true);
  };

  // Helper thu gọn selection về cuối (collapse to end)
  const collapseSelectionToEnd = (selection: RangeSelection) => {
    if (selection.isBackward()) {
      selection.focus.set(selection.anchor.key, selection.anchor.offset, selection.anchor.type);
    } else {
      selection.anchor.set(selection.focus.key, selection.focus.offset, selection.focus.type);
    }
  };

  // Áp dụng kết quả AI Copilot vào Lexical
  const handleApplyAiResult = (newText: string, mode: 'replace' | 'insert_after') => {
    const editor = editorRef.current;
    if (!editor) return;

    editor.update(() => {
      let selection = $getSelection();
      if (!$isRangeSelection(selection)) {
        const root = $getRoot();
        selection = root.selectEnd();
      }

      if ($isRangeSelection(selection)) {
        if (mode === 'insert_after') {
          collapseSelectionToEnd(selection);
        }

        if (newText.includes('<')) {
          const parser = new DOMParser();
          const dom = parser.parseFromString(newText, 'text/html');
          const nodes = $generateNodesFromDOM(editor, dom);
          selection.insertNodes(nodes);
        } else {
          if (mode === 'insert_after') {
            selection.insertText('\n' + newText);
          } else {
            selection.insertText(newText);
          }
        }
      }
    });

    setIsMenuOpen(false);
  };

  // Đồng bộ nội dung HTML ra bên ngoài khi có thay đổi
  const handleEditorChange = useCallback(
    (editorState: EditorState, editor: LexicalEditor) => {
      editorRef.current = editor;

      editorState.read(() => {
        const html = $generateHtmlFromNodes(editor, null);
        const text = $getRoot().getTextContent();
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        onChange(html, words);
      });
    },
    [onChange]
  );

  return (
    <div
      ref={containerRef}
      className="relative flex flex-col flex-1 h-full bg-background border border-border rounded-xl overflow-hidden shadow-xs"
      onMouseUp={handleMouseUp}
    >
      <LexicalComposer initialConfig={initialConfig}>
        {/* Toolbar */}
        <StudioToolbarPlugin onOpenAiMenu={handleOpenAiFromToolbar} />

        {/* Editor Main Content Area */}
        <div className="relative flex-1 overflow-y-auto">
          <RichTextPlugin
            contentEditable={
              <ContentEditable
                className="min-h-[500px] p-6 focus:outline-none text-sm text-foreground leading-relaxed font-sans max-w-4xl mx-auto"
                aria-placeholder={placeholder}
                placeholder={
                  <div className="absolute top-6 left-6 text-muted-foreground/60 text-sm pointer-events-none select-none max-w-4xl mx-auto">
                    {placeholder}
                  </div>
                }
              />
            }
            ErrorBoundary={LexicalErrorFallback}
          />
          <HistoryPlugin />
          <ListPlugin />
          <HtmlInitializerPlugin initialHtml={initialHtml} />
          <OnChangePlugin onChange={handleEditorChange} />
        </div>

        {/* Floating Quick AI Button near text selection */}
        {showPill && pillPosition && !isMenuOpen && (
          <div
            style={{
              position: 'absolute',
              top: `${pillPosition.top}px`,
              left: `${pillPosition.left}px`,
              zIndex: 40,
            }}
            className="animate-in fade-in zoom-in-95 duration-150"
          >
            <Button
              size="xs"
              onClick={handleOpenAiFromPill}
              className="h-7 px-2.5 text-xs gap-1.5 shadow-lg bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-full"
            >
              <Sparkles className="w-3.5 h-3.5 fill-white" />
              AI Copilot
            </Button>
          </div>
        )}

        {/* Floating AI Menu Dialog */}
        <FloatingAiMenu
          articleId={articleId}
          selectedText={selectedText}
          position={menuPosition}
          isOpen={isMenuOpen}
          onApplyResult={handleApplyAiResult}
          onClose={() => setIsMenuOpen(false)}
        />
      </LexicalComposer>
    </div>
  );
}
