<?php

namespace App\Services\Zalo;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloNotificationService
{
    public function sendReviewNotification(Article $article): bool
    {
        $zaloOaToken = config('services.zalo.oa_token');
        $zaloUserId = $article->tenant->zalo_user_id ?? config('services.zalo.admin_user_id');

        if (!$zaloOaToken || !$zaloUserId) {
            Log::warning("Zalo OA Token or User ID missing, cannot send notification for article {$article->id}");
            return false;
        }

        $frontendUrl = config('app.frontend_url', config('app.url'));
        $reviewUrl = "{$frontendUrl}/articles/{$article->id}/review?token={$article->review_token}";

        $response = Http::withHeaders([
            'access_token' => $zaloOaToken,
        ])->post('https://openapi.zalo.me/v3.0/oa/message/cs', [
            'recipient' => [
                'user_id' => $zaloUserId
            ],
            'message' => [
                'text' => "Bài viết mới cần duyệt: {$article->title}\nTừ khoá: {$article->keyword->keyword}\nSEO Score: {$article->seo_score}\nWord Count: {$article->word_count}",
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'list',
                        'elements' => [
                            [
                                'title' => "Duyệt bài viết: {$article->title}",
                                'subtitle' => "Dài {$article->word_count} từ, điểm SEO: {$article->seo_score}",
                                'image_url' => $article->featured_image_url ?? '',
                                'default_action' => [
                                    'type' => 'oa.open.url',
                                    'url' => $reviewUrl
                                ]
                            ]
                        ],
                        'buttons' => [
                            [
                                'title' => 'Duyệt ngay',
                                'type' => 'oa.query.show',
                                'payload' => json_encode(['action' => 'approve', 'article_id' => $article->id, 'token' => $article->review_token])
                            ],
                            [
                                'title' => 'Từ chối',
                                'type' => 'oa.query.show',
                                'payload' => json_encode(['action' => 'reject', 'article_id' => $article->id, 'token' => $article->review_token])
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        if ($response->failed() || ($response->json('error') ?? 0) !== 0) {
            Log::error("Zalo notification failed: " . $response->body());
            return false;
        }

        return true;
    }
}
