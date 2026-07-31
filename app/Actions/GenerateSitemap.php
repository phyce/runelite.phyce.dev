<?php

namespace App\Actions;

use App\Services\RuneliteApiService;
use Carbon\Carbon;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap
{
    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function handle(array $plugins): string
    {
        $sitemap = Sitemap::create()
            ->add(
                Url::create(route('home'))
                    ->setPriority(1.0)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            )
            ->add(
                Url::create(route('top'))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            )
            ->add(
                Url::create(route('top.absolute'))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            )
            ->add(
                Url::create(route('top.relative'))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );

        foreach (['developers.index', 'developers.top', 'developers.popular', 'developers.growing'] as $developerRoute) {
            $sitemap->add(
                Url::create(route($developerRoute))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );
        }

        foreach ($plugins as $plugin) {
            $sitemap->add(
                Url::create(route('plugin.show', ['name' => $plugin['name']]))
                    ->setLastModificationDate(Carbon::parse($plugin['updated_on']))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.7)
            );
        }

        foreach ($this->runeliteApi->getDevelopers()['developers'] ?? [] as $developer) {
            $sitemap->add(
                Url::create(route('developers.show', ['username' => $developer['slug']]))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.6)
            );
        }

        return $sitemap->render();
    }
}
