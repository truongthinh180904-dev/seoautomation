<?php

namespace App\Services\Article;

use App\Models\Article;
use Illuminate\Support\Carbon;

class ArticlePipelineService
{
    public const STEPS = [
        'queued' => 'Đã đưa vào hàng đợi',
        'outline' => 'Tạo dàn ý',
        'writing' => 'Viết bài',
        'seo_metadata' => 'Tối ưu SEO',
        'quality_check' => 'Kiểm tra chất lượng',
        'media_download' => 'Tải ảnh',
        'media_upload' => 'Đẩy ảnh lên WordPress',
        'publishing' => 'Đăng WordPress',
        'done' => 'Hoàn tất',
    ];

    public function reset(Article $article): Article
    {
        $article->forceFill([
            'pipeline_status' => [
                'current' => 'queued',
                'steps' => $this->baseSteps(),
                'updated_at' => now()->toIso8601String(),
            ],
        ])->save();

        return $article->refresh();
    }

    public function start(Article $article, string $step, ?string $message = null): Article
    {
        return $this->writeStep($article, $step, 'running', $message);
    }

    public function complete(Article $article, string $step, ?string $message = null): Article
    {
        return $this->writeStep($article, $step, 'completed', $message);
    }

    public function fail(Article $article, string $step, string $error): Article
    {
        return $this->writeStep($article, $step, 'failed', null, $error);
    }

    private function writeStep(
        Article $article,
        string $step,
        string $status,
        ?string $message = null,
        ?string $error = null
    ): Article {
        $article->refresh();

        $pipeline = $article->pipeline_status ?: [
            'current' => $step,
            'steps' => $this->baseSteps(),
        ];

        $pipeline['steps'] = $pipeline['steps'] ?? $this->baseSteps();
        $pipeline['steps'][$step] = array_merge(
            $pipeline['steps'][$step] ?? $this->baseStep($step),
            [
                'status' => $status,
                'message' => $message,
                'error' => $error,
                'updated_at' => $this->now(),
            ]
        );

        if ($status === 'running' && empty($pipeline['steps'][$step]['started_at'])) {
            $pipeline['steps'][$step]['started_at'] = $this->now();
        }

        if (in_array($status, ['completed', 'failed'], true)) {
            $pipeline['steps'][$step]['finished_at'] = $this->now();
        }

        $pipeline['current'] = $status === 'failed' ? $step : $this->currentStep($pipeline['steps'], $step, $status);
        $pipeline['last_error'] = $error;
        $pipeline['updated_at'] = $this->now();

        $article->forceFill(['pipeline_status' => $pipeline])->save();

        return $article->refresh();
    }

    private function currentStep(array $steps, string $step, string $status): string
    {
        if ($status === 'running') {
            return $step;
        }

        foreach ($steps as $key => $payload) {
            if (($payload['status'] ?? 'pending') === 'running') {
                return $key;
            }
        }

        if ($step === 'done' || $status === 'completed') {
            return $step;
        }

        return 'queued';
    }

    private function baseSteps(): array
    {
        $steps = [];

        foreach (self::STEPS as $key => $label) {
            $steps[$key] = $this->baseStep($key);
        }

        return $steps;
    }

    private function baseStep(string $step): array
    {
        return [
            'key' => $step,
            'label' => self::STEPS[$step] ?? $step,
            'status' => 'pending',
            'message' => null,
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
        ];
    }

    private function now(): string
    {
        return Carbon::now()->toIso8601String();
    }
}
