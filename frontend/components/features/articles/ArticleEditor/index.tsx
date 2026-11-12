"use client";

import React, { useState } from 'react';
import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { TablePlugin } from '@lexical/react/LexicalTablePlugin';
import { HorizontalRuleNode } from '@lexical/react/LexicalHorizontalRuleNode';
import { HorizontalRulePlugin } from '@lexical/react/LexicalHorizontalRulePlugin';
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary';
import { HeadingNode, QuoteNode } from '@lexical/rich-text';
import { TableCellNode, TableNode, TableRowNode } from '@lexical/table';
import { ListItemNode, ListNode } from '@lexical/list';
import { CodeHighlightNode, CodeNode } from '@lexical/code';
import { AutoLinkNode, LinkNode } from '@lexical/link';
import ToolbarPlugin from './ToolbarPlugin';
import SEOScorePanel from './SEOScorePanel';
import { ImageNode } from './nodes/ImageNode';
import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html';
import { $getRoot, $insertNodes, EditorState, LexicalEditor } from 'lexical';
import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

const editorConfig = {
  namespace: 'ArticleEditor',
  theme: {
    paragraph: 'mb-4 text-slate-700 leading-relaxed',
    heading: {
      h1: 'text-3xl font-black text-slate-900 mb-6 mt-8',
      h2: 'text-2xl font-bold text-slate-900 mb-4 mt-6 border-b border-slate-100 pb-2',
      h3: 'text-xl font-bold text-slate-800 mb-3 mt-5',
      h4: 'text-lg font-bold text-slate-800 mb-2 mt-4',
    },
    list: {
      ul: 'list-disc ml-6 mb-4 space-y-1',
      ol: 'list-decimal ml-6 mb-4 space-y-1',
      listitem: 'text-slate-700',
    },
    quote: 'border-l-4 border-sky-500 bg-sky-50/40 pl-4 py-2 italic my-4 text-slate-700 rounded-r-lg',
    code: 'bg-slate-950 text-slate-100 p-4 rounded-xl font-mono text-xs my-4 block overflow-x-auto',
    codeHighlight: {
      bold: 'font-bold',
      italic: 'italic',
    },
    table: 'w-full my-6 border-collapse text-xs md:text-sm border border-slate-200 rounded-xl overflow-hidden shadow-2xs',
    tableCell: 'border border-slate-200 p-3 text-slate-700 align-top',
    tableCellHeader: 'border border-slate-200 p-3 font-semibold bg-slate-100/80 text-slate-900 text-left',
    tableRow: 'hover:bg-slate-50/50 transition-colors',
    hr: 'my-6 border-t-2 border-slate-200',
    text: {
      bold: 'font-bold text-slate-900',
      italic: 'italic',
      underline: 'underline decoration-sky-500',
      strikethrough: 'line-through text-slate-400',
      code: 'bg-slate-100 text-pink-600 px-1.5 py-0.5 rounded font-mono text-xs',
    },
    link: 'text-sky-600 underline underline-offset-2 hover:text-sky-800 cursor-pointer font-medium',
  },
  onError(_error: Error) {
    // Unhandled lexical error suppressed for clean runtime
  },
  nodes: [
    HeadingNode,
    ListNode,
    ListItemNode,
    QuoteNode,
    CodeNode,
    CodeHighlightNode,
    TableNode,
    TableCellNode,
    TableRowNode,
    AutoLinkNode,
    LinkNode,
    HorizontalRuleNode,
    ImageNode,
  ],
};

export interface ArticleEditorProps {
  initialContent: string;
  initialTitle: string;
  initialMetaDescription: string;
  keyword?: string;
  onSave: (data: { title: string; content: string; metaDescription: string }) => void;
  isSaving?: boolean;
  hideSeoPanel?: boolean;
  onValuesChange?: (data: { title: string; content: string; metaDescription: string }) => void;
}

export default function ArticleEditor({ 
  initialContent, 
  initialTitle, 
  initialMetaDescription, 
  keyword = '',
  onSave,
  isSaving = false,
  hideSeoPanel = false,
  onValuesChange,
}: ArticleEditorProps) {
  const [content, setContent] = useState(initialContent);
  const [title, setTitle] = useState(initialTitle);
  const [metaDescription, setMetaDescription] = useState(initialMetaDescription);

  const onChange = (editorState: EditorState, editor: LexicalEditor) => {
    editorState.read(() => {
      const htmlString = $generateHtmlFromNodes(editor, null);
      setContent(htmlString);
      onValuesChange?.({ title, content: htmlString, metaDescription });
    });
  };

  const handleTitleChange = (val: string) => {
    setTitle(val);
    onValuesChange?.({ title: val, content, metaDescription });
  };

  const handleMetaChange = (val: string) => {
    setMetaDescription(val);
    onValuesChange?.({ title, content, metaDescription: val });
  };

  const editorForm = (
    <div className="space-y-6 w-full">
      {/* Title Input */}
      <div className="space-y-2">
        <label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Tiêu đề bài viết (H1)</label>
        <input
          type="text"
          value={title}
          onChange={(e) => handleTitleChange(e.target.value)}
          className="w-full text-2xl md:text-3xl font-extrabold text-slate-900 border-none focus:ring-0 placeholder:text-slate-300 p-0 leading-tight"
          placeholder="Nhập tiêu đề bài viết chuẩn SEO..."
        />
      </div>

      {/* Meta Description Input */}
      <div className="space-y-2">
        <label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Thẻ mô tả Meta Description (SERP Snippet)</label>
        <textarea
          value={metaDescription}
          onChange={(e) => handleMetaChange(e.target.value)}
          rows={2}
          className="w-full text-xs md:text-sm font-medium text-slate-600 bg-slate-50/80 border border-slate-200 rounded-xl p-3.5 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all resize-none leading-relaxed"
          placeholder="Nhập đoạn mô tả bài viết hiển thị trên Google..."
        />
      </div>

      {/* Rich Text Editor */}
      <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden min-h-[650px] flex flex-col">
        <LexicalComposer initialConfig={{
          ...editorConfig,
          editorState: (editor) => {
            try {
              const dom = new DOMParser().parseFromString(initialContent || '', 'text/html');
              const nodes = $generateNodesFromDOM(editor, dom);
              $getRoot().select();
              $insertNodes(nodes);
            } catch (err) {
              console.error('Lỗi khởi tạo nội dung ArticleEditor:', err);
            }
          }
        }}>
          <ToolbarPlugin />
          <div className="relative flex-1">
            <RichTextPlugin
              contentEditable={
                <ContentEditable className="min-h-[550px] p-6 md:p-8 outline-none focus:ring-0 prose prose-slate max-w-none text-slate-800 leading-relaxed" />
              }
              placeholder={
                <div className="absolute top-8 left-8 text-slate-300 pointer-events-none text-sm">
                  Bắt đầu chỉnh sửa hoặc soạn thảo nội dung bài viết...
                </div>
              }
              ErrorBoundary={LexicalErrorBoundary}
            />
            <HistoryPlugin />
            <ListPlugin />
            <LinkPlugin />
            <TablePlugin />
            <HorizontalRulePlugin />
            <OnChangePlugin onChange={onChange} />
          </div>
        </LexicalComposer>
      </div>

      <div className="flex justify-end gap-3 pt-2">
        <Button 
          type="button"
          disabled={isSaving}
          onClick={() => onSave({ title, content, metaDescription })}
          className="px-6 py-2.5 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white rounded-xl font-bold shadow-md shadow-sky-200 transition-all active:scale-95 flex items-center gap-2 cursor-pointer"
        >
          {isSaving && <Loader2 className="w-4 h-4 animate-spin" />}
          {isSaving ? 'Đang lưu bài viết...' : 'Lưu Thay Đổi Bài Viết'}
        </Button>
      </div>
    </div>
  );

  if (hideSeoPanel) {
    return editorForm;
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div className="lg:col-span-8">
        {editorForm}
      </div>

      <div className="lg:col-span-4">
        <SEOScorePanel 
          content={content} 
          title={title} 
          metaDescription={metaDescription} 
          keyword={keyword} 
        />
      </div>
    </div>
  );
}
