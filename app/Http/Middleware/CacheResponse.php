<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET requests, skip API routes
        if (! $request->isMethod('GET') || $request->is('api/*')) {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'max-age=5, stale-while-revalidate=604800');

            return $response;
        }

        $cacheKey = 'response:'.md5(implode('|', [
            $request->fullUrl(),
            $request->header(Header::INERTIA, ''),
            $request->header(Header::PARTIAL_COMPONENT, ''),
            $request->header(Header::PARTIAL_ONLY, ''),
            $request->header(Header::PARTIAL_EXCEPT, ''),
            $request->header(Header::EXCEPT_ONCE_PROPS, ''),
        ]));

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $response = response($cached['content'], $cached['status'], $cached['headers']);
            $response->headers->set('X-Cache', 'HIT');
            $response->headers->set('Cache-Control', 'max-age=5, stale-while-revalidate=604800');

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
        $response->headers->set('Cache-Control', 'max-age=5, stale-while-revalidate=604800');

        return $response;
    }
}
