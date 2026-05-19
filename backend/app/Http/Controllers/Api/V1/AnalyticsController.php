<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Cost\CostTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analytics,
        protected CostTrackingService $costTracking
    ) {}

    /**
     * Article counts per status + word count / SEO score averages.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json(
            $this->analytics->summary($request->user()->tenant_id)
        );
    }

    /**
     * AI cost per day for last N days.
     */
    public function aiCosts(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);

        return response()->json(
            $this->analytics->aiCosts($request->user()->tenant_id, $days)
        );
    }

    /**
     * Keywords processed per day for last N days.
     */
    public function keywordsDaily(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);

        return response()->json(
            $this->analytics->keywordsDaily($request->user()->tenant_id, $days)
        );
    }

    /**
     * Top failing agents by error count.
     */
    public function failingAgents(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);

        return response()->json(
            $this->analytics->failingAgents($request->user()->tenant_id, $days)
        );
    }

    public function usage(Request $request): JsonResponse
    {
        return response()->json(
            $this->costTracking->getMonthlyUsage($request->user()->tenant_id)
        );
    }
}
