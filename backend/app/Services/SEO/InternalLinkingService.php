<?php

namespace App\Services\SEO;

use App\Models\WordPressSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class InternalLinkingService
{
    public function fetchRelatedPosts(WordPressSite $site, string $keyword, int $limit = 5): array
    {
        $apiUrl = rtrim($site->api_url, '/');
        
        $decryptedPassword = Crypt::decryptString($site->app_password);
        $auth = base64_encode($site->username . ':' . $decryptedPassword);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $auth,
        ])->get($apiUrl . '/wp/v2/posts', [
            'search' => $keyword,
            'per_page' => $limit,
            '_fields' => 'id,title,link'
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }
}
