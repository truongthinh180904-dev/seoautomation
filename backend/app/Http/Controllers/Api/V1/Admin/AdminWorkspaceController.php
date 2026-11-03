<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\WorkspaceStatus;
use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateWorkspaceStatusRequest;
use App\Models\Workspace;
use App\Services\Billing\ManualPaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminWorkspaceController extends Controller
{
    public function __construct(
        protected ManualPaymentService $manualPaymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Workspace::with(['owner', 'creditAccount', 'subscriptions' => function ($q) {
            $q->where('status', 'active')->latest('id')->with('plan');
        }])->withCount('websites')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($oq) use ($search) {
                        $oq->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $workspaces = $query->paginate((int) $request->query('per_page', 15));

        $data = $workspaces->getCollection()->map(function (Workspace $ws) {
            $activeSub = $ws->subscriptions->first();

            return [
                'id' => $ws->id,
                'name' => $ws->name,
                'slug' => $ws->slug,
                'status' => $ws->status?->value ?? (string) $ws->status,
                'owner' => [
                    'id' => $ws->owner?->id,
                    'name' => $ws->owner?->name,
                    'email' => $ws->owner?->email,
                ],
                'current_plan' => $activeSub?->plan?->name ?? 'Free',
                'available_credits' => (int) ($ws->creditAccount?->available_credits ?? 0),
                'websites_count' => $ws->websites_count,
                'created_at' => $ws->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $workspaces->currentPage(),
                'last_page' => $workspaces->lastPage(),
                'per_page' => $workspaces->perPage(),
                'total' => $workspaces->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $workspace = Workspace::with([
            'owner',
            'creditAccount',
            'websites',
            'members.user',
            'subscriptions' => function ($q) {
                $q->latest('id')->with('plan');
            },
        ])->findOrFail($id);

        $activeSub = $workspace->subscriptions->first(function ($sub) {
            $statusVal = is_object($sub->status) ? ($sub->status->value ?? (string) $sub->status) : (string) $sub->status;

            return $statusVal === 'active';
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'status' => $workspace->status?->value ?? (string) $workspace->status,
                'owner' => [
                    'id' => $workspace->owner?->id,
                    'name' => $workspace->owner?->name,
                    'email' => $workspace->owner?->email,
                ],
                'credit_account' => [
                    'available_credits' => (int) ($workspace->creditAccount?->available_credits ?? 0),
                    'reserved_credits' => (int) ($workspace->creditAccount?->reserved_credits ?? 0),
                    'cycle_granted_credits' => (int) ($workspace->creditAccount?->cycle_granted_credits ?? 0),
                    'cycle_used_credits' => (int) ($workspace->creditAccount?->cycle_used_credits ?? 0),
                    'period_start' => $workspace->creditAccount?->period_start?->toIso8601String(),
                    'period_end' => $workspace->creditAccount?->period_end?->toIso8601String(),
                ],
                'current_plan' => $activeSub?->plan ? [
                    'id' => $activeSub->plan->id,
                    'name' => $activeSub->plan->name,
                    'code' => $activeSub->plan->code?->value,
                ] : null,
                'websites' => $workspace->websites->map(fn ($site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'url' => $site->url,
                    'status' => $site->status?->value ?? (string) $site->status,
                ]),
                'members_count' => $workspace->members->count(),
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateStatus(AdminUpdateWorkspaceStatusRequest $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        $newStatus = $request->input('status');
        $reason = $request->input('reason');
        $admin = $request->user();

        try {
            $updatedWorkspace = $this->manualPaymentService->updateWorkspaceStatus(
                workspace: $workspace,
                newStatus: $newStatus,
                admin: $admin,
                reason: $reason
            );

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Cập nhật trạng thái không gian làm việc thành %s.',
                    $newStatus === WorkspaceStatus::SUSPENDED->value ? 'Tạm ngưng' : 'Hoạt động'
                ),
                'data' => [
                    'id' => $updatedWorkspace->id,
                    'status' => $updatedWorkspace->status?->value ?? (string) $updatedWorkspace->status,
                ],
            ]);
        } catch (DomainException|SubscriptionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi cập nhật trạng thái workspace: '.$e->getMessage(), [
                'workspace_id' => $id,
                'new_status' => $newStatus,
                'admin_id' => $admin?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống khi cập nhật trạng thái không gian làm việc.',
            ], 500);
        }
    }
}
