<?php

namespace App\Services\Zalo;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Support\Facades\Log;

class ZaloWebhookService
{
    public function handle(array $payload): void
    {
        $event = $payload['event_name'] ?? '';
        if ($event !== 'user_submit_info' && $event !== 'user_send_text') {
            return;
        }

        $messageData = $payload['message'] ?? [];
        $text = $messageData['text'] ?? '';
        
        $data = json_decode($text, true);
        if (!$data || !isset($data['action'], $data['article_id'], $data['token'])) {
            return;
        }

        $article = Article::find($data['article_id']);
        if (!$article || $article->review_token !== $data['token']) {
            Log::warning("Zalo webhook invalid token or article ID");
            return;
        }

        if ($data['action'] === 'approve') {
            $article->status = ArticleStatus::APPROVED;
            $article->save();
            Log::info("Article {$article->id} approved via Zalo");
        } elseif ($data['action'] === 'reject') {
            $article->status = ArticleStatus::REJECTED;
            $article->rejection_reason = $data['reason'] ?? 'Rejected via Zalo webhook';
            $article->save();
            Log::info("Article {$article->id} rejected via Zalo");
        }
    }

    public function verifySignature(string $data, string $signature, string $timestamp, string $appSecret): bool
    {
        $mac = hash('sha256', $appSecret . $data . $timestamp);
        return hash_equals($mac, $signature);
    }
}
