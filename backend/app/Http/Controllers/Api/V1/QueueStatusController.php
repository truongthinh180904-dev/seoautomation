<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $queues = ['ai-research', 'ai-writing', 'imports', 'publishing', 'notifications', 'default'];

        $stats = array_map(function (string $queue) {
            return [
                'name'       => $queue,
                'label'      => $this->label($queue),
                'pending'    => $this->pendingCount($queue),
                'processing' => $this->processingCount($queue),
                'failed'     => $this->failedCount($queue),
            ];
        }, $queues);

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get()
            ->map(fn($job) => [
                'id'        => $job->id,
                'queue'     => $job->queue,
                'payload'   => json_decode($job->payload, true),
                'exception' => str($job->exception)->limit(300)->toString(),
                'failed_at' => $job->failed_at,
            ]);

        return response()->json([
            'stats'       => $stats,
            'failed_jobs' => $failedJobs,
        ]);
    }

    public function retry(int $jobId): JsonResponse
    {
        \Artisan::call('queue:retry', ['id' => [$jobId]]);
        return response()->json(['message' => "Job #{$jobId} queued for retry."]);
    }

    private function label(string $queue): string
    {
        return match($queue) {
            'ai-research'   => 'AI Research',
            'ai-writing'    => 'AI Writing',
            'imports'       => 'Imports & Media',
            'publishing'    => 'Publishing',
            'notifications' => 'Notifications',
            'default'       => 'Default',
            default         => $queue,
        };
    }

    private function pendingCount(string $queue): int
    {
        if (!Schema::hasTable('jobs')) {
            return 0;
        }

        return DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();
    }

    private function processingCount(string $queue): int
    {
        if (!Schema::hasTable('jobs')) {
            return 0;
        }

        return DB::table('jobs')
            ->where('queue', $queue)
            ->whereNotNull('reserved_at')
            ->count();
    }

    private function failedCount(string $queue): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')->where('queue', $queue)->count();
    }
}
