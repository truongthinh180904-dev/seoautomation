<?php

namespace App\Services\SEO;

use App\DTOs\SerpResultDTO;
use App\Models\SerpCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SerpCacheService
{
    /**
     * Lấy kết quả SERP từ bộ nhớ đệm đa tầng (Redis Memory Cache -> Database SerpCache).
     *
     * @return SerpResultDTO[]|null
     */
    public function get(string $keyword, string $language = 'vi', string $country = 'vn', string $provider = 'serper'): ?array
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));
        $cacheKey = $this->buildCacheKey($normalizedKeyword, $language, $country, $provider);

        // 1. Kiểm tra bộ nhớ đệm Redis
        try {
            $redisCached = Cache::get($cacheKey);
            if (is_array($redisCached) && ! empty($redisCached)) {
                return array_map(
                    fn (array $row) => SerpResultDTO::fromArray($row),
                    $redisCached
                );
            }
        } catch (\Throwable $e) {
            Log::warning("[SerpCacheService] Redis get failed: {$e->getMessage()}");
        }

        // 2. Tra cứu từ bảng cơ sở dữ liệu SerpCache
        $cache = SerpCache::query()
            ->where('keyword', $normalizedKeyword)
            ->where('language', $language)
            ->where('country', $country)
            ->where('provider', $provider)
            ->where('expires_at', '>', now())
            ->first();

        if (! $cache || empty($cache->results)) {
            return null;
        }

        $results = $cache->results;

        // 3. Làm ấm lại (warm up) bộ nhớ đệm Redis
        try {
            $ttlSeconds = (int) max(60, now()->diffInSeconds($cache->expires_at, false));
            if ($ttlSeconds > 0) {
                Cache::put($cacheKey, $results, $ttlSeconds);
            }
        } catch (\Throwable $e) {
            Log::warning("[SerpCacheService] Redis warmup failed: {$e->getMessage()}");
        }

        return array_map(
            fn (array $row) => SerpResultDTO::fromArray($row),
            $results
        );
    }

    /**
     * Lưu trữ kết quả SERP vào cả cơ sở dữ liệu và Redis với thời gian TTL từ 7 đến 14 ngày.
     */
    public function put(string $keyword, array $results, string $language = 'vi', string $country = 'vn', string $provider = 'serper'): void
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));
        $ttlDays = (int) config('seo.serp_cache_ttl_days', 14);
        $expiresAt = now()->addDays(max(7, $ttlDays));

        $rawResults = array_map(fn (SerpResultDTO $dto) => [
            'position' => $dto->position,
            'url' => $dto->url,
            'title' => $dto->title,
            'description' => $dto->description,
            'domain' => $dto->domain,
            'raw_data' => $dto->rawData,
        ], $results);

        // Lưu Database SerpCache
        SerpCache::updateOrCreate(
            [
                'keyword' => $normalizedKeyword,
                'language' => $language,
                'country' => $country,
                'provider' => $provider,
            ],
            [
                'results' => $rawResults,
                'expires_at' => $expiresAt,
            ]
        );

        // Lưu Redis Cache
        try {
            $cacheKey = $this->buildCacheKey($normalizedKeyword, $language, $country, $provider);
            Cache::put($cacheKey, $rawResults, $expiresAt);
        } catch (\Throwable $e) {
            Log::warning("[SerpCacheService] Redis put failed: {$e->getMessage()}");
        }
    }

    /**
     * Xóa cache cho từ khóa cụ thể khi cần làm mới bắt buộc.
     */
    public function forget(string $keyword, string $language = 'vi', string $country = 'vn', string $provider = 'serper'): void
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));
        $cacheKey = $this->buildCacheKey($normalizedKeyword, $language, $country, $provider);

        try {
            Cache::forget($cacheKey);
        } catch (\Throwable $e) {
            Log::warning("[SerpCacheService] Redis forget failed: {$e->getMessage()}");
        }

        SerpCache::query()
            ->where('keyword', $normalizedKeyword)
            ->where('language', $language)
            ->where('country', $country)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * Tạo khóa định danh thống nhất cho Redis Cache.
     */
    protected function buildCacheKey(string $normalizedKeyword, string $language, string $country, string $provider): string
    {
        return sprintf(
            'serp:%s:%s:%s:%s',
            $provider,
            $language,
            $country,
            md5($normalizedKeyword)
        );
    }
}
