<?php

namespace App\Http\Controllers;

use App\Actions\GenerateSitemap;
use App\Exceptions\RuneliteApiUnavailableException;
use App\Services\PluginTagService;
use App\Services\RuneliteApiService;
use Artesaos\SEOTools\Facades\JsonLd;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Response;

class PluginController extends Controller
{
    public function __construct(
        private RuneliteApiService $runeliteApi,
        private PluginTagService $tags,
    ) {}

    public function index(Request $request): Response
    {
        $title = 'RuneLite Plugin Stats';
        $description = 'Browse install counts, all-time highs, and growth trends for every RuneLite plugin.';

        SEOTools::setTitle($title);
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('home'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', $title);
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('home'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        $this->refreshSitemap();

        return inertia('Index');
    }

    private function refreshSitemap(): void
    {
        try {
            Cache::put('sitemap.xml', app(GenerateSitemap::class)->handle($this->runeliteApi->getPlugins()), now()->addWeek());
        } catch (RuneliteApiUnavailableException) {
            return;
        }
    }

    public function show(Request $request, string $name): Response
    {
        $params = $request->only(['range']);
        $plugin = $this->runeliteApi->getPlugin($name, $params);

        if ($plugin === null) {
            abort(404);
        }

        $pluginName = $plugin['display'] ?? $plugin['name'];
        $title = "{$pluginName} | RuneLite Plugin Stats";

        $metaParts = [];
        if (! empty($plugin['author'])) {
            $metaParts[] = "by {$plugin['author']}";
        }
        if (! empty($plugin['created_on'])) {
            $metaParts[] = 'released '.Carbon::parse($plugin['created_on'])->format('F j, Y');
        }
        $metaPrefix = implode(', ', $metaParts);

        $pluginDesc = $plugin['description'] ?? '';
        $summary = $pluginDesc !== ''
            ? $pluginDesc
            : "Install stats and history for the {$pluginName} RuneLite plugin.";

        $description = $metaPrefix !== ''
            ? Str::limit("{$metaPrefix}. {$summary}", 160)
            : Str::limit($summary, 160);
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

        JsonLd::setType('SoftwareApplication');
        JsonLd::addValue('name', $pluginName);
        JsonLd::addValue('description', $summary);
        JsonLd::addValue('url', route('plugin.show', $name));
        JsonLd::addValue('image', $imageUrl);
        JsonLd::addValue('applicationCategory', 'GameApplication');
        JsonLd::addValue('operatingSystem', 'Windows, macOS, Linux');
        JsonLd::addValue('sameAs', "https://runelite.net/plugin-hub/show/{$name}");

        if (! empty($plugin['author'])) {
            JsonLd::addValue('author', [
                '@type' => 'Person',
                'name' => $plugin['author'],
            ]);
        }

        if (! empty($plugin['created_on'])) {
            JsonLd::addValue('datePublished', Carbon::parse($plugin['created_on'])->toDateString());
        }

        if (! empty($plugin['updated_on'])) {
            JsonLd::addValue('dateModified', Carbon::parse($plugin['updated_on'])->toDateString());
        }

        if (isset($plugin['current_installs'])) {
            JsonLd::addValue('interactionStatistic', [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/InstallAction',
                'userInteractionCount' => $plugin['current_installs'],
            ]);
        }

        return inertia('PluginDetail', [
            'plugin' => $plugin,
            'related' => $this->runeliteApi->getRelatedPlugins($name),
            'developers' => $this->runeliteApi->getPluginDevelopers($plugin['author'] ?? ''),
            'tagLinks' => $this->tags->tagsForPlugin($plugin),
        ]);
    }

    public function random(Request $request): RedirectResponse
    {
        $plugin = $this->runeliteApi->getRandomPlugin();

        return redirect()->route('plugin.show', ['name' => $plugin['name']]);
    }
}
