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

        SEOTools::setTitle('RuneLite Plugin Stats — Browse All Plugins');
        SEOTools::setDescription('Browse install counts, all-time highs for every RuneLite plugins.');
        SEOTools::opengraph()->setUrl(route('home'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'RuneLite Plugin Stats — Browse All Plugins');
        SEOTools::opengraph()->addProperty('description', 'Browse install counts, all-time highs for every RuneLite plugin.');
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('home'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        Cache::put('sitemap.xml', app(GenerateSitemap::class)->handle($plugins), now()->addWeek());

        return inertia('Index', [
            'plugins' => $plugins,
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        $params = $request->only(['range']);
        $plugin = $this->runeliteApi->getPlugin($name, $params);

        if ($plugin === null) {
            abort(404);
        }

        $pluginName = $plugin['display'] ?? $plugin['name'];
        $title = "{$pluginName} — RuneLite Plugin Stats";
        $pluginDesc = $plugin['description'] ?? '';
        $description = $pluginDesc
            ? mb_substr($pluginDesc, 0, 155)
            : "Install stats and history for the {$pluginName} RuneLite plugin.";
        $imageUrl = route('og.image', ['name' => $name]);

        SEOTools::setTitle($title);
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('plugin.show', $name));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', $title);
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage($imageUrl);
        SEOMeta::setCanonical(route('plugin.show', $name));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle($title);
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage($imageUrl);

        return inertia('PluginDetail', [
            'plugin' => $plugin,
        ]);
    }

    public function random(): RedirectResponse
    {
        $plugin = $this->runeliteApi->getRandomPlugin();

        return redirect()->route('plugin.show', ['name' => $plugin['name']]);
    }
}
