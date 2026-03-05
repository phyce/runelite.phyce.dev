<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleGetParams
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieValue = $request->cookie('getParams');
        $cookieParams = [];

        if ($cookieValue) {
            $decoded = json_decode($cookieValue, true);
            if (is_array($decoded)) {
                $cookieParams = $decoded;
            }
        }

        $queryParams = $request->query->all();

        // URL params take precedence over cookie params
        $merged = array_merge($cookieParams, $queryParams);

        // Merge back into request so controllers receive them
        $request->query->replace($merged);
        $request->merge($merged);

        $response = $next($request);

        // Update cookie with merged params
        $response->headers->setCookie(
            cookie('getParams', json_encode($merged), 60 * 24 * 30, '/')
        );

        return $response;
    }
}
