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
            'search_volume' => -1,
            'difficulty' => -1,
            'priority' => -1,
            'wordpress_site_id' => -1,
            'scheduled_at' => -1,
        ];

        foreach ($headers as $index => $header) {
            if (in_array($header, ['keyword', 'keywords', 'tu khoa', 'từ khóa', 'từ khoá'])) $colMap['keyword'] = $index;
            elseif (in_array($header, ['search_volume', 'search volume', 'volume', 'luot tim kiem', 'lượt tìm kiếm'])) $colMap['search_volume'] = $index;
            elseif (in_array($header, ['difficulty', 'do kho', 'độ khó'])) $colMap['difficulty'] = $index;
            elseif (in_array($header, ['priority', 'uu tien', 'ưu tiên'])) $colMap['priority'] = $index;
            elseif (in_array($header, ['wordpress_site_id', 'website_id'])) $colMap['wordpress_site_id'] = $index;
            elseif (in_array($header, ['scheduled_at', 'len lich', 'lên lịch'])) $colMap['scheduled_at'] = $index;
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
}
