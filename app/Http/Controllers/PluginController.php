<?php

namespace App\Http\Controllers;

use App\Actions\GenerateSitemap;
use App\Services\RuneliteApiService;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class PluginController extends Controller
{
    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function index(Request $request): Response
    {
        $params = $request->only(['range']);
        $plugins = $this->runeliteApi->getPlugins($params);

        SEOTools::setTitle('RuneLite Plugin Stats');
        SEOTools::setDescription('Browse and compare install statistics for all RuneLite plugins.');
        SEOTools::opengraph()->setUrl(route('home'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOMeta::setCanonical(route('home'));

        Cache::put('sitemap.xml', app(GenerateSitemap::class)->handle($plugins), now()->addWeek());

        return inertia('Index', [
            'plugins' => $plugins,  // sorted/filtered by request params for the table
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        $params = $request->only(['range']);
        $plugin = $this->runeliteApi->getPlugin($name, $params);

        if ($plugin === null) {
            abort(404);
        }

        $title = ($plugin['display'] ?? $plugin['name']).' - RuneLite Plugin Stats';

        SEOTools::setTitle($title);
        SEOTools::setDescription($plugin['description'] ?? "Install stats for {$plugin['display']}.");
        SEOTools::opengraph()->setUrl(route('plugin.show', $name));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', $title);
        SEOTools::opengraph()->addProperty('description', $plugin['description'] ?? '');
        SEOMeta::setCanonical(route('plugin.show', $name));
        TwitterCard::setTitle($title);
        TwitterCard::setDescription($plugin['description'] ?? '');
        TwitterCard::setType('summary');

        return inertia('PluginDetail', [
            'plugin' => $plugin,
        ]);
    }

    public function random(Request $request): RedirectResponse
    {
        $plugin = $this->runeliteApi->getRandomPlugin();

        return redirect()->route('plugin.show', ['name' => $plugin['name']]);
    }
}
