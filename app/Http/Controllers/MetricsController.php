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
    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function top100(): Response
    {
        $data = $this->runeliteApi->getTopHundred();

        $topPlugin = $data['rankings'][0]['plugin']['display'] ?? $data['rankings'][0]['plugin']['name'] ?? null;
        $description = $topPlugin
            ? "Top 100 RuneLite plugins ranked by installs, growth, retention & momentum. Currently #1: {$topPlugin}. Updated daily."
            : 'Discover the top 100 RuneLite plugins ranked by installs, 30-day growth, retention, and 7-day momentum. Updated daily.';

        SEOTools::setTitle('Best RuneLite Plugins - Top 100 Ranked | RuneLite Stats');
        SEOTools::setDescription($description);
        SEOTools::opengraph()->setUrl(route('top'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOTools::opengraph()->addProperty('title', 'Best RuneLite Plugins — Top 100 Ranked');
        SEOTools::opengraph()->addProperty('description', $description);
        SEOTools::opengraph()->addProperty('site_name', config('app.name'));
        SEOMeta::setCanonical(route('top'));
        SEOMeta::addMeta('robots', 'index, follow');
        TwitterCard::setTitle('Best RuneLite Plugins — Top 100 Ranked');
        TwitterCard::setDescription($description);
        TwitterCard::setType('summary');

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

        SEOTools::setTitle('Most Popular - RuneLite Plugin Stats');
        SEOTools::setDescription('RuneLite plugins ranked by absolute install count growth over a selected time window.');
        SEOTools::opengraph()->setUrl(route('top.absolute'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOMeta::setCanonical(route('top.absolute'));

        return inertia('TopAbsolute', [
            'entries' => $data,
            'period' => $period,
        ]);
    }

    public function topRelative(Request $request): Response
    {
        $period = $request->query('period', '30d');
        $data = $this->runeliteApi->getTopRelative($period);

        SEOTools::setTitle('Fastest Growing - RuneLite Plugin Stats');
        SEOTools::setDescription('RuneLite plugins ranked by percentage install count growth over a selected time window.');
        SEOTools::opengraph()->setUrl(route('top.relative'));
        SEOTools::opengraph()->addProperty('type', 'website');
        SEOMeta::setCanonical(route('top.relative'));

        return inertia('TopRelative', [
            'entries' => $data,
            'period' => $period,
        ]);
    }
}
