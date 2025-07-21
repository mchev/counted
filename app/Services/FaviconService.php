<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FaviconService
{
    private const CACHE_TTL = 86400; // 24 hours

    private const FAVICON_SIZE = 32;

    private const STORAGE_PATH = 'favicons';

    public function getFaviconUrl(string $referrer): ?string
    {
        $hostname = $this->extractHostname($referrer);

        if (! $hostname) {
            return null;
        }

        // Check if we have a cached favicon (try different extensions)
        $extensions = ['ico', 'png', 'jpg', 'jpeg', 'gif'];
        foreach ($extensions as $ext) {
            $cachedPath = $this->getCachedFaviconPath($hostname, $ext);
            if ($cachedPath && Storage::exists($cachedPath)) {
                return url('/favicons/'.Str::slug($hostname).'.'.$ext);
            }
        }

        // Try to fetch and cache the favicon
        return $this->fetchAndCacheFavicon($hostname);
    }

    public function extractHostname(string $url): ?string
    {
        // If it's already just a hostname (no protocol), return it directly
        if (! str_contains($url, '://') && ! str_contains($url, '/')) {
            // Validate it's a proper domain
            if (filter_var($url, FILTER_VALIDATE_DOMAIN)) {
                return $url;
            }
        }

        // Remove protocol if present
        $url = preg_replace('/^https?:\/\//', '', $url);

        // Remove path and query parameters
        $url = explode('/', $url)[0];
        $url = explode('?', $url)[0];
        $url = explode('#', $url)[0];

        // Validate hostname
        if (filter_var($url, FILTER_VALIDATE_DOMAIN)) {
            return $url;
        }

        return null;
    }

    public function getCachedFaviconPath(string $hostname, string $extension = 'ico'): string
    {
        return self::STORAGE_PATH.'/'.Str::slug($hostname).'.'.$extension;
    }

    private function fetchAndCacheFavicon(string $hostname): ?string
    {
        // Use Google's favicon service (direct URL to avoid redirects)
        $faviconUrl = "https://t1.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=http://{$hostname}&size=".self::FAVICON_SIZE;

        try {
            $response = Http::timeout(5)->withOptions(['allow_redirects' => true])->get($faviconUrl);

            if ($response->successful() && (
                $response->header('content-type') === 'image/x-icon' ||
                $response->header('content-type') === 'image/png' ||
                $response->header('content-type') === 'image/jpeg' ||
                $response->header('content-type') === 'image/gif'
            )) {
                $faviconData = $response->body();

                // Determine file extension based on content type
                $contentType = $response->header('content-type');
                $extension = 'ico'; // default
                if ($contentType === 'image/png') {
                    $extension = 'png';
                } elseif ($contentType === 'image/jpeg') {
                    $extension = 'jpg';
                } elseif ($contentType === 'image/gif') {
                    $extension = 'gif';
                }

                // Cache the favicon
                $cachedPath = $this->getCachedFaviconPath($hostname, $extension);
                Storage::put($cachedPath, $faviconData);

                return url('/favicons/'.Str::slug($hostname).'.'.$extension);
            }
        } catch (\Exception $e) {
            // Silently fail - favicon is not critical
        }

        return null;
    }

    public function getFaviconForReferrers(array $referrers): array
    {
        $favicons = [];

        foreach ($referrers as $referrer) {
            $ref = $referrer['referrer'];
            if ($ref === null) {
                $favicons['direct_access'] = null; // Direct access doesn't need favicon

                continue;
            }

            $hostname = $this->extractHostname($ref);
            if ($hostname) {
                $favicons[$hostname] = $this->getFaviconUrl($ref);
            }
        }

        return $favicons;
    }

    public function batchFetchFavicons(array $hostnames): array
    {
        $results = [];

        foreach ($hostnames as $hostname) {
            $results[$hostname] = $this->getFaviconUrl($hostname);
        }

        return $results;
    }
}
