<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Horizon stores job metrics in Redis; we query the failed_jobs table for DB-backed failures
        $queues = ['ai-research', 'ai-writing', 'publishing', 'notifications'];

        $stats = array_map(function (string $queue) {
            return [
                'name'       => $queue,
                'label'      => $this->label($queue),
                'pending'    => 0, // Horizon-only: extend via Laravel\Horizon\Contracts\MetricsRepository
                'processing' => 0,
                'failed'     => DB::table('failed_jobs')->where('queue', $queue)->count(),
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
            'publishing'    => 'Publishing',
            'notifications' => 'Notifications',
            default         => $queue,
        };
    }
}
