<?php

namespace App\Http\Controllers;

use App\Services\RuneliteApiService;
use Artesaos\SEOTools\Facades\JsonLd;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\Request;
use Inertia\Response;

class DeveloperController extends Controller
{
    private const PERIODS = ['day', 'week', 'month', 'year', 'all'];

    private const GROWING_PERIODS = ['day', 'week', 'month', 'year'];

    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function index(): Response
    {
        $data = $this->runeliteApi->getDevelopers();

        $count = $data['count'] ?? 0;
        $description = $count
            ? "Browse all {$count} RuneLite plugin developers, with install counts, plugin counts, and how long each has been publishing to the plugin hub."
            : 'Browse every RuneLite plugin developer, with install counts, plugin counts, and how long each has been publishing to the plugin hub.';

        SEOTools::setTitle('All RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('developers.index'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'All RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('developers.index'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle('All RuneLite Plugin Developers');
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        return inertia('Developers/Index', [
            'developers' => $data,
        ]);
    }

    public function top(): Response
    {
        $data = $this->runeliteApi->getDeveloperTop();

        $topDeveloper = $data['entries'][0]['name'] ?? null;
        $description = $topDeveloper
            ? "The top 100 RuneLite plugin developers ranked by popularity, growth, and player retention. Currently #1: {$topDeveloper}. Updated daily."
            : 'The top 100 RuneLite plugin developers ranked by popularity, growth, and player retention. Updated daily.';

        SEOTools::setTitle('Top RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('developers.top'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Top RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('developers.top'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle('Best RuneLite Plugin Developers — Top 100 Ranked');
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        $this->addRankingJsonLd(
            $data['entries'] ?? [],
            'Top RuneLite Plugin Developers',
            $description,
            route('developers.top'),
        );

        return inertia('Developers/Top', [
            'rankings' => $data,
        ]);
    }

    public function popular(Request $request): Response
    {
        $period = $this->resolvePeriod($request);
        $data = $this->runeliteApi->getDevelopersPopular($period);

        $description = 'See which RuneLite plugin developers are gaining the most installs across their plugins. Filter by day, week, month, year, or all time.';

        SEOTools::setTitle('Most Popular RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('developers.popular'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Most Popular RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('developers.popular'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle('Most Popular RuneLite Plugin Developers');
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        $this->addRankingJsonLd(
            $data['entries'] ?? [],
            'Most Popular RuneLite Plugin Developers',
            $description,
            route('developers.popular'),
        );

        return inertia('Developers/Popular', [
            'rankings' => $data,
            'period' => $period,
        ]);
    }

    public function growing(Request $request): Response
    {
        $period = $this->resolvePeriod($request, self::GROWING_PERIODS);
        $data = $this->runeliteApi->getDevelopersGrowing($period);

        $description = 'See which RuneLite plugin developers are growing the fastest by percentage. Filter by day, week, month, or year.';

        SEOTools::setTitle('Fastest Growing RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('developers.growing'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Fastest Growing RuneLite Plugin Developers | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('developers.growing'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle('Fastest Growing RuneLite Plugin Developers');
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        $this->addRankingJsonLd(
            $data['entries'] ?? [],
            'Fastest Growing RuneLite Plugin Developers',
            $description,
            route('developers.growing'),
        );

        return inertia('Developers/Growing', [
            'rankings' => $data,
            'period' => $period,
        ]);
    }

    public function show(string $username): Response
    {
        $developer = $this->runeliteApi->getDeveloper($username);

        if ($developer === null) {
            abort(404);
        }

        $name = $developer['name'];
        $pluginCount = $developer['plugin_count'] ?? 0;
        $installs = number_format($developer['total_installs'] ?? 0);
        $title = "{$name} — RuneLite Plugin Developer Stats";
        $description = "{$name} has {$pluginCount} plugins on the RuneLite plugin hub with {$installs} installs. See their portfolio, growth, collaborators, and ranking.";

        SEOTools::setTitle($title);
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('developers.show', $username));
        SEOTools::opengraph()->addProperty('type', 'profile');
        SEOTools::opengraph()->addProperty('title', $title);
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical(route('developers.show', $username));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle($title);
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));

        JsonLd::setType('Person');
        JsonLd::addValue('name', $name);
        JsonLd::addValue('url', route('developers.show', $username));
        JsonLd::addValue('description', $description);

        if (! empty($developer['aliases'])) {
            JsonLd::addValue('alternateName', $developer['aliases']);
        }

        JsonLd::addValue('owns', collect($developer['plugins'] ?? [])
            ->map(fn (array $entry) => [
                '@type' => 'SoftwareApplication',
                'name' => $entry['plugin']['display'] ?? $entry['plugin']['name'],
                'applicationCategory' => 'GameApplication',
                'operatingSystem' => 'Windows, macOS, Linux',
                'url' => "https://runelite.net/plugin-hub/show/{$entry['plugin']['name']}",
                'interactionStatistic' => [
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/InstallAction',
                    'userInteractionCount' => $entry['plugin']['current_installs'],
                ],
            ])->values()->all());

        return inertia('Developers/Show', [
            'developer' => $developer,
        ]);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function resolvePeriod(Request $request, array $allowed = self::PERIODS): string
    {
        $period = $request->query('period', 'month');

        return in_array($period, $allowed, true) ? $period : 'month';
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function addRankingJsonLd(array $entries, string $name, string $description, string $url): void
    {
        $listElements = collect($entries)
            ->map(fn (array $entry) => [
                '@type' => 'ListItem',
                'position' => $entry['rank'],
                'item' => [
                    '@type' => 'Person',
                    'name' => $entry['name'],
                    'url' => route('developers.show', $entry['slug']),
                ],
            ])->values()->all();

        JsonLd::setType('ItemList');
        JsonLd::addValue('name', $name);
        JsonLd::addValue('description', $description);
        JsonLd::addValue('url', $url);
        JsonLd::addValue('numberOfItems', count($listElements));
        JsonLd::addValue('itemListOrder', 'https://schema.org/ItemListOrderDescending');
        JsonLd::addValue('itemListElement', $listElements);
    }
}
