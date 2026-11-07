<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractExternalApiClient
{
    protected int $timeoutSeconds = 10;

    protected int $connectTimeoutSeconds = 5;

    protected int $maxRetries = 3;

    protected int $retryDelayMs = 200;

    protected string $userAgent = 'AuraSEO-Integrations/1.0 (+https://auraseo.io)';

    /**
     * Build base pending request with standardized headers and timeouts.
     */
    protected function createClient(array $headers = []): PendingRequest
    {
        $defaultHeaders = array_merge([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
        ], $headers);

        return Http::timeout($this->timeoutSeconds)
            ->connectTimeout($this->connectTimeoutSeconds)
            ->withHeaders($defaultHeaders)
            ->retry($this->maxRetries, $this->retryDelayMs, function (\Throwable $exception, PendingRequest $request) {
                // Retry on connection exceptions or 5xx/429 HTTP statuses
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($exception instanceof RequestException) {
                    $status = $exception->response?->status();

                    return $status === 429 || ($status !== null && $status >= 500);
                }

                return false;
            }, throw: false);
    }

    /**
     * Send GET request.
     */
    protected function get(string $url, array $query = [], array $headers = []): Response
    {
        $this->logRequest('GET', $url, ['query' => $query]);
        $response = $this->createClient($headers)->get($url, $query);
        $this->logResponse('GET', $url, $response);

        return $response;
    }

    /**
     * Send POST request.
     */
    protected function post(string $url, array $data = [], array $headers = []): Response
    {
        $this->logRequest('POST', $url, $data);
        $response = $this->createClient($headers)->post($url, $data);
        $this->logResponse('POST', $url, $response);

        return $response;
    }

    /**
     * Send POST as form URL encoded.
     */
    protected function postForm(string $url, array $params = [], array $headers = []): Response
    {
        $this->logRequest('POST_FORM', $url, $params);
        $response = $this->createClient($headers)->asForm()->post($url, $params);
        $this->logResponse('POST_FORM', $url, $response);

        return $response;
    }

    /**
     * Send DELETE request.
     */
    protected function delete(string $url, array $data = [], array $headers = []): Response
    {
        $this->logRequest('DELETE', $url, $data);
        $response = $this->createClient($headers)->delete($url, $data);
        $this->logResponse('DELETE', $url, $response);

        return $response;
    }

    /**
     * Safe request logging without leaking secrets.
     */
    protected function logRequest(string $method, string $url, array $payload = []): void
    {
        Log::debug("[ExternalApiClient] {$method} {$url}", [
            'payload' => $this->sanitizePayload($payload),
        ]);
    }

    /**
     * Safe response logging.
     */
    protected function logResponse(string $method, string $url, Response $response): void
    {
        $context = [
            'status' => $response->status(),
            'successful' => $response->successful(),
        ];

        if ($response->failed()) {
            $context['error'] = $response->json('error') ?? $response->body();
            Log::warning("[ExternalApiClient] {$method} {$url} failed with status {$response->status()}", $context);
        } else {
            Log::debug("[ExternalApiClient] {$method} {$url} response status {$response->status()}", $context);
        }
    }

    /**
     * Strip or mask sensitive credentials from log payloads.
     */
    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'client_secret', 'secret', 'password', 'access_token',
            'refresh_token', 'token', 'authorization', 'api_key', 'code',
        ];

        $sanitized = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lower, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
