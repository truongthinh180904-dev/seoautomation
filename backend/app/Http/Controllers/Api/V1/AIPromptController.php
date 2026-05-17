<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AgentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AIPromptVersionResource;
use App\Models\AIPromptVersion;
use App\Services\AI\PromptBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AIPromptController extends Controller
{
    public function __construct(protected PromptBuilderService $promptBuilder) {}

    /**
     * List prompt versions — optionally filtered by agent_type.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AIPromptVersion::query()
            ->where(function ($q) use ($request) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $request->user()->tenant_id);
            })
            ->orderByDesc('created_at');

        if ($request->filled('agent_type')) {
            $query->where('agent_type', $request->agent_type);
        }

        return response()->json(
            AIPromptVersionResource::collection($query->paginate(20))
        );
    }

    /**
     * Create a new prompt version. Optionally deactivate others for same agent.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_type'           => 'required|string|in:' . implode(',', array_column(AgentType::cases(), 'value')),
            'version'              => 'required|string|max:50',
            'name'                 => 'required|string|max:255',
            'system_prompt'        => 'nullable|string',
            'user_prompt_template' => 'required|string',
            'variables'            => 'nullable|array',
            'is_active'            => 'boolean',
            'is_default'           => 'boolean',
            'tenant_id'            => 'nullable|integer|exists:tenants,id',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['tenant_id']  = $validated['tenant_id'] ?? null;

        $prompt = AIPromptVersion::create($validated);

        // Bust cache so next run picks up the new version
        $agentType = AgentType::from($validated['agent_type']);
        $this->promptBuilder->invalidateCache($agentType, $validated['tenant_id']);

        return response()->json(new AIPromptVersionResource($prompt), 201);
    }

    /**
     * Activate a specific prompt version (deactivate others for same agent+tenant).
     */
    public function activate(int $id, Request $request): JsonResponse
    {
        $prompt = AIPromptVersion::findOrFail($id);

        // Only deactivate others in the same scope
        AIPromptVersion::where('agent_type', $prompt->agent_type)
            ->where(function ($q) use ($prompt) {
                if ($prompt->tenant_id) {
                    $q->where('tenant_id', $prompt->tenant_id);
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->where('id', '!=', $prompt->id)
            ->update(['is_active' => false]);

        $prompt->update(['is_active' => true]);

        // Bust cache
        $agentType = AgentType::from($prompt->agent_type);
        $this->promptBuilder->invalidateCache($agentType, $prompt->tenant_id);

        return response()->json(['message' => "Prompt version '{$prompt->version}' activated.", 'prompt' => new AIPromptVersionResource($prompt)]);
    }

    /**
     * Update performance_score — called when article SEO score is high.
     */
    public function updatePerformance(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate(['seo_score' => 'required|numeric|min:0|max:100']);

        $this->promptBuilder->recordPerformance($id, $validated['seo_score']);

        return response()->json(['message' => 'Performance score updated.']);
    }

    /**
     * Delete a prompt version (cannot delete active/default ones).
     */
    public function destroy(int $id): JsonResponse
    {
        $prompt = AIPromptVersion::findOrFail($id);

        if ($prompt->is_active || $prompt->is_default) {
            return response()->json(['message' => 'Cannot delete an active or default prompt version.'], 422);
        }

        $prompt->delete();

        return response()->json(null, 204);
    }
}
