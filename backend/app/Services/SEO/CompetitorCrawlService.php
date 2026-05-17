<?php

namespace App\Services\SEO;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorCrawlService
{
    protected array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/120.0.0.0 Safari/537.36',
    ];

    public function crawl(string $url): array
    {
        $userAgent = $this->userAgents[array_rand($this->userAgents)];

        try {
            $response = Http::withHeaders([
                'User-Agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
            ])->timeout(15)->get($url);

            if (!$response->successful()) {
                throw new \Exception("HTTP Error: " . $response->status());
            }

            $html = $response->body();
            return $this->parseHtml($html);

        } catch (\Exception $e) {
            Log::warning("Crawl failed for {$url}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function parseHtml(string $html): array
    {
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOBLANKS | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Remove script and style tags
        $nodesToDelete = $xpath->query('//script | //style | //noscript | //nav | //header | //footer | //iframe | //svg');
        foreach ($nodesToDelete as $node) {
            $node->parentNode->removeChild($node);
        }

        // Extract headings
        $headings = [];
        $headingNodes = $xpath->query('//h1 | //h2 | //h3');
        foreach ($headingNodes as $node) {
            $headings[] = [
                'tag' => strtolower($node->nodeName),
                'text' => trim($node->textContent),
            ];
        }

        // Extract text content for word count
        $body = $dom->getElementsByTagName('body')->item(0);
        $textContent = $body ? trim($body->textContent) : '';
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $wordCount = str_word_count(strip_tags($textContent));

        // Identify FAQs (heuristic)
        $faqs = [];
        $questionNodes = $xpath->query('//h2 | //h3 | //strong | //b');
        foreach ($questionNodes as $node) {
            $text = trim($node->textContent);
            if (str_ends_with($text, '?') && mb_strlen($text) > 10) {
                $answerNode = $node->nextSibling;
                $answer = '';
                while ($answerNode) {
                    if ($answerNode->nodeType === XML_ELEMENT_NODE && in_array(strtolower($answerNode->nodeName), ['p', 'div', 'ul', 'ol', 'li'])) {
                        $answer .= trim($answerNode->textContent) . " ";
                    }
                    if ($answerNode->nodeType === XML_ELEMENT_NODE && in_array(strtolower($answerNode->nodeName), ['h1', 'h2', 'h3'])) {
                        break;
                    }
                    $answerNode = $answerNode->nextSibling;
                }
                
                if (trim($answer)) {
                    $faqs[] = [
                        'question' => $text,
                        'answer' => trim($answer)
                    ];
                }
            }
        }

        return [
            'word_count' => $wordCount,
            'heading_structure' => $headings,
            'faqs' => $faqs,
            'raw_text' => $textContent,
        ];
    }
}
