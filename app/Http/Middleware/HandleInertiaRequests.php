<?php

namespace App\Http\Middleware;

use App\Services\RuneliteApiService;
use Illuminate\Http\Request;
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
            'plugins' => $this->runeliteApi->getPlugins([]),
        ];
    }
}
