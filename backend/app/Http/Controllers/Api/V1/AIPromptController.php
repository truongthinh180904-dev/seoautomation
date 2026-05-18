<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AgentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAIPromptVersionRequest;
use App\Http\Requests\UpdateAIPromptVersionRequest;
use App\Http\Requests\UpdatePromptPerformanceRequest;
use App\Http\Resources\AIPromptVersionResource;
use App\Repositories\Contracts\AIPromptVersionRepositoryInterface;
use App\Services\AI\PromptBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIPromptController extends Controller
{
    public function __construct(
        protected PromptBuilderService $promptBuilder,
        protected AIPromptVersionRepositoryInterface $repository
    ) {}

    /**
     * List prompt versions — optionally filtered by agent_type.
     */
    public function index(Request $request): JsonResponse
    {
        $prompts = $this->repository->paginateVisibleForTenant(
            $request->user()->tenant_id,
            $request->only('agent_type'),
            $request->integer('per_page', 20)
        );

        return response()->json(AIPromptVersionResource::collection($prompts));
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $prompt = $this->repository->findVisibleForTenant($id, $request->user()->tenant_id);

        if (!$prompt) {
            abort(404);
        }

        return response()->json(new AIPromptVersionResource($prompt));
    }

    /**
     * Create a new prompt version. Optionally deactivate others for same agent.
     */
    public function store(StoreAIPromptVersionRequest $request): JsonResponse
    {
        $validated = $this->normalizeTenantScope($request->validated(), $request);
        $validated['created_by'] = $request->user()->id;

        $prompt = $this->repository->create($validated);

        $agentType = AgentType::from($validated['agent_type']);
        $this->promptBuilder->invalidateCache($agentType, $validated['tenant_id']);

        return response()->json(new AIPromptVersionResource($prompt), 201);
    }

    public function update(UpdateAIPromptVersionRequest $request, int $id): JsonResponse
    {
        $prompt = $this->repository->findVisibleForTenant($id, $request->user()->tenant_id);

        if (!$prompt) {
            abort(404);
        }

        if ($prompt->tenant_id === null && $request->user()->role !== UserRole::SUPER_ADMIN) {
            abort(403);
        }

        $validated = $this->normalizeTenantScope($request->validated(), $request, $prompt->tenant_id);
        $prompt = $this->repository->update($prompt, $validated);

        $agentType = AgentType::from($prompt->agent_type);
        $this->promptBuilder->invalidateCache($agentType, $prompt->tenant_id);

        return response()->json(new AIPromptVersionResource($prompt));
    }

    /**
     * Activate a specific prompt version (deactivate others for same agent+tenant).
     */
    public function activate(int $id, Request $request): JsonResponse
    {
        $prompt = $this->repository->findVisibleForTenant($id, $request->user()->tenant_id);

        if (!$prompt) {
            abort(404);
        }

        if ($prompt->tenant_id === null && $request->user()->role !== UserRole::SUPER_ADMIN) {
            abort(403);
        }

        $this->repository->deactivateSiblings($prompt);
        $prompt = $this->repository->update($prompt, ['is_active' => true]);

        $agentType = AgentType::from($prompt->agent_type);
        $this->promptBuilder->invalidateCache($agentType, $prompt->tenant_id);

        return response()->json(['message' => "Prompt version '{$prompt->version}' activated.", 'prompt' => new AIPromptVersionResource($prompt)]);
    }

    /**
     * Update performance_score — called when article SEO score is high.
     */
    public function updatePerformance(int $id, UpdatePromptPerformanceRequest $request): JsonResponse
    {
        $prompt = $this->repository->findVisibleForTenant($id, $request->user()->tenant_id);

        if (!$prompt) {
            abort(404);
        }

        $this->repository->updatePerformance($id, (float) $request->validated('seo_score'));

        return response()->json(['message' => 'Performance score updated.']);
    }

    /**
     * Delete a prompt version (cannot delete active/default ones).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $prompt = $this->repository->findVisibleForTenant($id, $request->user()->tenant_id);

        if (!$prompt) {
            abort(404);
        }

        if ($prompt->tenant_id === null && $request->user()->role !== UserRole::SUPER_ADMIN) {
            abort(403);
        }

        if ($prompt->is_active || $prompt->is_default) {
            return response()->json(['message' => 'Cannot delete an active or default prompt version.'], 422);
        }

        $this->repository->delete($prompt);

        return response()->json(null, 204);
    }

    protected function normalizeTenantScope(array $data, Request $request, ?int $currentTenantId = null): array
    {
        if ($request->user()->role !== UserRole::SUPER_ADMIN) {
            $data['tenant_id'] = $request->user()->tenant_id;

            return $data;
        }

        if (!array_key_exists('tenant_id', $data)) {
            $data['tenant_id'] = $currentTenantId;
        }

        return $data;
    }
}
