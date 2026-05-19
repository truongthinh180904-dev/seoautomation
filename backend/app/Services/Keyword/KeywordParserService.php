<?php

namespace App\Services\Keyword;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class KeywordParserService
{
    public function parseUploadedFile(UploadedFile $file): array
    {
        return $this->parseExcel(
            $file->getRealPath(),
            strtolower($file->getClientOriginalExtension())
        );
    }

    public function parseExcel(string $filePath, ?string $extension = null): array
    {
        try {
            $reader = $this->makeReader($filePath, $extension);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (Exception $e) {
            throw new Exception("Không đọc được file import: " . $e->getMessage());
        }

        if (empty($rows)) {
            return [];
        }

        $headers = array_map(fn($h) => $this->normalizeHeader((string) $h), array_shift($rows));
        
        $colMap = [
            'keyword' => -1,
            'campaign_id' => -1,
            'language' => -1,
            'search_volume' => -1,
            'difficulty' => -1,
            'priority' => -1,
            'search_intent' => -1,
            'wordpress_site_id' => -1,
            'scheduled_at' => -1,
            'pillar_topic' => -1,
            'content_cluster' => -1,
            'funnel_stage' => -1,
            'target_word_count' => -1,
            'target_url' => -1,
            'canonical_url' => -1,
            'brief_notes' => -1,
            'must_include_points' => -1,
            'avoid_topics' => -1,
            'reference_urls' => -1,
            'competitor_urls_override' => -1,
            'internal_links' => -1,
            'featured_image_url' => -1,
            'image_urls' => -1,
            'wp_category_ids' => -1,
            'wp_tag_names' => -1,
            'wp_slug' => -1,
            'wp_excerpt' => -1,
            'yoast_title' => -1,
            'yoast_description' => -1,
        ];

        foreach ($headers as $index => $header) {
            if (in_array($header, ['keyword', 'keywords', 'tu khoa', 'từ khóa', 'từ khoá'])) $colMap['keyword'] = $index;
            elseif (in_array($header, ['campaign_id', 'campaign id'])) $colMap['campaign_id'] = $index;
            elseif (in_array($header, ['language', 'lang', 'ngon ngu', 'ngôn ngữ'])) $colMap['language'] = $index;
            elseif (in_array($header, ['search_volume', 'search volume', 'volume', 'luot tim kiem', 'lượt tìm kiếm'])) $colMap['search_volume'] = $index;
            elseif (in_array($header, ['difficulty', 'do kho', 'độ khó'])) $colMap['difficulty'] = $index;
            elseif (in_array($header, ['priority', 'uu tien', 'ưu tiên'])) $colMap['priority'] = $index;
            elseif (in_array($header, ['search_intent', 'search intent', 'intent'])) $colMap['search_intent'] = $index;
            elseif (in_array($header, ['wordpress_site_id', 'website_id'])) $colMap['wordpress_site_id'] = $index;
            elseif (in_array($header, ['scheduled_at', 'len lich', 'lên lịch'])) $colMap['scheduled_at'] = $index;
            elseif (array_key_exists($header, $colMap)) $colMap[$header] = $index;
        }

        if ($colMap['keyword'] === -1) {
            $colMap['keyword'] = 0;
        }

        $parsedRows = [];
        foreach ($rows as $row) {
            $keyword = trim((string)($row[$colMap['keyword']] ?? ''));
            if (empty($keyword)) continue;

            $parsedRow = [
                'keyword' => $keyword,
                'campaign_id' => $this->numberOrNull($row, $colMap['campaign_id']),
                'language' => $this->stringOrNull($row, $colMap['language']) ?? 'vi',
                'search_volume' => $colMap['search_volume'] !== -1 && is_numeric($row[$colMap['search_volume']]) ? (int) $row[$colMap['search_volume']] : null,
                'difficulty' => $colMap['difficulty'] !== -1 && is_numeric($row[$colMap['difficulty']]) ? (int) $row[$colMap['difficulty']] : null,
                'priority' => $this->parsePriority($this->stringOrNull($row, $colMap['priority'])),
                'search_intent' => $this->stringOrNull($row, $colMap['search_intent']),
                'wordpress_site_id' => $colMap['wordpress_site_id'] !== -1 && is_numeric($row[$colMap['wordpress_site_id']]) ? (int) $row[$colMap['wordpress_site_id']] : null,
                'pillar_topic' => $this->stringOrNull($row, $colMap['pillar_topic']),
                'content_cluster' => $this->stringOrNull($row, $colMap['content_cluster']),
                'funnel_stage' => $this->stringOrNull($row, $colMap['funnel_stage']),
                'target_word_count' => $this->numberOrNull($row, $colMap['target_word_count']),
                'target_url' => $this->stringOrNull($row, $colMap['target_url']),
                'canonical_url' => $this->stringOrNull($row, $colMap['canonical_url']),
                'brief_notes' => $this->stringOrNull($row, $colMap['brief_notes']),
                'must_include_points' => $this->splitList($this->stringOrNull($row, $colMap['must_include_points'])),
                'avoid_topics' => $this->splitList($this->stringOrNull($row, $colMap['avoid_topics'])),
                'reference_urls' => $this->splitList($this->stringOrNull($row, $colMap['reference_urls'])),
                'competitor_urls_override' => $this->splitList($this->stringOrNull($row, $colMap['competitor_urls_override'])),
                'raw_import_row' => $this->combineRawRow($headers, $row),
                'template_version' => 'v2',
                'meta' => array_filter([
                    'internal_links' => $this->stringOrNull($row, $colMap['internal_links']),
                    'featured_image_url' => $this->stringOrNull($row, $colMap['featured_image_url']),
                    'image_urls' => $this->stringOrNull($row, $colMap['image_urls']),
                    'wp_category_ids' => $this->stringOrNull($row, $colMap['wp_category_ids']),
                    'wp_tag_names' => $this->stringOrNull($row, $colMap['wp_tag_names']),
                    'wp_slug' => $this->stringOrNull($row, $colMap['wp_slug']),
                    'wp_excerpt' => $this->stringOrNull($row, $colMap['wp_excerpt']),
                    'yoast_title' => $this->stringOrNull($row, $colMap['yoast_title']),
                    'yoast_description' => $this->stringOrNull($row, $colMap['yoast_description']),
                ], fn ($value) => $value !== null && $value !== ''),
            ];

            if ($colMap['scheduled_at'] !== -1 && !empty($row[$colMap['scheduled_at']])) {
                $parsedRow['scheduled_at'] = date('Y-m-d H:i:s', strtotime((string)$row[$colMap['scheduled_at']]));
            }

            if (empty($parsedRow['wordpress_site_id'])) {
                $parsedRow['wordpress_site_id'] = null;
            }

            $parsedRows[] = $parsedRow;

            if (count($parsedRows) >= 1000) {
                break;
            }
        }

        return $parsedRows;
    }

    protected function makeReader(string $filePath, ?string $extension)
    {
        $readerType = match ($extension) {
            'csv' => 'Csv',
            'xls' => 'Xls',
            'xlsx' => 'Xlsx',
            default => null,
        };

        $reader = $readerType
            ? IOFactory::createReader($readerType)
            : IOFactory::createReaderForFile($filePath);

        $reader->setReadDataOnly(true);

        return $reader;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return $header;
    }

    protected function stringOrNull(array $row, int $index): ?string
    {
        if ($index === -1 || !isset($row[$index])) {
            return null;
        }

        $value = trim((string) $row[$index]);

        return $value === '' ? null : $value;
    }

    protected function numberOrNull(array $row, int $index): ?int
    {
        $value = $this->stringOrNull($row, $index);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function parsePriority(?string $priority): int
    {
        if ($priority === null) {
            return 5;
        }

        if (is_numeric($priority)) {
            return max(1, min(10, (int) $priority));
        }

        return match (mb_strtolower($priority)) {
            'critical' => 10,
            'high' => 8,
            'medium' => 5,
            'low' => 2,
            default => 5,
        };
    }

    protected function splitList(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $items = array_values(array_filter(array_map('trim', preg_split('/[;|,]+/', $value) ?: [])));

        return empty($items) ? null : $items;
    }

    protected function combineRawRow(array $headers, array $row): array
    {
        $raw = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $raw[$header] = $row[$index] ?? null;
            }
        }

        return $raw;
    }
}
