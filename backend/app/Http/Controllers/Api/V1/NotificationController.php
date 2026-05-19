<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('tenant_id', $request->user()->tenant_id)
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            });

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection(
            $query->latest()->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('tenant_id', $request->user()->tenant_id)
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }
}
