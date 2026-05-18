<?php

namespace App\Services\AI;

class JsonResponseParser
{
    public function parse(string $content): ?array
    {
        $content = $this->clean($content);
        $decoded = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $json = $this->extractJsonObject($content);
        if (!$json) {
            return null;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : null;
    }

    public function preview(string $content, int $length = 500): string
    {
        $content = preg_replace('/\s+/', ' ', $this->clean($content)) ?? $content;

        return mb_substr($content, 0, $length);
    }

    public function clean(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $content, $matches)) {
            return trim($matches[1]);
        }

        return $content;
    }

    protected function extractJsonObject(string $content): ?string
    {
        $objectStart = strpos($content, '{');
        $objectEnd = strrpos($content, '}');
        $arrayStart = strpos($content, '[');
        $arrayEnd = strrpos($content, ']');

        if ($objectStart !== false && $objectEnd !== false && $objectEnd > $objectStart) {
            return substr($content, $objectStart, $objectEnd - $objectStart + 1);
        }

        if ($arrayStart !== false && $arrayEnd !== false && $arrayEnd > $arrayStart) {
            return substr($content, $arrayStart, $arrayEnd - $arrayStart + 1);
        }

        return null;
    }
}
