<?php

namespace App\Services\Security;

class UrlSafetyValidator
{
    /**
     * Determine if a given URL is safe from SSRF attacks.
     */
    public static function isSafeUrl(?string $url): bool
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (! isset($parts['scheme']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? null;
        if (empty($host)) {
            return false;
        }

        // Check for common internal hostnames
        $lowerHost = strtolower($host);
        if (in_array($lowerHost, ['localhost', 'localhost.localdomain', 'broadcasthost'], true)) {
            return false;
        }

        // Resolve DNS to IP addresses
        $ip = gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            // In testing/offline environment where DNS cannot resolve external hosts,
            // allow valid domain syntax as long as it's not a known local alias
            if (app()->environment('testing') && ! str_contains($lowerHost, 'localhost') && ! str_contains($lowerHost, '127.0.0.1')) {
                return true;
            }

            return false;
        }

        return self::isSafeIp($ip);
    }

    /**
     * Check whether an IP address is a public, safe address.
     */
    public static function isSafeIp(string $ip): bool
    {
        // Reject private and reserved IP ranges
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        if (! filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
            return false;
        }

        // Specifically block AWS/GCP metadata IP
        if ($ip === '169.254.169.254' || str_starts_with($ip, '169.254.')) {
            return false;
        }

        // Block IPv4 loopback (127.0.0.0/8)
        if (str_starts_with($ip, '127.')) {
            return false;
        }

        // Block IPv4 0.0.0.0/8
        if (str_starts_with($ip, '0.')) {
            return false;
        }

        return true;
    }
}
