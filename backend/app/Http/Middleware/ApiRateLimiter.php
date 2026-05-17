<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ThrottleRequestsException $e) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;

            return response()->json([
                'success'     => false,
                'message'     => 'Too Many Requests. Please wait before retrying.',
                'retry_after' => (int) $retryAfter,
            ], 429, [
                'Retry-After'        => $retryAfter,
                'X-RateLimit-Reset'  => time() + (int) $retryAfter,
                'Content-Type'       => 'application/json',
            ]);
        }
    }
}
