<?php

namespace App\Http\Middleware;

use App\Services\RuneliteApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private RuneliteApiService $runeliteApi) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'apiUrl' => rtrim(config('services.runelite_api.client_url'), '/'),
            'appUrl' => config('app.url'),
            /**
             * Only the header search needs this list, and only once the user
             * starts typing, so it is kept out of the initial document and
             * fetched after paint.
             *
             * The homepage passes its own eager `plugins` prop, which replaces
             * this one before the response is resolved, so that page still
             * server-renders the full table and issues no follow-up request.
             *
             * `once()` keeps the client from re-fetching the same list on every
             * subsequent visit; the TTL bounds how stale a long session can get.
             */
            'plugins' => Inertia::defer(fn (): array => $this->runeliteApi->getPlugins([]))
                ->once(until: now()->addMinutes(10)),
        ];
    }
}
