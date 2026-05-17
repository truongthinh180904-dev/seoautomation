<?php

namespace App\Services\Keyword;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class KeywordParserService
{
    public function parseExcel(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (Exception $e) {
            throw new Exception("Could not read Excel file: " . $e->getMessage());
        }

        if (empty($rows)) {
            return [];
        }

        $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), array_shift($rows));
        
        $colMap = [
            'keyword' => -1,
            'search_volume' => -1,
            'difficulty' => -1,
            'priority' => -1,
            'wordpress_site_id' => -1,
            'scheduled_at' => -1,
        ];

        foreach ($headers as $index => $header) {
            if (in_array($header, ['keyword', 'từ khóa'])) $colMap['keyword'] = $index;
            elseif (in_array($header, ['search_volume', 'lượt tìm kiếm'])) $colMap['search_volume'] = $index;
            elseif (in_array($header, ['difficulty', 'độ khó'])) $colMap['difficulty'] = $index;
            elseif (in_array($header, ['priority', 'ưu tiên'])) $colMap['priority'] = $index;
            elseif (in_array($header, ['wordpress_site_id', 'website_id'])) $colMap['wordpress_site_id'] = $index;
            elseif (in_array($header, ['scheduled_at', 'lên lịch'])) $colMap['scheduled_at'] = $index;
        }

        if ($colMap['keyword'] === -1) {
            throw new Exception("Parse Exception: No keyword column found. Allowed headers: 'keyword' or 'từ khóa'.");
        }

        $parsedRows = [];
        foreach ($rows as $row) {
            $keyword = trim((string)($row[$colMap['keyword']] ?? ''));
            if (empty($keyword)) continue;

            $parsedRow = [
                'keyword' => $keyword,
                'search_volume' => $colMap['search_volume'] !== -1 && is_numeric($row[$colMap['search_volume']]) ? (int) $row[$colMap['search_volume']] : null,
                'difficulty' => $colMap['difficulty'] !== -1 && is_numeric($row[$colMap['difficulty']]) ? (int) $row[$colMap['difficulty']] : null,
                'priority' => $colMap['priority'] !== -1 && is_numeric($row[$colMap['priority']]) ? (int) $row[$colMap['priority']] : 5,
                'wordpress_site_id' => $colMap['wordpress_site_id'] !== -1 && is_numeric($row[$colMap['wordpress_site_id']]) ? (int) $row[$colMap['wordpress_site_id']] : null,
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
}
