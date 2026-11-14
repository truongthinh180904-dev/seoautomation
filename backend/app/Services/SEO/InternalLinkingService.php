<?php

namespace App\Services\SEO;

use App\Models\WordPressSite;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class InternalLinkingService
{
    public function fetchRelatedPosts(WordPressSite $site, string $keyword, int $limit = 5): array
    {
        $apiUrl = rtrim($site->api_url, '/');

        $password = (string) $site->app_password;
        try {
            $password = Crypt::decryptString($password);
        } catch (\Throwable) {
            // Already decrypted by Eloquent cast
        }

        $auth = base64_encode($site->username.':'.$password);

        $response = Http::withOptions([
            'verify' => (bool) config('wordpress.verify_ssl', true),
        ])->timeout(15)->withHeaders([
            'Authorization' => 'Basic '.$auth,
        ])->get($apiUrl.'/wp/v2/posts', [
            'search' => $keyword,
            'per_page' => $limit,
            '_fields' => 'id,title,link',
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }
}
