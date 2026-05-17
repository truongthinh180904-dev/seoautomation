<?php

namespace App\Services\WordPress;

use App\Repositories\Contracts\WordPressSiteRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class WordPressSiteService
{
    public function __construct(
        protected WordPressSiteRepositoryInterface $repository
    ) {}

    public function testConnection(int $id): array
    {
        $site = $this->repository->findById($id);
        if (!$site) {
            return ['success' => false, 'message' => 'Site not found'];
        }

        try {
            $response = Http::withBasicAuth($site->username, $site->app_password)
                ->timeout(10)
                ->get(rtrim($site->url, '/') . '/wp-json/wp/v2/users/me');

            if ($response->successful()) {
                $this->repository->updateConnectionStatus($site->id, 'connected');
                return ['success' => true, 'message' => 'Connection successful'];
            }

            $this->repository->updateConnectionStatus($site->id, 'failed');
            return [
                'success' => false, 
                'message' => 'Connection failed: ' . $response->status()
            ];

        } catch (Exception $e) {
            $this->repository->updateConnectionStatus($site->id, 'error');
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
