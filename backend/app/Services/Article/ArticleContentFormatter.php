<?php

namespace App\Services\Article;

class ArticleContentFormatter
{
    public function normalize(string $content, ?string $title = null): string
    {
        $content = trim($content);
        $content = $this->unwrapJsonContent($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(["\\n", "\r\n", "\r"], "\n", $content);
        $content = preg_replace('/```(?:html|json)?|```/i', '', $content) ?? $content;

        if (!str_contains($content, '<')) {
            $content = $this->plainTextToHtml($content, $title);
        }

        $content = $this->stripDuplicateTitle($content, $title);
        $content = $this->normalizeHeadings($content);
        $content = $this->normalizeParagraphs($content);
        $content = $this->normalizeLists($content);
        $content = $this->removeEmptyTags($content);

        return trim($content);
    }

    public function wordCount(string $content): int
    {
        $text = trim(strip_tags($content));
        if ($text === '') {
            return 0;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    public function insertInternalLinks(string $content, string|array|null $links): array
    {
        $items = is_array($links) ? $links : $this->parseLinks((string) $links);
        $added = [];

        foreach ($items as $item) {
            $url = trim((string) ($item['url'] ?? ''));
            $anchor = trim((string) ($item['anchor'] ?? $item['anchor_text'] ?? ''));

            if ($url === '' || $anchor === '' || str_contains($content, 'href="' . e($url) . '"')) {
                continue;
            }

            $pattern = '/' . preg_quote($anchor, '/') . '(?![^<]*>|[^<>]*<\/a>)/iu';
            $replacement = '<a href="' . e($url) . '">' . e($anchor) . '</a>';
            $updated = preg_replace($pattern, $replacement, $content, 1);

            if (is_string($updated) && $updated !== $content) {
                $content = $updated;
                $added[] = ['url' => $url, 'anchor_text' => $anchor];
            }
        }

        return [$content, $added];
    }

    private function unwrapJsonContent(string $content): string
    {
        $clean = trim(preg_replace('/^```(?:json)?\s*(.*?)\s*```$/is', '$1', $content) ?? $content);
        $decoded = json_decode($clean, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['content'])) {
            return (string) $decoded['content'];
        }

        return $content;
    }

    private function plainTextToHtml(string $content, ?string $title): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));
        $html = '';

        foreach ($lines as $line) {
            if ($title && mb_strtolower($line) === mb_strtolower($title)) {
                continue;
            }

            if (preg_match('/^[-*•]\s+(.+)$/u', $line, $matches)) {
                $html .= '<ul><li>' . e($matches[1]) . '</li></ul>';
                continue;
            }

            if (mb_strlen($line) < 90 && !str_ends_with($line, '.')) {
                $html .= '<h2>' . e($line) . '</h2>';
                continue;
            }

            $html .= '<p>' . e($line) . '</p>';
        }

        return $html;
    }

    private function stripDuplicateTitle(string $content, ?string $title): string
    {
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content, 1) ?? $content;

        if ($title) {
            $quoted = preg_quote($title, '/');
            $content = preg_replace('/^\s*' . $quoted . '\s*/iu', '', $content, 1) ?? $content;
        }

        return $content;
    }

    private function normalizeHeadings(string $content): string
    {
        $content = preg_replace('/<h1([^>]*)>/i', '<h2$1>', $content) ?? $content;
        return preg_replace('/<\/h1>/i', '</h2>', $content) ?? $content;
    }

    private function normalizeParagraphs(string $content): string
    {
        $content = preg_replace('/(?:\n\s*){2,}/', "\n", $content) ?? $content;
        $content = preg_replace('/<p>\s*(<h[2-4][^>]*>.*?<\/h[2-4]>)\s*<\/p>/is', '$1', $content) ?? $content;

        return preg_replace('/<p>\s*<\/p>/i', '', $content) ?? $content;
    }

    private function normalizeLists(string $content): string
    {
        $content = preg_replace('/<\/ul>\s*<ul>/i', '', $content) ?? $content;
        return preg_replace('/<li>\s*<\/li>/i', '', $content) ?? $content;
    }

    private function removeEmptyTags(string $content): string
    {
        return preg_replace('/<(p|h2|h3|h4)[^>]*>\s*(?:&nbsp;)?\s*<\/\1>/i', '', $content) ?? $content;
    }

    private function parseLinks(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $items = [];
        foreach (preg_split('/[;\n]+/', $value) ?: [] as $chunk) {
            $parts = array_map('trim', explode('|', $chunk));
            if (!empty($parts[0])) {
                $items[] = [
                    'url' => $parts[0],
                    'anchor' => $parts[1] ?? parse_url($parts[0], PHP_URL_HOST) ?? $parts[0],
                ];
            }
        }

        return $items;
    }
}
