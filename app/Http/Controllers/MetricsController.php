<?php

namespace App\Http\Controllers;

use App\Services\RuneliteApiService;
use Artesaos\SEOTools\Facades\JsonLd;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\Request;
use Inertia\Response;

class MetricsController extends Controller
{
    private const STATIC_OG_IMAGE = 'img/og-static.png';

    private const ROBOTS_INDEX_FOLLOW = 'index, follow';

    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function top100(): Response
    {
        $data = $this->runeliteApi->getTopHundred();

        $topPlugin = $data['rankings'][0]['plugin']['display'] ?? $data['rankings'][0]['plugin']['name'] ?? null;
        $description = $topPlugin
            ? "The top 100 RuneLite plugins ranked by popularity, growth, and player retention. Currently #1: {$topPlugin}. Updated daily."
            : 'The top 100 RuneLite plugins ranked by popularity, growth, and player retention. Updated daily.';

        SEOTools::setTitle('Top 100 RuneLite Plugins | RuneLite Plugin Stats');
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('top'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Top 100 RuneLite Plugins | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset(self::STATIC_OG_IMAGE));
        SEOMeta::setCanonical(route('top'));
        SEOMeta::addMeta('robots', self::ROBOTS_INDEX_FOLLOW);
        TwitterCard::setTitle('Best RuneLite Plugins — Top 100 Ranked');
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset(self::STATIC_OG_IMAGE));

        $listElements = collect($data['rankings'] ?? [])
            ->take(100)
            ->map(fn (array $entry) => [
                '@type' => 'ListItem',
                'position' => $entry['rank'],
                'item' => [
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
                ],
            ])->values()->all();

        JsonLd::setType('ItemList');
        JsonLd::addValue('name', 'Top 100 RuneLite Plugins');
        JsonLd::addValue('description', $description);
        JsonLd::addValue('url', route('top'));
        JsonLd::addValue('numberOfItems', count($listElements));
        JsonLd::addValue('itemListOrder', 'https://schema.org/ItemListOrderDescending');
        JsonLd::addValue('itemListElement', $listElements);

        return inertia('Top100', [
            'metrics' => $data,
        ]);
    }

    public function topAbsolute(Request $request): Response
    {
        $period = $request->query('period', '30d');
        $data = $this->runeliteApi->getTopAbsolute($period);

        SEOTools::setTitle('Most Popular RuneLite Plugins | RuneLite Plugin Stats');
        SEOTools::setDescription('See which RuneLite plugins are gaining the most new installs. Filter by 24 hours, 7 days, 30 days, 6 months, or 1 year.');
        SEOTools::opengraph()->setUrl(route('top.absolute'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Most Popular RuneLite Plugins | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', 'See which RuneLite plugins are gaining the most new installs.');
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset(self::STATIC_OG_IMAGE));
        SEOMeta::setCanonical(route('top.absolute'));
        SEOMeta::addMeta('robots', self::ROBOTS_INDEX_FOLLOW);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset(self::STATIC_OG_IMAGE));

        return inertia('TopAbsolute', [
            'entries' => $data,
            'period' => $period,
        ]);
    }

    public function topRelative(Request $request): Response
    {
        $period = $request->query('period', '30d');
        $data = $this->runeliteApi->getTopRelative($period);

        SEOTools::setTitle('Fastest Growing RuneLite Plugins | RuneLite Plugin Stats');
        SEOTools::setDescription('See which RuneLite plugins are growing the fastest. Filter by 24 hours, 7 days, 30 days, 6 months, or 1 year.');
        SEOTools::opengraph()->setUrl(route('top.relative'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Fastest Growing RuneLite Plugins | RuneLite Plugin Stats');
        SEOTools::opengraph()->addProperty('description', 'See which RuneLite plugins are growing the fastest.');
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOTools::opengraph()->addImage(asset(self::STATIC_OG_IMAGE));
        SEOMeta::setCanonical(route('top.relative'));
        SEOMeta::addMeta('robots', self::ROBOTS_INDEX_FOLLOW);
        TwitterCard::setType('summary_large_image');
        TwitterCard::setImage(asset(self::STATIC_OG_IMAGE));

        return inertia('TopRelative', [
            'entries' => $data,
            'period' => $period,
        ]);
    }
}
