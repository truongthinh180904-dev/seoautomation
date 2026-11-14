"use client";

import React, { useCallback, useEffect, useState } from 'react';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $createParagraphNode,
  $getRoot,
  $getSelection,
  $insertNodes,
  $isElementNode,
  $isRangeSelection,
  $isTextNode,
  CAN_REDO_COMMAND,
  CAN_UNDO_COMMAND,
  COMMAND_PRIORITY_CRITICAL,
  FORMAT_ELEMENT_COMMAND,
  FORMAT_TEXT_COMMAND,
  INDENT_CONTENT_COMMAND,
  OUTDENT_CONTENT_COMMAND,
  REDO_COMMAND,
  SELECTION_CHANGE_COMMAND,
  UNDO_COMMAND,
  type ElementFormatType,
  type TextFormatType,
} from 'lexical';
import { $getSelectionStyleValueForProperty, $patchStyleText, $setBlocksType } from '@lexical/selection';
import { $createHeadingNode, $createQuoteNode, $isHeadingNode, $isQuoteNode, type HeadingTagType } from '@lexical/rich-text';
import {
  $isListNode,
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  ListNode,
  REMOVE_LIST_COMMAND,
} from '@lexical/list';
import { $createCodeNode, $isCodeNode } from '@lexical/code';
import { $isLinkNode, TOGGLE_LINK_COMMAND } from '@lexical/link';
import { INSERT_TABLE_COMMAND } from '@lexical/table';
import { INSERT_HORIZONTAL_RULE_COMMAND } from '@lexical/react/LexicalHorizontalRuleNode';
import { $findMatchingParent, $getNearestNodeOfType, mergeRegister } from '@lexical/utils';
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Baseline,
  Bold,
  Code,
  Highlighter,
  ImagePlus,
  IndentDecrease,
  IndentIncrease,
  Italic,
  Link2,
  Link2Off,
  List,
  ListOrdered,
  Minus,
  Redo,
  RemoveFormatting,
  Strikethrough,
  Table2,
  Underline,
  Undo,
} from 'lucide-react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { $createImageNode } from './nodes/ImageNode';
import { ImageDetailsDialog, type ImageDetails } from './nodes/ImageDetailsDialog';

type BlockType = 'paragraph' | 'h1' | 'h2' | 'h3' | 'h4' | 'quote' | 'code' | 'bullet' | 'number';

const BLOCK_LABELS: Record<BlockType, string> = {
  paragraph: 'Đoạn văn',
  h1: 'Tiêu đề 1 (H1)',
  h2: 'Tiêu đề 2 (H2)',
  h3: 'Tiêu đề 3 (H3)',
  h4: 'Tiêu đề 4 (H4)',
  quote: 'Trích dẫn',
  code: 'Khối mã',
  bullet: 'Danh sách chấm',
  number: 'Danh sách số',
};

const COLORS = ['#0f172a', '#475569', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0284c7', '#2563eb', '#7c3aed', '#db2777'];
const HIGHLIGHTS = ['#fef08a', '#bbf7d0', '#bae6fd', '#fecaca', '#e9d5ff', '#fed7aa'];

function ToolButton({
  active,
  disabled,
  title,
  onClick,
  children,
}: {
  active?: boolean;
  disabled?: boolean;
  title: string;
  onClick?: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      title={title}
      aria-label={title}
      aria-pressed={active}
      disabled={disabled}
      onMouseDown={(e) => e.preventDefault()}
      onClick={onClick}
      className={cn(
        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-slate-600 transition-colors hover:bg-slate-200/70 hover:text-slate-900 disabled:pointer-events-none disabled:opacity-35',
        active && 'bg-sky-100 text-sky-700 hover:bg-sky-100'
      )}
    >
      {children}
    </button>
  );
}

const Divider = () => <span className="mx-1 h-6 w-px shrink-0 bg-slate-200" />;

export default function ToolbarPlugin() {
  const [editor] = useLexicalComposerContext();
  const [canUndo, setCanUndo] = useState(false);
  const [canRedo, setCanRedo] = useState(false);
  const [blockType, setBlockType] = useState<BlockType>('paragraph');
  const [formats, setFormats] = useState<Record<string, boolean>>({});
  const [elementFormat, setElementFormat] = useState<ElementFormatType>('left');
  const [isLink, setIsLink] = useState(false);
  const [fontColor, setFontColor] = useState('');
  const [linkUrl, setLinkUrl] = useState('https://');
  const [isLinkOpen, setLinkOpen] = useState(false);
  const [isImageOpen, setImageOpen] = useState(false);

  const refreshToolbar = useCallback(() => {
    const selection = $getSelection();
    if (!$isRangeSelection(selection)) return;

    setFormats({
      bold: selection.hasFormat('bold'),
      italic: selection.hasFormat('italic'),
      underline: selection.hasFormat('underline'),
      strikethrough: selection.hasFormat('strikethrough'),
      code: selection.hasFormat('code'),
    });
    setFontColor($getSelectionStyleValueForProperty(selection, 'color', ''));

    const anchorNode = selection.anchor.getNode();
    const element =
      anchorNode.getKey() === 'root'
        ? anchorNode
        : $findMatchingParent(anchorNode, (n) => {
            const parent = n.getParent();
            return parent !== null && parent.getKey() === 'root';
          }) ?? anchorNode.getTopLevelElementOrThrow();

    const parent = anchorNode.getParent();
    setIsLink($isLinkNode(parent) || $isLinkNode(anchorNode));

    if ($isElementNode(element)) setElementFormat(element.getFormatType() || 'left');

    if ($isListNode(element)) {
      const parentList = $getNearestNodeOfType(anchorNode, ListNode);
      setBlockType((parentList ?? element).getListType() === 'number' ? 'number' : 'bullet');
    } else if ($isHeadingNode(element)) {
      const tag = element.getTag();
      setBlockType((['h1', 'h2', 'h3', 'h4'].includes(tag) ? tag : 'h4') as BlockType);
    } else if ($isQuoteNode(element)) {
      setBlockType('quote');
    } else if ($isCodeNode(element)) {
      setBlockType('code');
    } else {
      setBlockType('paragraph');
    }
  }, []);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(({ editorState }) => editorState.read(refreshToolbar)),
      editor.registerCommand(
        SELECTION_CHANGE_COMMAND,
        () => {
          refreshToolbar();
          return false;
        },
        COMMAND_PRIORITY_CRITICAL
      ),
      editor.registerCommand(CAN_UNDO_COMMAND, (p) => (setCanUndo(p), false), COMMAND_PRIORITY_CRITICAL),
      editor.registerCommand(CAN_REDO_COMMAND, (p) => (setCanRedo(p), false), COMMAND_PRIORITY_CRITICAL)
    );
  }, [editor, refreshToolbar]);

  const changeBlockType = (type: BlockType) => {
    if (type === 'bullet') {
      editor.dispatchCommand(blockType === 'bullet' ? REMOVE_LIST_COMMAND : INSERT_UNORDERED_LIST_COMMAND, undefined);
      return;
    }
    if (type === 'number') {
      editor.dispatchCommand(blockType === 'number' ? REMOVE_LIST_COMMAND : INSERT_ORDERED_LIST_COMMAND, undefined);
      return;
    }
    editor.update(() => {
      const selection = $getSelection();
      if (!$isRangeSelection(selection)) return;
      if (type === 'paragraph') $setBlocksType(selection, () => $createParagraphNode());
      else if (type === 'quote') $setBlocksType(selection, () => $createQuoteNode());
      else if (type === 'code') $setBlocksType(selection, () => $createCodeNode());
      else $setBlocksType(selection, () => $createHeadingNode(type as HeadingTagType));
    });
  };

  const format = (f: TextFormatType) => editor.dispatchCommand(FORMAT_TEXT_COMMAND, f);
  const align = (a: ElementFormatType) => editor.dispatchCommand(FORMAT_ELEMENT_COMMAND, a);

  const applyStyle = (style: Record<string, string | null>) => {
    editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) $patchStyleText(selection, style);
    });
  };

  const clearFormatting = () => {
    editor.update(() => {
      const selection = $getSelection();
      if (!$isRangeSelection(selection)) return;
      selection.getNodes().forEach((node) => {
        if ($isTextNode(node)) {
          node.setFormat(0);
          node.setStyle('');
        }
      });
    });
  };

  const applyLink = () => {
    const url = linkUrl.trim();
    if (!url || url === 'https://') return;
    editor.dispatchCommand(TOGGLE_LINK_COMMAND, /^(https?:|mailto:|tel:|\/|#)/i.test(url) ? url : `https://${url}`);
    setLinkOpen(false);
  };

  const insertImage = (details: ImageDetails) => {
    editor.update(() => {
      if (!$getSelection()) $getRoot().selectEnd();
      $insertNodes([$createImageNode(details)]);
    });
  };

  const insertTable = (rows: number, columns: number) => {
    editor.dispatchCommand(INSERT_TABLE_COMMAND, {
      rows: String(rows),
      columns: String(columns),
      includeHeaders: { rows: true, columns: false },
    });
  };

  return (
    <div className="sticky top-0 z-10 flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50/95 p-1.5 backdrop-blur">
      <ToolButton title="Hoàn tác (Ctrl+Z)" disabled={!canUndo} onClick={() => editor.dispatchCommand(UNDO_COMMAND, undefined)}>
        <Undo className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Làm lại (Ctrl+Y)" disabled={!canRedo} onClick={() => editor.dispatchCommand(REDO_COMMAND, undefined)}>
        <Redo className="h-4 w-4" />
      </ToolButton>

      <Divider />

      <select
        aria-label="Kiểu khối văn bản"
        value={blockType}
        onChange={(e) => changeBlockType(e.target.value as BlockType)}
        className="h-8 rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500/30"
      >
        {(Object.keys(BLOCK_LABELS) as BlockType[]).map((t) => (
          <option key={t} value={t}>
            {BLOCK_LABELS[t]}
          </option>
        ))}
      </select>

      <Divider />

      <ToolButton title="In đậm (Ctrl+B)" active={formats.bold} onClick={() => format('bold')}>
        <Bold className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="In nghiêng (Ctrl+I)" active={formats.italic} onClick={() => format('italic')}>
        <Italic className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Gạch chân (Ctrl+U)" active={formats.underline} onClick={() => format('underline')}>
        <Underline className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Gạch ngang" active={formats.strikethrough} onClick={() => format('strikethrough')}>
        <Strikethrough className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Mã nội tuyến" active={formats.code} onClick={() => format('code')}>
        <Code className="h-4 w-4" />
      </ToolButton>

      <Popover>
        <PopoverTrigger
          title="Màu chữ"
          className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-slate-600 transition-colors hover:bg-slate-200/70 hover:text-slate-900"
        >
          <Baseline className="h-4 w-4" style={{ color: fontColor || undefined }} />
        </PopoverTrigger>
        <PopoverContent className="w-auto p-2">
          <div className="grid grid-cols-5 gap-1.5">
            {COLORS.map((c) => (
              <button
                key={c}
                type="button"
                title={c}
                onClick={() => applyStyle({ color: c })}
                className="h-6 w-6 rounded-full border border-slate-200 transition-transform hover:scale-110"
                style={{ backgroundColor: c }}
              />
            ))}
          </div>
          <Button variant="ghost" size="sm" className="mt-2 w-full text-xs" onClick={() => applyStyle({ color: null })}>
            Màu mặc định
          </Button>
        </PopoverContent>
      </Popover>

      <Popover>
        <PopoverTrigger
          title="Tô nền chữ (highlight)"
          className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-slate-600 transition-colors hover:bg-slate-200/70 hover:text-slate-900"
        >
          <Highlighter className="h-4 w-4" />
        </PopoverTrigger>
        <PopoverContent className="w-auto p-2">
          <div className="grid grid-cols-6 gap-1.5">
            {HIGHLIGHTS.map((c) => (
              <button
                key={c}
                type="button"
                title={c}
                onClick={() => applyStyle({ 'background-color': c })}
                className="h-6 w-6 rounded border border-slate-200 transition-transform hover:scale-110"
                style={{ backgroundColor: c }}
              />
            ))}
          </div>
          <Button variant="ghost" size="sm" className="mt-2 w-full text-xs" onClick={() => applyStyle({ 'background-color': null })}>
            Bỏ tô nền
          </Button>
        </PopoverContent>
      </Popover>

      <Divider />

      {isLink ? (
        <ToolButton title="Gỡ liên kết" active onClick={() => editor.dispatchCommand(TOGGLE_LINK_COMMAND, null)}>
          <Link2Off className="h-4 w-4" />
        </ToolButton>
      ) : (
        <Popover open={isLinkOpen} onOpenChange={setLinkOpen}>
          <PopoverTrigger
            title="Chèn liên kết (bôi đen chữ trước)"
            className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-slate-600 transition-colors hover:bg-slate-200/70 hover:text-slate-900"
          >
            <Link2 className="h-4 w-4" />
          </PopoverTrigger>
          <PopoverContent className="w-80 p-3">
            <p className="mb-2 text-xs font-semibold text-slate-600">Chèn liên kết cho đoạn chữ đã bôi đen</p>
            <div className="flex gap-2">
              <Input
                value={linkUrl}
                onChange={(e) => setLinkUrl(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), applyLink())}
                placeholder="https://..."
                className="h-8 text-xs"
              />
              <Button size="sm" className="h-8" onClick={applyLink}>
                Chèn
              </Button>
            </div>
          </PopoverContent>
        </Popover>
      )}

      <Divider />

      <ToolButton title="Căn trái" active={elementFormat === 'left' || elementFormat === ''} onClick={() => align('left')}>
        <AlignLeft className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Căn giữa" active={elementFormat === 'center'} onClick={() => align('center')}>
        <AlignCenter className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Căn phải" active={elementFormat === 'right'} onClick={() => align('right')}>
        <AlignRight className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Căn đều hai bên" active={elementFormat === 'justify'} onClick={() => align('justify')}>
        <AlignJustify className="h-4 w-4" />
      </ToolButton>

      <Divider />

      <ToolButton title="Danh sách chấm" active={blockType === 'bullet'} onClick={() => changeBlockType('bullet')}>
        <List className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Danh sách số" active={blockType === 'number'} onClick={() => changeBlockType('number')}>
        <ListOrdered className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Giảm thụt lề" onClick={() => editor.dispatchCommand(OUTDENT_CONTENT_COMMAND, undefined)}>
        <IndentDecrease className="h-4 w-4" />
      </ToolButton>
      <ToolButton title="Tăng thụt lề" onClick={() => editor.dispatchCommand(INDENT_CONTENT_COMMAND, undefined)}>
        <IndentIncrease className="h-4 w-4" />
      </ToolButton>

      <Divider />

      <ToolButton title="Chèn hình ảnh" onClick={() => setImageOpen(true)}>
        <ImagePlus className="h-4 w-4" />
        <span className="ml-1 hidden text-xs font-medium xl:inline">Ảnh</span>
      </ToolButton>

      <Popover>
        <PopoverTrigger
          title="Chèn bảng"
          className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-slate-600 transition-colors hover:bg-slate-200/70 hover:text-slate-900"
        >
          <Table2 className="h-4 w-4" />
        </PopoverTrigger>
        <PopoverContent className="w-48 p-2">
          <p className="mb-1.5 px-1 text-xs font-semibold text-slate-600">Chèn bảng (có hàng tiêu đề)</p>
          {[
            [3, 2],
            [3, 3],
            [4, 3],
            [5, 4],
            [6, 4],
          ].map(([r, c]) => (
            <button
              key={`${r}x${c}`}
              type="button"
              onClick={() => insertTable(r, c)}
              className="block w-full rounded px-2 py-1.5 text-left text-xs hover:bg-slate-100"
            >
              {r} hàng × {c} cột
            </button>
          ))}
        </PopoverContent>
      </Popover>

      <ToolButton title="Đường kẻ ngang" onClick={() => editor.dispatchCommand(INSERT_HORIZONTAL_RULE_COMMAND, undefined)}>
        <Minus className="h-4 w-4" />
      </ToolButton>

      <ToolButton title="Xóa định dạng" onClick={clearFormatting}>
        <RemoveFormatting className="h-4 w-4" />
      </ToolButton>

      <ImageDetailsDialog
        open={isImageOpen}
        onOpenChange={setImageOpen}
        title="Chèn hình ảnh vào bài viết"
        submitLabel="Chèn ảnh"
        onSubmit={insertImage}
      />
    </div>
  );
}
