<?php

namespace App\Http\Middleware;

use App\Services\RuneliteApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;
use Inertia\OptionalProp;

class HandleInertiaRequests extends Middleware
{
    private const PLUGIN_LIST_TTL_MINUTES = 60;

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
            'plugins' => $this->pluginList($request),
        ];
    }

    private function pluginList(Request $request): OnceProp|OptionalProp
    {
        $plugins = fn (): array => $this->runeliteApi->getClientPlugins();
        $ttl = now()->addMinutes(self::PLUGIN_LIST_TTL_MINUTES);

        if ($request->routeIs('home')) {
            return Inertia::once($plugins)->until($ttl);
        }

        return Inertia::optional($plugins)->once(until: $ttl);
    }
}
