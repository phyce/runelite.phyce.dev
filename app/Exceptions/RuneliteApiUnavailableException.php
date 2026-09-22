<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * The stats API could not answer: unreachable, or it replied with anything
 * other than success or 404. Rendering it as a 503 tells crawlers to come back
 * later instead of dropping the page (a 404) or indexing an empty one (a 200),
 * and because it escapes Cache::remember, a failure is never cached as data.
 */
class RuneliteApiUnavailableException extends ServiceUnavailableHttpException
{
    public function __construct(public readonly string $endpoint, string $reason, ?Throwable $previous = null)
    {
        parent::__construct(60, "RuneLite API /{$endpoint} unavailable: {$reason}", $previous);
    }
}
