<?php

namespace App\Services\SEO;

use App\Models\Article;
use Illuminate\Support\Str;

class SeoScoringEngine
{
    /**
     * Audit an existing Article model with optional live overrides from Content Studio.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function auditArticle(Article $article, array $overrides = [], bool $persist = true): array
    {
        $content = (string) ($overrides['content'] ?? $article->content ?? '');
        $title = (string) ($overrides['title'] ?? $article->title ?? '');
        $seoTitle = (string) ($overrides['seo_title'] ?? $article->seo_title ?? '');
        $seoDescription = (string) ($overrides['seo_description'] ?? $article->seo_description ?? '');

        $keyword = (string) ($overrides['focus_keyword']
            ?? $article->focus_keyword
            ?? $article->keyword?->keyword
            ?? '');

        $extra = [
            'faqs' => $overrides['faqs'] ?? $article->faqs ?? [],
            'internal_links' => $overrides['internal_links'] ?? $article->internal_links ?? [],
            'featured_image_url' => $article->featured_image_url,
            'image_assets' => $article->image_assets ?? [],
            'website_domain' => $article->website?->domain ?? '',
        ];

        $audit = $this->audit(
            content: $content,
            title: $title,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
            keyword: $keyword,
            extra: $extra
        );

        if ($persist) {
            $article->update([
                'seo_score' => $audit['total_score'],
                'readability_score' => $audit['readability_score'],
                'quality_report' => $audit,
            ]);
        }

        return $audit;
    }

    /**
     * Deterministic 100-point SEO & Readability scoring algorithm.
     * Evaluates 9 core SEO criteria without making external AI calls.
     * Execution time is typically < 5ms. Zero credit cost.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function audit(
        string $content,
        ?string $title = null,
        ?string $seoTitle = null,
        ?string $seoDescription = null,
        ?string $keyword = null,
        array $extra = []
    ): array {
        $startTime = microtime(true);

        $cleanContent = trim($content);
        $plainText = trim(strip_tags($cleanContent));
        $normalizedKeyword = trim((string) $keyword);

        $criteria = [];
        $recommendations = [];
        $warnings = [];

        // 1. Tiêu đề H1 (15 points)
        $h1Result = $this->evaluateH1Title($title, $seoTitle, $cleanContent, $normalizedKeyword);
        $criteria['h1_title'] = $h1Result;

        // 2. Thẻ Meta Description (10 points)
        $metaResult = $this->evaluateMetaDescription($seoDescription, $normalizedKeyword);
        $criteria['meta_description'] = $metaResult;

        // 3. Cấu trúc Heading H2, H3 (15 points)
        $headingResult = $this->evaluateHeadingStructure($cleanContent, $normalizedKeyword);
        $criteria['heading_structure'] = $headingResult;

        // 4. Mật độ từ khóa chính 1.0% - 2.0% (15 points)
        $densityResult = $this->evaluateKeywordDensity($plainText, $normalizedKeyword);
        $criteria['keyword_density'] = $densityResult;

        // 5. Hình ảnh & Thẻ ALT (10 points)
        $imageResult = $this->evaluateImageAlts($cleanContent, $normalizedKeyword, $extra);
        $criteria['image_alts'] = $imageResult;

        // 6. Độ dễ đọc & Số từ Readability (15 points)
        $readabilityResult = $this->evaluateReadability($cleanContent, $plainText);
        $criteria['readability'] = $readabilityResult;

        // 7. Liên kết Internal & External Links (10 points)
        $linkResult = $this->evaluateLinks($cleanContent, $extra);
        $criteria['links'] = $linkResult;

        // 8. Câu hỏi thường gặp FAQs (5 points)
        $faqResult = $this->evaluateFaqs($cleanContent, $extra);
        $criteria['faqs'] = $faqResult;

        // 9. Cấu trúc dữ liệu Schema Markup (5 points)
        $schemaResult = $this->evaluateSchemaMarkup($cleanContent, $extra);
        $criteria['schema_markup'] = $schemaResult;

        // Aggregate scores
        $totalScore = 0;
        $passedCount = 0;
        $failedCount = 0;
        $warningCount = 0;

        foreach ($criteria as $item) {
            $totalScore += $item['score'];
            if ($item['status'] === 'passed') {
                $passedCount++;
            } elseif ($item['status'] === 'warning') {
                $warningCount++;
                if (! empty($item['recommendations'])) {
                    $recommendations = array_merge($recommendations, $item['recommendations']);
                }
                if (! empty($item['warning_message'])) {
                    $warnings[] = $item['warning_message'];
                }
            } else {
                $failedCount++;
                if (! empty($item['recommendations'])) {
                    $recommendations = array_merge($recommendations, $item['recommendations']);
                }
                if (! empty($item['warning_message'])) {
                    $warnings[] = $item['warning_message'];
                }
            }
        }

        $totalScore = max(0, min(100, (int) round($totalScore)));

        // Overall rating status
        $status = match (true) {
            $totalScore >= 85 => 'excellent',
            $totalScore >= 70 => 'good',
            $totalScore >= 50 => 'needs_improvement',
            default => 'poor',
        };

        // Readability sub-score normalized to 0-100 scale
        $readabilityRaw = $readabilityResult['score'];
        $readabilityMax = $readabilityResult['max_score'];
        $readabilityScore = (int) round(($readabilityRaw / max(1, $readabilityMax)) * 100);

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $wordCount = $this->countWords($plainText);
        $charCount = mb_strlen($plainText, 'UTF-8');
        $readingTimeMinutes = max(1, (int) ceil($wordCount / 200));

        return [
            'total_score' => $totalScore,
            'seo_score' => $totalScore,
            'readability_score' => $readabilityScore,
            'status' => $status,
            'execution_time_ms' => $executionTimeMs,
            'criteria' => $criteria,
            'summary' => [
                'word_count' => $wordCount,
                'character_count' => $charCount,
                'reading_time_minutes' => $readingTimeMinutes,
                'keyword' => $normalizedKeyword,
                'keyword_count' => $densityResult['details']['keyword_count'] ?? 0,
                'keyword_density' => $densityResult['details']['density_percent'] ?? 0.0,
                'headings' => $headingResult['details']['counts'] ?? ['h1' => 0, 'h2' => 0, 'h3' => 0],
                'images_count' => $imageResult['details']['total_images'] ?? 0,
                'images_with_alt_count' => $imageResult['details']['images_with_alt'] ?? 0,
                'links_count' => $linkResult['details']['total_links'] ?? 0,
                'internal_links_count' => $linkResult['details']['internal_links'] ?? 0,
                'external_links_count' => $linkResult['details']['external_links'] ?? 0,
                'faqs_count' => $faqResult['details']['faq_count'] ?? 0,
                'has_schema' => $schemaResult['passed'],
                'criteria_summary' => [
                    'passed' => $passedCount,
                    'warning' => $warningCount,
                    'failed' => $failedCount,
                    'total' => count($criteria),
                ],
            ],
            'recommendations' => array_values(array_unique($recommendations)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * 1. Tiêu đề H1 (15 points).
     *
     * @return array<string, mixed>
     */
    protected function evaluateH1Title(?string $title, ?string $seoTitle, string $content, string $keyword): array
    {
        $maxScore = 15;
        $evalTitle = trim((string) ($seoTitle ?: $title));

        if ($evalTitle === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/iu', $content, $h1Match)) {
            $evalTitle = trim(strip_tags($h1Match[1]));
        }

        if ($evalTitle === '') {
            return [
                'name' => 'Tiêu đề H1',
                'score' => 0,
                'max_score' => $maxScore,
                'passed' => false,
                'status' => 'failed',
                'value' => '',
                'details' => ['length' => 0, 'has_keyword' => false, 'starts_with_keyword' => false],
                'message' => 'Bài viết chưa có tiêu đề hoặc thẻ H1.',
                'warning_message' => 'Thiếu tiêu đề chính H1 cho bài viết.',
                'recommendations' => ['Bổ sung tiêu đề H1 hấp dẫn có độ dài từ 40–60 ký tự và chứa từ khóa chính.'],
                'auto_fixable' => true,
            ];
        }

        $length = mb_strlen($evalTitle, 'UTF-8');
        $hasKeyword = false;
        $startsWithKeyword = false;

        if ($keyword !== '') {
            $pos = mb_stripos($evalTitle, $keyword, 0, 'UTF-8');
            $hasKeyword = ($pos !== false);
            $startsWithKeyword = ($pos !== false && $pos <= 15);
        }

        $lengthOk = ($length >= 40 && $length <= 65);
        $lengthAcceptable = ($length >= 30 && $length <= 75);

        $recs = [];
        $score = 0;

        if ($hasKeyword && $lengthOk && $startsWithKeyword) {
            $score = 15;
            $status = 'passed';
            $msg = 'Tiêu đề đạt chuẩn xuất sắc: độ dài lý tưởng và từ khóa nằm ở đầu câu.';
        } elseif ($hasKeyword && $lengthOk) {
            $score = 13;
            $status = 'passed';
            $msg = 'Tiêu đề chuẩn SEO: độ dài hợp lệ và chứa từ khóa chính.';
        } elseif ($hasKeyword && $lengthAcceptable) {
            $score = 11;
            $status = 'passed';
            $msg = 'Tiêu đề chứa từ khóa chính, độ dài ở mức chấp nhận được.';
            if ($length < 40) {
                $recs[] = 'Mở rộng tiêu đề lên ít nhất 40 ký tự để tăng tỷ lệ click (CTR).';
            } elseif ($length > 65) {
                $recs[] = 'Rút gọn tiêu đề dưới 65 ký tự để tránh bị Google cắt bớt trên kết quả tìm kiếm.';
            }
        } elseif ($hasKeyword) {
            $score = 8;
            $status = 'warning';
            $msg = 'Tiêu đề chứa từ khóa chính nhưng độ dài chưa tối ưu ('.$length.' ký tự).';
            $recs[] = 'Điều chỉnh độ dài tiêu đề vào khoảng 40–60 ký tự.';
        } elseif ($lengthOk) {
            $score = 7;
            $status = 'warning';
            $msg = 'Tiêu đề có độ dài tốt ('.$length.' ký tự) nhưng thiếu từ khóa chính.';
            $recs[] = 'Chèn từ khóa chính "'.$keyword.'" vào tiêu đề để tối ưu xếp hạng.';
        } else {
            $score = 3;
            $status = 'failed';
            $msg = 'Tiêu đề chưa tối ưu: không chứa từ khóa và độ dài không chuẩn.';
            $recs[] = 'Viết lại tiêu đề có độ dài 40–60 ký tự và chứa từ khóa chính "'.$keyword.'".';
        }

        return [
            'name' => 'Tiêu đề H1',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 11,
            'status' => $status,
            'value' => $evalTitle,
            'details' => [
                'length' => $length,
                'has_keyword' => $hasKeyword,
                'starts_with_keyword' => $startsWithKeyword,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => ! $hasKeyword || ! $lengthOk,
        ];
    }

    /**
     * 2. Thẻ Meta Description (10 points).
     *
     * @return array<string, mixed>
     */
    protected function evaluateMetaDescription(?string $seoDescription, string $keyword): array
    {
        $maxScore = 10;
        $desc = trim((string) $seoDescription);

        if ($desc === '') {
            return [
                'name' => 'Thẻ Meta Description',
                'score' => 0,
                'max_score' => $maxScore,
                'passed' => false,
                'status' => 'failed',
                'value' => '',
                'details' => ['length' => 0, 'has_keyword' => false, 'has_cta' => false],
                'message' => 'Bài viết chưa có thẻ Meta Description.',
                'warning_message' => 'Chưa cấu hình Meta Description cho bài viết.',
                'recommendations' => ['Bổ sung đoạn mô tả 120–160 ký tự chứa từ khóa chính và lời kêu gọi hành động (CTA).'],
                'auto_fixable' => true,
            ];
        }

        $length = mb_strlen($desc, 'UTF-8');
        $hasKeyword = false;
        if ($keyword !== '') {
            $hasKeyword = (mb_stripos($desc, $keyword, 0, 'UTF-8') !== false);
        }

        // Detect Call to action
        $hasCta = (bool) preg_match('/(\b|\s)(khám phá|tìm hiểu|xem ngay|đọc ngay|liên hệ|mua ngay|trải nghiệm|đăng ký|chi tiết|hướng dẫn|bấm vào|click|discover|learn more|find out|explore|get started|check out)(\b|\s|[.!?])/iu', $desc);

        $recs = [];
        $score = 0;

        $lengthOk = ($length >= 120 && $length <= 165);
        $lengthAcceptable = ($length >= 90 && $length <= 180);

        if ($hasKeyword && $lengthOk && $hasCta) {
            $score = 10;
            $status = 'passed';
            $msg = 'Meta Description hoàn hảo: độ dài 120–160 ký tự, có từ khóa và lời kêu gọi hành động.';
        } elseif ($hasKeyword && $lengthOk) {
            $score = 8;
            $status = 'passed';
            $msg = 'Meta Description đạt chuẩn độ dài và chứa từ khóa chính.';
            $recs[] = 'Thêm lời kêu gọi hành động (CTA) như "Khám phá ngay", "Xem chi tiết" để tăng CTR.';
        } elseif ($hasKeyword && $lengthAcceptable) {
            $score = 7;
            $status = 'passed';
            $msg = 'Meta Description chứa từ khóa chính nhưng độ dài ('.$length.' ký tự) nên điều chỉnh về 120–160 ký tự.';
            if ($length < 120) {
                $recs[] = 'Mở rộng Meta Description lên tối thiểu 120 ký tự để mô tả đầy đủ giá trị bài viết.';
            } else {
                $recs[] = 'Cắt gọn Meta Description dưới 160 ký tự tránh bị hiển thị dấu chấm lửng trên Google.';
            }
        } elseif ($hasKeyword) {
            $score = 5;
            $status = 'warning';
            $msg = 'Meta Description chứa từ khóa nhưng độ dài không phù hợp ('.$length.' ký tự).';
            $recs[] = 'Viết lại Meta Description trong khoảng 120–160 ký tự.';
        } elseif ($lengthOk) {
            $score = 5;
            $status = 'warning';
            $msg = 'Meta Description có độ dài tốt nhưng chưa chứa từ khóa chính.';
            $recs[] = 'Lồng ghép từ khóa "'.$keyword.'" tự nhiên vào thẻ mô tả.';
        } else {
            $score = 2;
            $status = 'failed';
            $msg = 'Meta Description chưa tối ưu về cả độ dài lẫn từ khóa.';
            $recs[] = 'Viết lại Meta Description 120–160 ký tự bao gồm từ khóa "'.$keyword.'".';
        }

        return [
            'name' => 'Thẻ Meta Description',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 7,
            'status' => $status,
            'value' => $desc,
            'details' => [
                'length' => $length,
                'has_keyword' => $hasKeyword,
                'has_cta' => $hasCta,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => true,
        ];
    }

    /**
     * 3. Cấu trúc Heading H2, H3 (15 points).
     *
     * @return array<string, mixed>
     */
    protected function evaluateHeadingStructure(string $content, string $keyword): array
    {
        $maxScore = 15;

        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/iu', $content, $matches, PREG_SET_ORDER);

        $counts = ['h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0];
        $h2WithKeyword = 0;
        $h2Texts = [];

        foreach ($matches as $match) {
            $level = (int) $match[1];
            $tagKey = 'h'.$level;
            if (isset($counts[$tagKey])) {
                $counts[$tagKey]++;
            }

            if ($level === 2) {
                $hText = trim(strip_tags($match[2]));
                $h2Texts[] = $hText;
                if ($keyword !== '' && mb_stripos($hText, $keyword, 0, 'UTF-8') !== false) {
                    $h2WithKeyword++;
                }
            }
        }

        $recs = [];
        $score = 0;

        if ($counts['h2'] >= 2 && $h2WithKeyword >= 1 && $counts['h3'] >= 1) {
            $score = 15;
            $status = 'passed';
            $msg = 'Cấu trúc heading xuất sắc: có '.$counts['h2'].' thẻ H2, '.$counts['h3'].' thẻ H3 và từ khóa xuất hiện trong tiêu đề phụ.';
        } elseif ($counts['h2'] >= 2 && $h2WithKeyword >= 1) {
            $score = 13;
            $status = 'passed';
            $msg = 'Cấu trúc H2 đạt chuẩn và có chứa từ khóa chính.';
            $recs[] = 'Bổ sung thêm một số thẻ H3 để phân cấp nội dung chi tiết hơn.';
        } elseif ($counts['h2'] >= 2 && $counts['h3'] >= 1) {
            $score = 11;
            $status = 'passed';
            $msg = 'Bài viết có bố cục heading phân cấp tốt nhưng chưa có thẻ H2 nào chứa từ khóa chính.';
            $recs[] = 'Đưa từ khóa chính "'.$keyword.'" vào ít nhất một thẻ H2 quan trọng.';
        } elseif ($counts['h2'] >= 2) {
            $score = 9;
            $status = 'warning';
            $msg = 'Có '.$counts['h2'].' thẻ H2, nhưng thiếu thẻ H3 và chưa lồng ghép từ khóa trong H2.';
            $recs[] = 'Đưa từ khóa chính vào tiêu đề H2 và bổ sung thẻ H3 cho các tiểu mục.';
        } elseif ($counts['h2'] === 1) {
            $score = 5;
            $status = 'warning';
            $msg = 'Bài viết mới chỉ có 1 thẻ H2, cấu trúc phân đoạn còn đơn điệu.';
            $recs[] = 'Phân chia bài viết thành ít nhất 2 đến 4 mục H2 chính.';
        } else {
            $score = 0;
            $status = 'failed';
            $msg = 'Bài viết hoàn toàn thiếu thẻ H2, không đạt tiêu chuẩn SEO phân cấp.';
            $recs[] = 'Tạo các thẻ tiêu đề H2 phân đoạn cho bài viết để bọ Google dễ dàng đọc hiểu cấu trúc.';
        }

        if ($counts['h1'] > 1) {
            $score = max(0, $score - 2);
            $recs[] = 'Nội dung chứa nhiều hơn 1 thẻ H1. Mỗi bài viết chỉ nên có duy nhất 1 thẻ H1.';
        }

        return [
            'name' => 'Cấu trúc Heading',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 11,
            'status' => $status,
            'value' => "H2: {$counts['h2']}, H3: {$counts['h3']}",
            'details' => [
                'counts' => $counts,
                'h2_with_keyword' => $h2WithKeyword,
                'h2_samples' => array_slice($h2Texts, 0, 3),
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => false,
        ];
    }

    /**
     * 4. Mật độ từ khóa chính 1.0% - 2.0% (15 points).
     *
     * @return array<string, mixed>
     */
    protected function evaluateKeywordDensity(string $plainText, string $keyword): array
    {
        $maxScore = 15;
        $totalWords = $this->countWords($plainText);

        if ($keyword === '' || $totalWords === 0) {
            return [
                'name' => 'Mật độ từ khóa',
                'score' => 0,
                'max_score' => $maxScore,
                'passed' => false,
                'status' => 'failed',
                'value' => '0%',
                'details' => ['keyword_count' => 0, 'total_words' => $totalWords, 'density_percent' => 0.0],
                'message' => 'Chưa có từ khóa chính hoặc bài viết chưa có nội dung.',
                'warning_message' => 'Thiếu từ khóa chính để phân tích mật độ SEO.',
                'recommendations' => ['Xác định từ khóa chính và chèn vào bài viết.'],
                'auto_fixable' => false,
            ];
        }

        $lowerText = mb_strtolower($plainText, 'UTF-8');
        $lowerKeyword = mb_strtolower($keyword, 'UTF-8');
        $keywordCount = mb_substr_count($lowerText, $lowerKeyword, 'UTF-8');

        $densityPercent = round(($keywordCount / max(1, $totalWords)) * 100, 2);

        // Check if keyword appears in the first 150 words (intro)
        $wordsArray = preg_split('/[\s\r\n]+/u', $lowerText, 160, PREG_SPLIT_NO_EMPTY) ?: [];
        $introSnippet = implode(' ', array_slice($wordsArray, 0, 150));
        $inIntro = (mb_stripos($introSnippet, $lowerKeyword, 0, 'UTF-8') !== false);

        $recs = [];
        $score = 0;

        if ($densityPercent >= 1.0 && $densityPercent <= 2.0 && $inIntro) {
            $score = 15;
            $status = 'passed';
            $msg = "Mật độ từ khóa hoàn hảo: {$densityPercent}% ({$keywordCount} lần) và có xuất hiện ở đoạn mở đầu.";
        } elseif ($densityPercent >= 0.8 && $densityPercent <= 2.2 && $inIntro) {
            $score = 13;
            $status = 'passed';
            $msg = "Mật độ từ khóa rất tốt: {$densityPercent}% ({$keywordCount} lần).";
        } elseif ($densityPercent >= 0.8 && $densityPercent <= 2.5) {
            $score = 10;
            $status = 'passed';
            $msg = "Mật độ từ khóa ở mức an toàn: {$densityPercent}%.";
            if (! $inIntro) {
                $recs[] = 'Đưa từ khóa chính vào đoạn mở đầu (100–150 từ đầu tiên) để định hướng chủ đề cho Google bot.';
            }
        } elseif ($densityPercent >= 0.4 && $densityPercent < 0.8) {
            $score = 7;
            $status = 'warning';
            $msg = "Mật độ từ khóa hơi thấp ({$densityPercent}%). Khuyến nghị nâng lên từ 1.0%–2.0%.";
            $recs[] = "Bổ sung thêm từ khóa '{$keyword}' vào các đoạn phân tích để đạt mật độ 1.0%–2.0%.";
        } elseif ($densityPercent > 2.5 && $densityPercent <= 3.5) {
            $score = 5;
            $status = 'warning';
            $msg = "Cảnh báo nhồi nhét từ khóa (Keyword Stuffing): mật độ {$densityPercent}% hơi cao ({$keywordCount} lần).";
            $recs[] = 'Giảm bớt tần suất lặp lại từ khóa chính, thay thế bằng các từ đồng nghĩa hoặc thực thể LSI.';
        } elseif ($densityPercent > 3.5) {
            $score = 2;
            $status = 'failed';
            $msg = "Nguy cơ cao bị phạt nhồi nhét từ khóa: mật độ {$densityPercent}% ({$keywordCount} lần).";
            $recs[] = 'Ngay lập tức cắt giảm số lần xuất hiện từ khóa chính để tránh án phạt từ Google Panda/Spam Brain.';
        } else {
            $score = 1;
            $status = 'failed';
            $msg = "Từ khóa chính gần như không xuất hiện trong bài viết ({$keywordCount} lần, {$densityPercent}%).";
            $recs[] = "Lồng ghép từ khóa '{$keyword}' vào nội dung với mật độ tiêu chuẩn 1.0%–2.0%.";
        }

        return [
            'name' => 'Mật độ từ khóa',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 10,
            'status' => $status,
            'value' => "{$densityPercent}% ({$keywordCount} lần)",
            'details' => [
                'keyword_count' => $keywordCount,
                'total_words' => $totalWords,
                'density_percent' => $densityPercent,
                'appears_in_intro' => $inIntro,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => false,
        ];
    }

    /**
     * 5. Hình ảnh & Thẻ ALT (10 points).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function evaluateImageAlts(string $content, string $keyword, array $extra): array
    {
        $maxScore = 10;

        preg_match_all('/<img\s+[^>]*>/iu', $content, $imgMatches);
        $imgTags = $imgMatches[0] ?? [];
        $totalImages = count($imgTags);

        // Fallback check: featured image in model
        $hasFeaturedImage = ! empty($extra['featured_image_url']) || ! empty($extra['image_assets']);

        if ($totalImages === 0 && ! $hasFeaturedImage) {
            return [
                'name' => 'Hình ảnh & Thẻ ALT',
                'score' => 2,
                'max_score' => $maxScore,
                'passed' => false,
                'status' => 'warning',
                'value' => '0 ảnh',
                'details' => ['total_images' => 0, 'images_with_alt' => 0, 'keyword_in_alt' => false],
                'message' => 'Bài viết chưa có hình ảnh minh họa nào.',
                'warning_message' => 'Thiếu hình ảnh minh họa cho bài viết.',
                'recommendations' => ['Chèn thêm ít nhất 1–2 hình ảnh minh họa kèm thẻ alt chứa từ khóa chuẩn SEO.'],
                'auto_fixable' => true,
            ];
        }

        if ($totalImages === 0 && $hasFeaturedImage) {
            return [
                'name' => 'Hình ảnh & Thẻ ALT',
                'score' => 7,
                'max_score' => $maxScore,
                'passed' => true,
                'status' => 'passed',
                'value' => '1 ảnh đại diện',
                'details' => ['total_images' => 1, 'images_with_alt' => 1, 'keyword_in_alt' => false],
                'message' => 'Bài viết đã có ảnh đại diện (Featured Image).',
                'recommendations' => ['Chèn thêm hình ảnh minh họa trong các đoạn nội dung H2.'],
                'auto_fixable' => true,
            ];
        }

        $imagesWithAlt = 0;
        $keywordInAlt = false;

        foreach ($imgTags as $imgTag) {
            if (preg_match('/alt=(["\'])(.*?)\1/iu', $imgTag, $altMatch)) {
                $altText = trim($altMatch[2]);
                if ($altText !== '') {
                    $imagesWithAlt++;
                    if ($keyword !== '' && mb_stripos($altText, $keyword, 0, 'UTF-8') !== false) {
                        $keywordInAlt = true;
                    }
                }
            }
        }

        $allHaveAlt = ($imagesWithAlt === $totalImages);
        $recs = [];
        $score = 0;

        if ($allHaveAlt && $keywordInAlt) {
            $score = 10;
            $status = 'passed';
            $msg = "Hoàn hảo: {$totalImages}/{$totalImages} ảnh có thẻ ALT và có ảnh chứa từ khóa chính.";
        } elseif ($allHaveAlt) {
            $score = 8;
            $status = 'passed';
            $msg = "Toàn bộ {$totalImages} ảnh đều có thẻ ALT đầy đủ.";
            $recs[] = "Thêm từ khóa chính '{$keyword}' vào thuộc tính ALT của ít nhất 1 hình ảnh.";
        } elseif ($imagesWithAlt > 0) {
            $score = 5;
            $status = 'warning';
            $msg = "Mới chỉ có {$imagesWithAlt}/{$totalImages} hình ảnh có thẻ mô tả ALT.";
            $recs[] = 'Bổ sung thẻ alt cho các hình ảnh còn thiếu để tối ưu tìm kiếm hình ảnh Google Images.';
        } else {
            $score = 2;
            $status = 'failed';
            $msg = "Có {$totalImages} hình ảnh nhưng toàn bộ đều thiếu thẻ ALT.";
            $recs[] = 'Điền thuộc tính alt cho toàn bộ hình ảnh trong bài viết.';
        }

        return [
            'name' => 'Hình ảnh & Thẻ ALT',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 7,
            'status' => $status,
            'value' => "{$imagesWithAlt}/{$totalImages} có ALT",
            'details' => [
                'total_images' => $totalImages,
                'images_with_alt' => $imagesWithAlt,
                'keyword_in_alt' => $keywordInAlt,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => true,
        ];
    }

    /**
     * 6. Độ dễ đọc & Số từ Readability (15 points).
     *
     * @return array<string, mixed>
     */
    protected function evaluateReadability(string $content, string $plainText): array
    {
        $maxScore = 15;
        $wordCount = $this->countWords($plainText);

        if ($wordCount < 100) {
            return [
                'name' => 'Độ dễ đọc (Readability)',
                'score' => 2,
                'max_score' => $maxScore,
                'passed' => false,
                'status' => 'failed',
                'value' => "{$wordCount} từ",
                'details' => [
                    'word_count' => $wordCount,
                    'avg_words_per_sentence' => 0,
                    'long_paragraphs_count' => 0,
                ],
                'message' => 'Nội dung quá ngắn, chưa đủ dữ liệu để xếp hạng SEO.',
                'warning_message' => 'Nội dung bài viết quá ngắn (< 100 từ).',
                'recommendations' => ['Phát triển bài viết đạt tối thiểu 800–1200 từ để cung cấp thông tin chuyên sâu.'],
                'auto_fixable' => false,
            ];
        }

        // Split sentences
        $sentences = preg_split('/[.!?]+[\s\r\n]+/u', $plainText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $validSentences = array_filter($sentences, fn ($s) => mb_strlen(trim($s), 'UTF-8') > 10);
        $sentenceCount = max(1, count($validSentences));
        $avgWordsPerSentence = round($wordCount / $sentenceCount, 1);

        // Analyze paragraphs
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $content, $pMatches);
        $paragraphs = $pMatches[1] ?? [];
        $longParagraphsCount = 0;

        foreach ($paragraphs as $p) {
            $pWords = $this->countWords(strip_tags($p));
            if ($pWords > 140) {
                $longParagraphsCount++;
            }
        }

        $recs = [];
        $score = 0;

        $wordScore = match (true) {
            $wordCount >= 1000 => 8,
            $wordCount >= 700 => 7,
            $wordCount >= 500 => 5,
            $wordCount >= 300 => 3,
            default => 1,
        };

        $sentenceScore = match (true) {
            $avgWordsPerSentence >= 10 && $avgWordsPerSentence <= 22 => 4,
            $avgWordsPerSentence > 22 && $avgWordsPerSentence <= 28 => 3,
            default => 1,
        };

        $paraScore = ($longParagraphsCount === 0) ? 3 : max(1, 3 - $longParagraphsCount);

        $score = $wordScore + $sentenceScore + $paraScore;

        if ($score >= 13) {
            $status = 'passed';
            $msg = "Văn phong trôi chảy, bài viết dài {$wordCount} từ với câu cú gãy gọn (TB {$avgWordsPerSentence} từ/câu).";
        } elseif ($score >= 10) {
            $status = 'passed';
            $msg = "Độ dễ đọc tốt ({$wordCount} từ, TB {$avgWordsPerSentence} từ/câu).";
            if ($wordCount < 1000) {
                $recs[] = 'Mở rộng nội dung lên trên 1000 từ để tăng cơ hội cạnh tranh top 3.';
            }
        } elseif ($score >= 6) {
            $status = 'warning';
            $msg = "Bài viết ở mức trung bình ({$wordCount} từ). Cần tối ưu thêm độ dài câu và phân đoạn.";
            if ($avgWordsPerSentence > 25) {
                $recs[] = 'Tách các câu quá dài thành các câu đơn ngắn gọn hơn để người đọc trên di động dễ theo dõi.';
            }
            if ($longParagraphsCount > 0) {
                $recs[] = 'Ngắt bớt các đoạn văn dài thành các đoạn 2–3 dòng thoáng mắt.';
            }
        } else {
            $status = 'failed';
            $msg = "Nội dung ngắn ({$wordCount} từ) và cấu trúc câu phức tạp.";
            $recs[] = 'Viết bổ sung nội dung và chia nhỏ đoạn văn.';
        }

        return [
            'name' => 'Độ dễ đọc (Readability)',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 10,
            'status' => $status,
            'value' => "{$wordCount} từ, {$avgWordsPerSentence} từ/câu",
            'details' => [
                'word_count' => $wordCount,
                'avg_words_per_sentence' => $avgWordsPerSentence,
                'long_paragraphs_count' => $longParagraphsCount,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => false,
        ];
    }

    /**
     * 7. Liên kết Internal & External Links (10 points).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function evaluateLinks(string $content, array $extra): array
    {
        $maxScore = 10;

        preg_match_all('/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/isu', $content, $matches, PREG_SET_ORDER);

        $totalLinks = count($matches);
        $internalLinks = 0;
        $externalLinks = 0;
        $genericAnchorCount = 0;
        $domain = $extra['website_domain'] ?? '';

        $genericWords = ['tại đây', 'click here', 'xem thêm', 'link', 'ở đây', 'bấm vào đây', 'here'];

        foreach ($matches as $match) {
            $href = trim($match[2]);
            $anchor = trim(strip_tags($match[3]));

            if (in_array(mb_strtolower($anchor, 'UTF-8'), $genericWords, true)) {
                $genericAnchorCount++;
            }

            if (str_starts_with($href, '/') || str_starts_with($href, '#') || ($domain !== '' && str_contains($href, $domain))) {
                $internalLinks++;
            } elseif (preg_match('/^https?:\/\//i', $href)) {
                $externalLinks++;
            } else {
                $internalLinks++;
            }
        }

        // Check fallback in extra internal_links array
        if ($internalLinks === 0 && ! empty($extra['internal_links']) && is_array($extra['internal_links'])) {
            $internalLinks = count($extra['internal_links']);
            $totalLinks += $internalLinks;
        }

        $recs = [];
        $score = 0;

        if ($internalLinks >= 2 && $externalLinks >= 1 && $genericAnchorCount === 0) {
            $score = 10;
            $status = 'passed';
            $msg = "Hệ thống liên kết xuất sắc: {$internalLinks} liên kết nội bộ và {$externalLinks} liên kết tham chiếu ngoài.";
        } elseif ($internalLinks >= 2) {
            $score = 8;
            $status = 'passed';
            $msg = "Có {$internalLinks} liên kết nội bộ tốt.";
            if ($externalLinks === 0) {
                $recs[] = 'Bổ sung thêm 1 liên kết ngoài trỏ tới tài liệu uy tín (Wikipedia, báo cáo chuyên ngành) để tăng độ trust.';
            }
        } elseif ($internalLinks >= 1) {
            $score = 6;
            $status = 'warning';
            $msg = "Bài viết mới có {$internalLinks} liên kết nội bộ.";
            $recs[] = 'Chèn thêm ít nhất 1–2 liên kết nội bộ dẫn tới các chủ đề liên quan trong website.';
        } elseif ($externalLinks >= 1) {
            $score = 4;
            $status = 'warning';
            $msg = 'Có liên kết ra ngoài nhưng thiếu liên kết nội bộ (Internal Link).';
            $recs[] = 'Thêm 2–3 liên kết nội bộ để giữ chân người dùng và điều hướng PageRank.';
        } else {
            $score = 1;
            $status = 'failed';
            $msg = 'Bài viết hoàn toàn chưa có liên kết nào (cô lập/mồ côi).';
            $recs[] = 'Bổ sung liên kết nội bộ và liên kết tham chiếu hữu ích.';
        }

        if ($genericAnchorCount > 0) {
            $recs[] = 'Thay thế các từ khóa neo vô nghĩa như "tại đây", "click here" bằng các từ khóa ngữ nghĩa rõ ràng.';
        }

        return [
            'name' => 'Liên kết (Internal & External)',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 7,
            'status' => $status,
            'value' => "{$internalLinks} internal, {$externalLinks} external",
            'details' => [
                'total_links' => $totalLinks,
                'internal_links' => $internalLinks,
                'external_links' => $externalLinks,
                'generic_anchors' => $genericAnchorCount,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => false,
        ];
    }

    /**
     * 8. Câu hỏi thường gặp FAQs (5 points).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function evaluateFaqs(string $content, array $extra): array
    {
        $maxScore = 5;
        $faqCount = 0;

        if (! empty($extra['faqs']) && is_array($extra['faqs'])) {
            $faqCount = count($extra['faqs']);
        }

        if ($faqCount === 0) {
            // Check headings in content
            if (preg_match('/<h[2-4][^>]*>.*?(câu hỏi thường gặp|thắc mắc|faq|frequently asked).*?<\/h[2-4]>/iu', $content)) {
                // Count subquestions
                $matches = preg_match_all('/<h[3-5][^>]*>(.*?\?|.*?)<\/h[3-5]>/iu', $content);
                $faqCount = max(2, $matches);
            }
        }

        $recs = [];
        if ($faqCount >= 3) {
            $score = 5;
            $status = 'passed';
            $msg = "Mục FAQ phong phú với {$faqCount} câu hỏi đáp, đáp ứng tốt ý định tìm kiếm.";
        } elseif ($faqCount >= 1) {
            $score = 3;
            $status = 'passed';
            $msg = "Đã có {$faqCount} câu hỏi FAQ.";
            $recs[] = 'Bổ sung thêm 1–2 câu hỏi FAQ nữa để tăng khả năng xuất hiện trên Google People Also Ask.';
        } else {
            $score = 0;
            $status = 'warning';
            $msg = 'Bài viết chưa có mục Câu hỏi thường gặp (FAQ).';
            $recs[] = 'Thêm 3–5 câu hỏi thường gặp để tăng cơ hội hiển thị trên Google Rich Snippets.';
        }

        return [
            'name' => 'Câu hỏi thường gặp (FAQs)',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 3,
            'status' => $status,
            'value' => "{$faqCount} FAQs",
            'details' => ['faq_count' => $faqCount],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => true,
        ];
    }

    /**
     * 9. Cấu trúc dữ liệu Schema Markup (5 points).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function evaluateSchemaMarkup(string $content, array $extra): array
    {
        $maxScore = 5;
        $hasSchema = false;
        $schemaTypes = [];

        if (preg_match_all('/<script\s+type=(["\'])application\/ld\+json\1[^>]*>(.*?)<\/script>/isu', $content, $matches)) {
            foreach ($matches[2] as $jsonStr) {
                $decoded = json_decode(trim($jsonStr), true);
                if (is_array($decoded)) {
                    $hasSchema = true;
                    if (isset($decoded['@type'])) {
                        $schemaTypes[] = is_array($decoded['@type']) ? implode(', ', $decoded['@type']) : $decoded['@type'];
                    }
                }
            }
        }

        if (! $hasSchema && ! empty($extra['schema'])) {
            $hasSchema = true;
            $schemaTypes[] = 'ConfiguredSchema';
        }

        $recs = [];
        if ($hasSchema && count($schemaTypes) > 0) {
            $score = 5;
            $status = 'passed';
            $typesStr = implode(', ', array_unique($schemaTypes));
            $msg = "Đã tích hợp Schema dữ liệu có cấu trúc hợp lệ ({$typesStr}).";
        } elseif ($hasSchema) {
            $score = 3;
            $status = 'passed';
            $msg = 'Đã có thẻ JSON-LD Schema trong bài viết.';
        } else {
            $score = 0;
            $status = 'warning';
            $msg = 'Chưa có cấu trúc dữ liệu Schema (Article / FAQPage) trong bài viết.';
            $recs[] = 'Tích hợp thẻ Schema JSON-LD Article và FAQPage để hiển thị Rich Snippets trên kết quả Google.';
        }

        return [
            'name' => 'Cấu trúc dữ liệu Schema',
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= 3,
            'status' => $status,
            'value' => $hasSchema ? implode(', ', $schemaTypes) : 'Chưa có',
            'details' => [
                'has_schema' => $hasSchema,
                'detected_types' => $schemaTypes,
            ],
            'message' => $msg,
            'warning_message' => $status !== 'passed' ? $msg : null,
            'recommendations' => $recs,
            'auto_fixable' => true,
        ];
    }

    /**
     * Generate Schema JSON-LD snippet helper for Auto-Fix.
     *
     * @return array<string, mixed>
     */
    public function generateSchemaSnippet(Article $article): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->seo_title ?: $article->title,
            'description' => $article->seo_description ?: Str::limit(strip_tags((string) $article->content), 150),
            'datePublished' => $article->published_at?->toIso8601String() ?? now()->toIso8601String(),
            'dateModified' => $article->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $article->user?->name ?? 'SEO Expert',
            ],
        ];

        if ($article->featured_image_url) {
            $schema['image'] = [$article->featured_image_url];
        }

        if (! empty($article->faqs) && is_array($article->faqs)) {
            $mainEntity = [];
            foreach ($article->faqs as $faq) {
                if (! empty($faq['question']) && ! empty($faq['answer'])) {
                    $mainEntity[] = [
                        '@type' => 'Question',
                        'name' => $faq['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $faq['answer'],
                        ],
                    ];
                }
            }
            if (count($mainEntity) > 0) {
                return [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        $schema,
                        [
                            '@type' => 'FAQPage',
                            'mainEntity' => $mainEntity,
                        ],
                    ],
                ];
            }
        }

        return $schema;
    }

    /**
     * Count words in a multi-byte, unicode-safe manner.
     */
    public function countWords(string $text): int
    {
        $clean = trim(strip_tags($text));
        if ($clean === '') {
            return 0;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $clean, $matches);

        return count($matches[0] ?? []);
    }
}
