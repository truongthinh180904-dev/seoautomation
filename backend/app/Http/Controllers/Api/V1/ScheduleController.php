<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Enums\KeywordStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    /**
     * List all keywords with a scheduled_at date (upcoming schedules).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $schedules = Keyword::where('tenant_id', $tenantId)
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->paginate(20);

        return response()->json($schedules);
    }

    /**
     * Schedule a keyword for future processing (one-time or update).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword_id'   => 'required|integer|exists:keywords,id',
            'scheduled_at' => 'required|date|after:now',
            'recurring'    => 'boolean',
            'interval_days' => 'nullable|integer|min:1|max:365',
        ]);

        $keyword = Keyword::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($validated['keyword_id']);

        $keyword->update([
            'scheduled_at' => $validated['scheduled_at'],
            'status'       => KeywordStatus::NEW,
        ]);

        return response()->json([
            'message'  => 'Keyword scheduled successfully.',
            'keyword'  => $keyword->fresh(),
        ], 201);
    }

    /**
     * Update schedule date for a keyword.
     */
    public function update(Request $request, int $keywordId): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at'  => 'required|date',
            'recurring'     => 'boolean',
            'interval_days' => 'nullable|integer|min:1',
        ]);

        $keyword = Keyword::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($keywordId);

        $keyword->update(['scheduled_at' => $validated['scheduled_at']]);

        return response()->json(['message' => 'Schedule updated.', 'keyword' => $keyword]);
    }

    /**
     * Remove schedule from a keyword (set scheduled_at to null).
     */
    public function destroy(int $keywordId, Request $request): JsonResponse
    {
        $keyword = Keyword::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($keywordId);

        $keyword->update(['scheduled_at' => null]);

        return response()->json(['message' => 'Schedule removed.']);
    }
}
