<?php

namespace App\Services\WordPress\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait ConfiguresWordPressHttp
{
    protected function wordpressHttp(): PendingRequest
    {
        return Http::withOptions([
            'verify' => (bool) config('wordpress.verify_ssl', true),
        ]);
    }
}
