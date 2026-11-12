'use client';

import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  $applyNodeReplacement,
  $getNodeByKey,
  $getSelection,
  $isNodeSelection,
  CLICK_COMMAND,
  COMMAND_PRIORITY_LOW,
  DecoratorNode,
  KEY_BACKSPACE_COMMAND,
  KEY_DELETE_COMMAND,
  type DOMConversionMap,
  type DOMConversionOutput,
  type DOMExportOutput,
  type EditorConfig,
  type LexicalNode,
  type NodeKey,
  type SerializedLexicalNode,
  type Spread,
} from 'lexical';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { useLexicalNodeSelection } from '@lexical/react/useLexicalNodeSelection';
import { mergeRegister } from '@lexical/utils';
import {
  AlignCenter,
  AlignLeft,
  AlignRight,
  ImageUp,
  Loader2,
  Pencil,
  Trash2,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ImageDetailsDialog, uploadEditorImage, type ImageDetails } from './ImageDetailsDialog';

export type ImageAlign = 'left' | 'center' | 'right';

export interface ImagePayload {
  src: string;
  altText?: string;
  caption?: string;
  width?: number; // phần trăm chiều rộng khung bài viết (25 - 100)
  align?: ImageAlign;
  key?: NodeKey;
}

export type SerializedImageNode = Spread<
  { src: string; altText: string; caption: string; width: number; align: ImageAlign },
  SerializedLexicalNode
>;

function parseWidth(el: HTMLElement | null): number {
  if (!el) return 100;
  const styleWidth = el.style?.width || '';
  const pct = styleWidth.match(/(\d+(?:\.\d+)?)%/);
  if (pct) return Math.min(100, Math.max(20, Math.round(Number(pct[1]))));
  const dataWidth = Number(el.getAttribute('data-width'));
  return dataWidth >= 20 && dataWidth <= 100 ? dataWidth : 100;
}

function parseAlign(el: HTMLElement | null): ImageAlign {
  const value = (el?.getAttribute('data-align') || el?.style?.textAlign || '').toLowerCase();
  if (value === 'left' || value === 'right') return value;
  if (el?.style?.float === 'left') return 'left';
  if (el?.style?.float === 'right') return 'right';
  return 'center';
}

function convertImgElement(domNode: Node): DOMConversionOutput | null {
  const img = domNode as HTMLImageElement;
  const src = img.getAttribute('src');
  if (!src || src.startsWith('file:')) return null;
  return {
    node: $createImageNode({
      src,
      altText: img.getAttribute('alt') || '',
      width: parseWidth(img),
      align: parseAlign(img),
    }),
  };
}

function convertFigureElement(domNode: Node): DOMConversionOutput | null {
  const figure = domNode as HTMLElement;
  const img = figure.querySelector('img');
  const src = img?.getAttribute('src');
  if (!img || !src) return null;
  const caption = (figure.querySelector('figcaption')?.textContent || '').replace(/^\s*📷\s*/, '').trim();
  return {
    node: $createImageNode({
      src,
      altText: img.getAttribute('alt') || '',
      caption,
      width: parseWidth(figure.getAttribute('data-width') ? figure : img),
      align: parseAlign(figure),
    }),
    // Bỏ qua phần con (img + figcaption) vì đã gói trọn trong ImageNode
    forChild: () => null,
  };
}

export class ImageNode extends DecoratorNode<React.ReactElement> {
  __src: string;
  __altText: string;
  __caption: string;
  __width: number;
  __align: ImageAlign;

  static getType(): string {
    return 'article-image';
  }

  static clone(node: ImageNode): ImageNode {
    return new ImageNode(
      { src: node.__src, altText: node.__altText, caption: node.__caption, width: node.__width, align: node.__align },
      node.__key
    );
  }

  static importJSON(json: SerializedImageNode): ImageNode {
    return $createImageNode(json);
  }

  static importDOM(): DOMConversionMap | null {
    return {
      figure: (node: Node) =>
        (node as HTMLElement).querySelector('img') ? { conversion: convertFigureElement, priority: 2 } : null,
      img: () => ({ conversion: convertImgElement, priority: 1 }),
    };
  }

  constructor(payload: ImagePayload, key?: NodeKey) {
    super(key);
    this.__src = payload.src;
    this.__altText = payload.altText || '';
    this.__caption = payload.caption || '';
    this.__width = payload.width || 100;
    this.__align = payload.align || 'center';
  }

  exportJSON(): SerializedImageNode {
    return {
      ...super.exportJSON(),
      type: 'article-image',
      version: 1,
      src: this.__src,
      altText: this.__altText,
      caption: this.__caption,
      width: this.__width,
      align: this.__align,
    };
  }

  /** Xuất HTML chuẩn SEO (figure + img alt + figcaption) để lưu DB / đăng WordPress. */
  exportDOM(): DOMExportOutput {
    const figure = document.createElement('figure');
    figure.setAttribute('data-align', this.__align);
    figure.setAttribute('data-width', String(this.__width));
    const marginByAlign: Record<ImageAlign, string> = {
      left: '16px auto 16px 0',
      center: '24px auto',
      right: '16px 0 16px auto',
    };
    figure.style.cssText = `margin: ${marginByAlign[this.__align]}; width: ${this.__width}%; text-align: center;`;

    const img = document.createElement('img');
    img.setAttribute('src', this.__src);
    img.setAttribute('alt', this.__altText);
    img.setAttribute('loading', 'lazy');
    img.style.cssText = 'width: 100%; height: auto; border-radius: 12px; object-fit: cover;';
    figure.appendChild(img);

    if (this.__caption) {
      const figcaption = document.createElement('figcaption');
      figcaption.textContent = this.__caption;
      figcaption.style.cssText = 'font-size: 13px; color: #64748b; font-style: italic; margin-top: 8px;';
      figure.appendChild(figcaption);
    }
    return { element: figure };
  }

  createDOM(config: EditorConfig): HTMLElement {
    const span = document.createElement('span');
    const className = config.theme.image as string | undefined;
    span.className = cn('block w-full', className);
    return span;
  }

  updateDOM(): false {
    return false;
  }

  getTextContent(): string {
    return this.__caption ? `${this.__caption}\n` : '';
  }

  setDetails(details: Partial<ImagePayload>): void {
    const writable = this.getWritable();
    if (details.src !== undefined) writable.__src = details.src;
    if (details.altText !== undefined) writable.__altText = details.altText;
    if (details.caption !== undefined) writable.__caption = details.caption;
    if (details.width !== undefined) writable.__width = details.width;
    if (details.align !== undefined) writable.__align = details.align;
  }

  decorate(): React.ReactElement {
    return (
      <ImageComponent
        nodeKey={this.getKey()}
        src={this.__src}
        altText={this.__altText}
        caption={this.__caption}
        width={this.__width}
        align={this.__align}
      />
    );
  }
}

export function $createImageNode(payload: ImagePayload): ImageNode {
  return $applyNodeReplacement(new ImageNode(payload, payload.key));
}

export function $isImageNode(node: LexicalNode | null | undefined): node is ImageNode {
  return node instanceof ImageNode;
}

const WIDTH_PRESETS = [25, 50, 75, 100];

function ImageComponent({
  nodeKey,
  src,
  altText,
  caption,
  width,
  align,
}: Required<Omit<ImagePayload, 'key'>> & { nodeKey: NodeKey }) {
  const [editor] = useLexicalComposerContext();
  const [isSelected, setSelected, clearSelection] = useLexicalNodeSelection(nodeKey);
  const imageRef = useRef<HTMLImageElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isDialogOpen, setDialogOpen] = useState(false);
  const [isUploading, setUploading] = useState(false);
  const [hasError, setHasError] = useState(false);

  const update = useCallback(
    (details: Partial<ImagePayload>) => {
      editor.update(() => {
        const node = $getNodeByKey(nodeKey);
        if ($isImageNode(node)) node.setDetails(details);
      });
    },
    [editor, nodeKey]
  );

  const remove = useCallback(() => {
    editor.update(() => {
      const node = $getNodeByKey(nodeKey);
      if ($isImageNode(node)) node.remove();
    });
  }, [editor, nodeKey]);

  useEffect(() => {
    const onDelete = (event: KeyboardEvent) => {
      if (isSelected && $isNodeSelection($getSelection())) {
        event.preventDefault();
        remove();
        return true;
      }
      return false;
    };
    return mergeRegister(
      editor.registerCommand<MouseEvent>(
        CLICK_COMMAND,
        (event) => {
          if (event.target === imageRef.current) {
            if (!event.shiftKey) clearSelection();
            setSelected(true);
            return true;
          }
          return false;
        },
        COMMAND_PRIORITY_LOW
      ),
      editor.registerCommand(KEY_DELETE_COMMAND, onDelete, COMMAND_PRIORITY_LOW),
      editor.registerCommand(KEY_BACKSPACE_COMMAND, onDelete, COMMAND_PRIORITY_LOW)
    );
  }, [editor, isSelected, clearSelection, setSelected, remove]);

  const handleReplaceFile = async (file?: File) => {
    if (!file) return;
    setUploading(true);
    try {
      const url = await uploadEditorImage(file, altText);
      update({ src: url });
      setHasError(false);
    } finally {
      setUploading(false);
    }
  };

  const editable = editor.isEditable();
  const justify = align === 'left' ? 'justify-start' : align === 'right' ? 'justify-end' : 'justify-center';

  return (
    <span className={cn('my-5 flex w-full', justify)} contentEditable={false}>
      <span className="relative inline-block" style={{ width: `${width}%` }}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          ref={imageRef}
          src={src}
          alt={altText}
          onError={() => setHasError(true)}
          onLoad={() => setHasError(false)}
          draggable={false}
          className={cn(
            'block w-full rounded-xl object-cover shadow-sm transition-shadow',
            editable && 'cursor-pointer',
            isSelected && 'ring-4 ring-sky-500 ring-offset-2',
            hasError && 'min-h-[120px] bg-slate-100'
          )}
        />
        {hasError && (
          <span className="absolute inset-0 flex items-center justify-center rounded-xl border border-dashed border-rose-300 bg-rose-50/90 text-xs font-medium text-rose-600">
            Không tải được ảnh — bấm chọn ảnh rồi bấm “Thay ảnh”
          </span>
        )}
        {isUploading && (
          <span className="absolute inset-0 flex items-center justify-center rounded-xl bg-white/70">
            <Loader2 className="h-6 w-6 animate-spin text-sky-600" />
          </span>
        )}

        {/* Thanh công cụ ảnh nổi khi chọn ảnh (giống block ảnh của WordPress) */}
        {editable && isSelected && (
          <span className="absolute -top-12 left-1/2 z-20 flex -translate-x-1/2 items-center gap-0.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
            {(['left', 'center', 'right'] as ImageAlign[]).map((a) => {
              const Icon = a === 'left' ? AlignLeft : a === 'right' ? AlignRight : AlignCenter;
              return (
                <button
                  key={a}
                  type="button"
                  title={a === 'left' ? 'Căn trái' : a === 'right' ? 'Căn phải' : 'Căn giữa'}
                  onClick={() => update({ align: a })}
                  className={cn('rounded p-1.5 hover:bg-slate-100', align === a && 'bg-sky-100 text-sky-700')}
                >
                  <Icon className="h-4 w-4" />
                </button>
              );
            })}
            <span className="mx-1 h-5 w-px bg-slate-200" />
            {WIDTH_PRESETS.map((w) => (
              <button
                key={w}
                type="button"
                title={`Chiều rộng ${w}%`}
                onClick={() => update({ width: w })}
                className={cn(
                  'rounded px-1.5 py-1 text-xs font-semibold hover:bg-slate-100',
                  width === w && 'bg-sky-100 text-sky-700'
                )}
              >
                {w}%
              </button>
            ))}
            <span className="mx-1 h-5 w-px bg-slate-200" />
            <button
              type="button"
              title="Thay ảnh từ máy tính"
              onClick={() => fileInputRef.current?.click()}
              className="flex items-center gap-1 rounded px-1.5 py-1 text-xs font-medium hover:bg-slate-100"
            >
              <ImageUp className="h-4 w-4" /> Thay ảnh
            </button>
            <button
              type="button"
              title="Sửa URL, văn bản thay thế (ALT) và chú thích"
              onClick={() => setDialogOpen(true)}
              className="flex items-center gap-1 rounded px-1.5 py-1 text-xs font-medium hover:bg-slate-100"
            >
              <Pencil className="h-4 w-4" /> Chi tiết
            </button>
            <button
              type="button"
              title="Xóa ảnh"
              onClick={remove}
              className="rounded p-1.5 text-rose-600 hover:bg-rose-50"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </span>
        )}

        {caption ? (
          <span
            className={cn(
              'mt-2 block text-center text-[13px] italic text-slate-500',
              editable && 'cursor-text hover:text-slate-700'
            )}
            onClick={() => editable && setDialogOpen(true)}
          >
            {caption}
          </span>
        ) : (
          editable &&
          isSelected && (
            <button
              type="button"
              onClick={() => setDialogOpen(true)}
              className="mt-2 block w-full text-center text-xs text-slate-400 hover:text-sky-600"
            >
              + Thêm chú thích ảnh
            </button>
          )
        )}

        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          className="hidden"
          onChange={(e) => {
            void handleReplaceFile(e.target.files?.[0]);
            e.target.value = '';
          }}
        />
      </span>

      <ImageDetailsDialog
        open={isDialogOpen}
        onOpenChange={setDialogOpen}
        title="Chỉnh sửa hình ảnh"
        submitLabel="Cập nhật ảnh"
        initial={{ src, altText, caption, width, align }}
        onSubmit={(details: ImageDetails) => update(details)}
      />
    </span>
  );
}
