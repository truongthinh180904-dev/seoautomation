"use client";

import React, { useState } from 'react';
import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary';
import { HeadingNode, QuoteNode } from '@lexical/rich-text';
import { TableCellNode, TableNode, TableRowNode } from '@lexical/table';
import { ListItemNode, ListNode } from '@lexical/list';
import { CodeHighlightNode, CodeNode } from '@lexical/code';
import { AutoLinkNode, LinkNode } from '@lexical/link';
import ToolbarPlugin from './ToolbarPlugin';
import SEOScorePanel from './SEOScorePanel';
import { $generateHtmlFromNodes, $generateNodesFromHtml } from '@lexical/html';
import { $getRoot, $insertNodes, EditorState } from 'lexical';

const editorConfig = {
  namespace: 'ArticleEditor',
  theme: {
    paragraph: 'mb-4 text-slate-700 leading-relaxed',
    heading: {
      h1: 'text-4xl font-black text-slate-900 mb-6 mt-8',
      h2: 'text-2xl font-bold text-slate-900 mb-4 mt-6',
      h3: 'text-xl font-bold text-slate-800 mb-3 mt-5',
    },
    list: {
      ul: 'list-disc ml-6 mb-4',
      ol: 'list-decimal ml-6 mb-4',
      listitem: 'mb-2',
    },
    text: {
      bold: 'font-bold',
      italic: 'italic',
      underline: 'underline',
    },
    link: 'text-blue-600 underline hover:text-blue-800 cursor-pointer',
  },
  onError(error: Error) {
    console.error(error);
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
  ],
};

interface ArticleEditorProps {
  initialContent: string;
  initialTitle: string;
  initialMetaDescription: string;
  keyword: string;
  onSave: (data: { title: string, content: string, metaDescription: string }) => void;
}

export default function ArticleEditor({ 
  initialContent, 
  initialTitle, 
  initialMetaDescription, 
  keyword,
  onSave
}: ArticleEditorProps) {
  const [content, setContent] = useState(initialContent);
  const [title, setTitle] = useState(initialTitle);
  const [metaDescription, setMetaDescription] = useState(initialMetaDescription);

  const onChange = (editorState: EditorState, editor: any) => {
    editorState.read(() => {
      const htmlString = $generateHtmlFromNodes(editor, null);
      setContent(htmlString);
    });
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div className="lg:col-span-8 space-y-6">
        {/* Title Input */}
        <div className="space-y-2">
          <label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Article Title</label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="w-full text-4xl font-black text-slate-900 border-none focus:ring-0 placeholder:text-slate-200 p-0"
            placeholder="Enter title..."
          />
        </div>

        {/* Meta Description Input */}
        <div className="space-y-2">
          <label className="text-xs font-bold text-slate-500 uppercase tracking-widest">Meta Description</label>
          <textarea
            value={metaDescription}
            onChange={(e) => setMetaDescription(e.target.value)}
            rows={2}
            className="w-full text-sm font-medium text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-4 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all resize-none"
            placeholder="Enter meta description..."
          />
        </div>

        {/* Rich Text Editor */}
        <div className="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden min-h-[600px] flex flex-col">
          <LexicalComposer initialConfig={{
            ...editorConfig,
            editorState: (editor) => {
              const nodes = $generateNodesFromHtml(editor, initialContent);
              $getRoot().select();
              $insertNodes(nodes);
            }
          }}>
            <ToolbarPlugin />
            <div className="relative flex-1">
              <RichTextPlugin
                contentEditable={
                  <ContentEditable className="min-h-[500px] p-8 outline-none focus:ring-0 prose prose-slate max-w-none" />
                }
                placeholder={
                  <div className="absolute top-8 left-8 text-slate-300 pointer-events-none">
                    Start writing your article...
                  </div>
                }
                ErrorBoundary={LexicalErrorBoundary}
              />
              <HistoryPlugin />
              <ListPlugin />
              <LinkPlugin />
              <OnChangePlugin onChange={onChange} />
            </div>
          </LexicalComposer>
        </div>

        <div className="flex justify-end gap-4 pt-4">
          <button 
            onClick={() => onSave({ title, content, metaDescription })}
            className="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-200 transition-all active:scale-95"
          >
            Save Changes
          </button>
        </div>
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
