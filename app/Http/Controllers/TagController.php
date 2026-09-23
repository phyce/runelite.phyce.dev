<?php

namespace App\Http\Controllers;

use App\Services\PluginTagService;
use App\Services\RuneliteApiService;
use Artesaos\SEOTools\Facades\JsonLd;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class TagController extends Controller
{
    private const PERIODS = ['day', 'week', 'month', 'year', 'all'];

    private const GROWING_PERIODS = ['day', 'week', 'month', 'year'];

    private const MAP_LINKS_PER_TAG = 3;

    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function index(): Response
    {
        $data = $this->runeliteApi->getTags();
        $listed = array_values(array_map(
            fn (array $tag): array => [
                'slug' => $tag['slug'],
                'name' => $tag['name'],
                'plugin_count' => $tag['plugin_count'] ?? 0,
                'total_installs' => $tag['total_installs'] ?? 0,
            ],
            $data['tags'] ?? [],
        ));

        $title = 'Tags | RuneLite Plugin Stats';
        $description = 'All '.number_format(count($listed)).' tags used across RuneLite plugins, ranked by how many plugins use them. Browse as a ranked table or an interactive tag map.';

        $this->applyPageMeta($title, $description, route('tags.index'));

        $top = collect($listed)->sortByDesc('plugin_count')->take(100)->values()->all();

        JsonLd::setType('ItemList');
        JsonLd::addValue('name', 'RuneLite Plugin Tags');
        JsonLd::addValue('description', $description);
        JsonLd::addValue('url', route('tags.index'));
        JsonLd::addValue('numberOfItems', count($listed));
        JsonLd::addValue('itemListOrder', 'https://schema.org/ItemListOrderDescending');
        JsonLd::addValue('itemListElement', array_values(array_map(
            fn (array $tag, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => Str::title($tag['name']).' RuneLite Plugins',
                'url' => route('tag.show', $tag['slug']),
            ],
            $top,
            array_keys($top),
        )));

        return inertia('Tags/Index', [
            'tags' => $listed,
        ]);
    }

    public function cloud(PluginTagService $pluginTags): Response
    {
        $index = $this->runeliteApi->getTags()['tags'] ?? [];
        $map = $pluginTags->mapGraph($index, $this->runeliteApi->getPlugins(), self::MAP_LINKS_PER_TAG);

        $title = 'Tag Cloud | RuneLite Plugin Stats';
        $description = 'An interactive map of all '.number_format(count($map['nodes'])).' RuneLite plugin tags, where related tags sit together. Zoom into any tag to explore its plugins.';

        $this->applyPageMeta($title, $description, route('tags.cloud'));

        return inertia('Tags/Cloud', [
            'nodes' => $map['nodes'],
            'links' => $map['links'],
        ]);
    }

    public function top(): Response
    {
        $data = $this->runeliteApi->getTagsTop();

        $topTag = $data['entries'][0]['name'] ?? null;
        $description = $topTag
            ? 'The top 100 RuneLite plugin tags ranked by popularity, growth, and various other metrics. Currently #1: '.Str::title($topTag).'. Updated daily.'
            : 'The top 100 RuneLite plugin tags ranked by popularity, growth, and various other metrics. Updated daily.';

        $this->applyPageMeta('Top Tags | RuneLite Plugin Stats', $description, route('tags.top'));
        $this->addRankingJsonLd($data['entries'] ?? [], 'Top RuneLite Plugin Tags', $description, route('tags.top'));

        return inertia('Tags/Top', [
            'rankings' => $data,
        ]);
    }

    public function popular(Request $request): Response
    {
        $period = $this->resolvePeriod($request);
        $data = $this->runeliteApi->getTagsPopular($period);

        $description = 'See which RuneLite plugin tags are gaining the most installs across their plugins. Filter by day, week, month, year, or all time.';

        $this->applyPageMeta('Most Popular Tags | RuneLite Plugin Stats', $description, route('tags.popular'));
        $this->addRankingJsonLd($data['entries'] ?? [], 'Most Popular RuneLite Plugin Tags', $description, route('tags.popular'));

        return inertia('Tags/Popular', [
            'rankings' => $data,
            'period' => $period,
        ]);
    }

    public function growing(Request $request): Response
    {
        $period = $this->resolvePeriod($request, self::GROWING_PERIODS);
        $data = $this->runeliteApi->getTagsGrowing($period);

        $description = 'See which RuneLite plugin tags are growing the fastest by percentage. Filter by day, week, month, or year.';

        $this->applyPageMeta('Fastest Growing Tags | RuneLite Plugin Stats', $description, route('tags.growing'));
        $this->addRankingJsonLd($data['entries'] ?? [], 'Fastest Growing RuneLite Plugin Tags', $description, route('tags.growing'));

        return inertia('Tags/Growing', [
            'rankings' => $data,
            'period' => $period,
        ]);
    }

    /**
     * The API resolves any spelling of a tag ("Grand Exchange", "OVERLAY"), so
     * every variant is sent on to the one canonical slug instead of being
     * served as a duplicate page. A payload without a slug is not a tag
     * profile: a tag slugged "top" would reach the API's /tags/top rankings.
     */
    public function show(string $slug): Response|RedirectResponse
    {
        $tag = $this->runeliteApi->getTag($slug);

        if (! isset($tag['slug'], $tag['name'])) {
            abort(404);
        }

        if ($slug !== $tag['slug']) {
            return redirect()->route('tag.show', ['slug' => $tag['slug']], 301);
        }

        $label = Str::title($tag['name']);
        $count = $tag['plugin_count'] ?? count($tag['plugins'] ?? []);
        $installs = number_format($tag['total_installs'] ?? 0);
        $topNames = array_map(
            fn (array $entry): string => $entry['plugin']['display'] ?? $entry['plugin']['name'],
            array_slice($tag['plugins'] ?? [], 0, 3),
        );

        $title = "{$label} Plugins | RuneLite Plugin Stats";
        $description = match (true) {
            $topNames === [] => "Install stats for RuneLite plugins tagged {$label}. Install counts, all-time highs, and growth trends.",
            $count === 1 => "{$topNames[0]} is the only RuneLite plugin tagged {$label}, with {$installs} installs. See its install count, all-time high, and growth trend.",
            default => "{$count} RuneLite plugins tagged {$label} with {$installs} combined installs, including ".implode(', ', $topNames).'. Install counts, all-time highs, and growth trends.',
        };

        $this->applyPageMeta($title, $description, route('tag.show', $slug));

        $top = array_slice($tag['plugins'] ?? [], 0, 100);

        JsonLd::setType('ItemList');
        JsonLd::addValue('name', "{$label} RuneLite Plugins");
        JsonLd::addValue('description', $description);
        JsonLd::addValue('url', route('tag.show', $slug));
        JsonLd::addValue('numberOfItems', $count);
        JsonLd::addValue('itemListOrder', 'https://schema.org/ItemListOrderDescending');
        JsonLd::addValue('itemListElement', array_values(array_map(
            fn (array $entry, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => [
                    '@type' => 'SoftwareApplication',
                    'name' => $entry['plugin']['display'] ?? $entry['plugin']['name'],
                    'applicationCategory' => 'GameApplication',
                    'operatingSystem' => 'Windows, macOS, Linux',
                    'url' => "https://runelite.net/plugin-hub/show/{$entry['plugin']['name']}",
                    'interactionStatistic' => [
                        '@type' => 'InteractionCounter',
                        'interactionType' => 'https://schema.org/InstallAction',
                        'userInteractionCount' => $entry['plugin']['current_installs'] ?? 0,
                    ],
                ],
            ],
            $top,
            array_keys($top),
        )));

        return inertia('Tags/Show', [
            'tag' => $tag,
            'title' => $title,
        ]);
    }

    private function applyPageMeta(string $title, string $description, string $url): void
    {
        SEOTools::setTitle($title);
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl($url);
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', $title);
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset('img/og-static.png'));
        SEOMeta::setCanonical($url);
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle($title);
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset('img/og-static.png'));
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
                    '@type' => 'DefinedTerm',
                    'name' => $entry['name'],
                    'url' => route('tag.show', $entry['slug']),
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
