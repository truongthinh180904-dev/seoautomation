<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __construct(
        protected ScheduleRepositoryInterface $schedules
    ) {}

    /**
     * List all keywords with a scheduled_at date (upcoming schedules).
     */
    public function index(Request $request): JsonResponse
    {
        $schedules = $this->schedules->paginateForTenant(
            $request->user()->tenant_id,
            $request->only(['type', 'status']),
            $request->integer('per_page', 20)
        );

        return response()->json(ScheduleResource::collection($schedules));
    }

    /**
     * Schedule a keyword for future processing (one-time or update).
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $schedule = $this->schedules->create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Schedule created successfully.',
            'schedule' => new ScheduleResource($schedule),
        ], 201);
    }

    /**
     * Update a schedule.
     */
    public function update(UpdateScheduleRequest $request, int $id): JsonResponse
    {
        $schedule = $this->schedules->findByIdForTenant($id, $request->user()->tenant_id);

        if (!$schedule) {
            abort(404);
        }

        $schedule = $this->schedules->update($schedule, $request->validated());

        return response()->json(['message' => 'Schedule updated.', 'schedule' => new ScheduleResource($schedule)]);
    }

    /**
     * Delete a schedule.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $schedule = $this->schedules->findByIdForTenant($id, $request->user()->tenant_id);

        if (!$schedule) {
            abort(404);
        }

        $this->schedules->delete($schedule);

        return response()->json(null, 204);
    }
}
