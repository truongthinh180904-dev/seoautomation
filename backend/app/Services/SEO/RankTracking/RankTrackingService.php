<?php

namespace App\Services\SEO\RankTracking;

use App\Exceptions\QuotaExceededException;
use App\Models\RankTrackedKeyword;
use App\Models\RankTrackingHistory;
use App\Models\Website;
use App\Services\Billing\CreditLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RankTrackingService
{
    /**
     * Max allowed tracked keywords by plan slug.
     */
    protected const PLAN_KEYWORD_LIMITS = [
        'free' => 10,
        'basic' => 30,
        'starter' => 30,
        'pro' => 100,
        'business' => 300,
        'agency' => 300,
        'enterprise' => 1000,
    ];

    public function __construct(
        protected SerperRankAdapter $adapter,
        protected ?CreditLedgerService $creditLedger = null
    ) {}

    /**
     * Retrieve paginated tracked keywords for a website with eager loaded history.
     */
    public function getTrackedKeywords(int $websiteId, array $filters = []): LengthAwarePaginator
    {
        $query = RankTrackedKeyword::query()
            ->where('website_id', $websiteId)
            ->with(['history' => function ($q) {
                $q->orderBy('check_date', 'desc')->limit(30);
            }]);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('keyword', 'like', "%{$search}%");
        }

        if (! empty($filters['device'])) {
            $query->where('device', $filters['device']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderBy('current_position', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Add a new tracked keyword, enforcing workspace plan quota limits.
     *
     * @throws QuotaExceededException
     */
    public function addTrackedKeyword(Website $website, array $data): RankTrackedKeyword
    {
        $this->enforcePlanLimit($website);

        $keyword = trim((string) ($data['keyword'] ?? ''));
        $country = strtolower(trim((string) ($data['country'] ?? 'vn')));
        $device = strtolower(trim((string) ($data['device'] ?? 'desktop')));
        $frequency = strtolower(trim((string) ($data['tracking_frequency'] ?? 'daily')));
        $targetUrl = ! empty($data['target_url']) ? trim((string) $data['target_url']) : null;
        $keywordId = ! empty($data['keyword_id']) ? (int) $data['keyword_id'] : null;

        return RankTrackedKeyword::create([
            'workspace_id' => $website->workspace_id,
            'website_id' => $website->id,
            'keyword_id' => $keywordId,
            'keyword' => $keyword,
            'target_url' => $targetUrl,
            'country' => $country,
            'device' => $device,
            'tracking_frequency' => $frequency,
            'is_active' => true,
        ]);
    }

    /**
     * Remove a tracked keyword and its associated history.
     */
    public function removeTrackedKeyword(RankTrackedKeyword $keyword): bool
    {
        return (bool) $keyword->delete();
    }

    /**
     * Track live rank for a keyword via Serper.dev and persist history idempotently.
     */
    public function trackKeyword(RankTrackedKeyword $keyword, ?string $checkDate = null): RankTrackingHistory
    {
        $website = $keyword->website ?? Website::find($keyword->website_id);
        $domain = $website?->domain
            ?: (parse_url($website?->url ?? '', PHP_URL_HOST) ?: 'localhost');

        $country = $keyword->country ?: 'vn';
        $language = $country === 'vn' ? 'vi' : 'en';

        $serpData = $this->adapter->search(
            keyword: $keyword->keyword,
            country: $country,
            language: $language,
            device: $keyword->device ?: 'desktop',
            num: 100
        );

        $ranking = $this->adapter->findRanking(
            domainOrUrl: $domain,
            organicResults: $serpData['organic'] ?? [],
            targetUrl: $keyword->target_url
        );

        $newPosition = $ranking ? (int) $ranking['position'] : null;
        $foundUrl = $ranking ? (string) $ranking['found_url'] : null;

        DB::transaction(function () use ($keyword, $newPosition) {
            $keyword->previous_position = $keyword->current_position;
            $keyword->current_position = $newPosition;

            if ($newPosition !== null) {
                if ($keyword->best_position === null || $newPosition < $keyword->best_position) {
                    $keyword->best_position = $newPosition;
                }
            }

            $keyword->last_checked_at = now();
            $keyword->save();
        });

        $date = $checkDate ?: Carbon::today()->toDateString();

        return RankTrackingHistory::updateOrCreate(
            [
                'rank_tracked_keyword_id' => $keyword->id,
                'check_date' => $date,
            ],
            [
                'workspace_id' => $keyword->workspace_id,
                'website_id' => $keyword->website_id,
                'position' => $newPosition,
                'found_url' => $foundUrl,
                'serp_features' => $serpData['serp_features'] ?? null,
            ]
        );
    }

    /**
     * Retrieve historical ranking points for a keyword within a date range.
     *
     * @return Collection<int, RankTrackingHistory>
     */
    public function getKeywordHistory(RankTrackedKeyword $keyword, int $days = 30): Collection
    {
        $cutoffDate = Carbon::today()->subDays($days)->toDateString();

        return RankTrackingHistory::query()
            ->where('rank_tracked_keyword_id', $keyword->id)
            ->where('check_date', '>=', $cutoffDate)
            ->orderBy('check_date', 'asc')
            ->get();
    }

    /**
     * Compute aggregated rank tracking summary stats for a website.
     *
     * @return array{
     *   total_keywords: int,
     *   top_3: int,
     *   top_10: int,
     *   top_30: int,
     *   top_100: int,
     *   out_of_100: int,
     *   improved: int,
     *   declined: int,
     *   unchanged: int
     * }
     */
    public function getWebsiteSummary(int $websiteId): array
    {
        $keywords = RankTrackedKeyword::query()
            ->where('website_id', $websiteId)
            ->where('is_active', true)
            ->get(['current_position', 'previous_position']);

        $total = $keywords->count();
        $top3 = 0;
        $top10 = 0;
        $top30 = 0;
        $top100 = 0;
        $outOf100 = 0;

        $improved = 0;
        $declined = 0;
        $unchanged = 0;

        foreach ($keywords as $kw) {
            $cur = $kw->current_position;
            $prev = $kw->previous_position;

            if ($cur !== null) {
                if ($cur <= 3) {
                    $top3++;
                }
                if ($cur <= 10) {
                    $top10++;
                }
                if ($cur <= 30) {
                    $top30++;
                }
                if ($cur <= 100) {
                    $top100++;
                }
            } else {
                $outOf100++;
            }

            if ($cur !== null && $prev !== null) {
                if ($cur < $prev) {
                    $improved++; // position lowered means better rank
                } elseif ($cur > $prev) {
                    $declined++;
                } else {
                    $unchanged++;
                }
            } elseif ($cur !== null && $prev === null) {
                $improved++;
            } elseif ($cur === null && $prev !== null) {
                $declined++;
            } else {
                $unchanged++;
            }
        }

        return [
            'total_keywords' => $total,
            'top_3' => $top3,
            'top_10' => $top10,
            'top_30' => $top30,
            'top_100' => $top100,
            'out_of_100' => $outOf100,
            'improved' => $improved,
            'declined' => $declined,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * Enforce tracked keyword quota according to workspace subscription plan.
     *
     * @throws QuotaExceededException
     */
    protected function enforcePlanLimit(Website $website): void
    {
        $workspace = $website->workspace;
        $planCode = 'starter';

        if ($workspace) {
            $activeSub = $workspace->subscriptions()
                ->where('status', 'active')
                ->with('plan')
                ->first();

            if ($activeSub?->plan?->code) {
                $planCode = $this->extractPlanCode($activeSub->plan->code);
            } elseif ($workspace->legacyTenant?->plan ?? $workspace->legacy_tenant?->plan) {
                $rawPlan = $workspace->legacyTenant?->plan ?? $workspace->legacy_tenant?->plan;
                $planCode = $this->extractPlanCode($rawPlan);
            }
        }

        $limit = self::PLAN_KEYWORD_LIMITS[$planCode] ?? self::PLAN_KEYWORD_LIMITS['starter'];
        $currentCount = RankTrackedKeyword::where('website_id', $website->id)->count();

        if ($currentCount >= $limit) {
            Log::warning("[RankTrackingService] Quota exceeded for website {$website->id} on plan {$planCode}: {$currentCount}/{$limit}");
            throw new QuotaExceededException("Gói dịch vụ hiện tại ({$planCode}) chỉ cho phép theo dõi tối đa {$limit} từ khóa. Vui lòng nâng cấp gói để thêm từ khóa mới.");
        }
    }

    /**
     * Normalize plan code to string value.
     */
    protected function extractPlanCode(mixed $code): string
    {
        if ($code instanceof \BackedEnum) {
            return strtolower((string) $code->value);
        }

        if ($code instanceof \UnitEnum) {
            return strtolower($code->name);
        }

        return strtolower((string) $code);
    }
}
