<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Zalo\ZaloWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ZaloController extends Controller
{
    public function __construct(protected ZaloWebhookService $webhookService) {}

    public function webhook(Request $request): JsonResponse
    {
        $appSecret = config('services.zalo.app_secret', '');
        
        $signature = $request->header('X-ZECA-Signature', '');
        $timestamp = $request->header('X-ZECA-Timestamp', '');
        
        $isValid = $this->webhookService->verifySignature($request->getContent(), $signature, $timestamp, $appSecret);
        
        if (!$isValid && app()->isProduction()) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $this->webhookService->handle($request->all());

        return response()->json(['success' => true]);
    }
}
