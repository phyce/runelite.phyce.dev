<?php

namespace App\Actions;

use Carbon\Carbon;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap
{
    public function handle(array $plugins): string
    {
        $sitemap = Sitemap::create()
            ->add(
                Url::create(route('home'))
                    ->setPriority(1.0)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            )
            ->add(
                Url::create(route('plugin.random'))
                    ->setPriority(0.3)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_ALWAYS)
            );

        foreach ($plugins as $plugin) {
            $sitemap->add(
                Url::create(route('plugin.show', ['name' => $plugin['name']]))
                    ->setLastModificationDate(Carbon::parse($plugin['updated_on']))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.7)
            );
        }

        return $sitemap->render();
    }
}
