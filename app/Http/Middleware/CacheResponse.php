<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    private const CACHE_CONTROL_VALUE = 'max-age=5, stale-while-revalidate=604800';

    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET requests, skip API routes
        if (! $request->isMethod('GET') || $request->is('api/*')) {
            $response = $next($request);
            $response->headers->set('Cache-Control', self::CACHE_CONTROL_VALUE);

            return $response;
        }

        $inertiaHeader = $request->header('X-Inertia', '');
        $cacheKey = 'response:'.md5($request->fullUrl().$inertiaHeader);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $response = response($cached['content'], $cached['status'], $cached['headers']);
            $response->headers->set('X-Cache', 'HIT');
            $response->headers->set('Cache-Control', self::CACHE_CONTROL_VALUE);

            return $response;
        }

        $response = $next($request);

        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $status,
                'headers' => array_filter($response->headers->all(), fn ($key) => ! in_array($key, [
                    'set-cookie', 'x-cache',
                ]), ARRAY_FILTER_USE_KEY),
            ], 60);
        }

        $response->headers->set('X-Cache', 'MISS');
        $response->headers->set('Cache-Control', self::CACHE_CONTROL_VALUE);

        return $response;
    }
}
