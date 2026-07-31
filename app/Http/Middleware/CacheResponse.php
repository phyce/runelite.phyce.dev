<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        /**
         * Every header that changes which props end up in the body has to be
         * part of the key. Partial and once-prop requests hit the same URL with
         * the same `X-Inertia: true` as a full visit, so keying on those two
         * alone would let a partial response containing a single prop be served
         * as though it were the whole page.
         */
        $cacheKey = 'response:'.md5(implode('|', [
            $request->fullUrl(),
            $request->header('X-Inertia', ''),
            $request->header('X-Inertia-Partial-Component', ''),
            $request->header('X-Inertia-Partial-Data', ''),
            $request->header('X-Inertia-Partial-Except', ''),
            $request->header('X-Inertia-Except-Once-Props', ''),
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
