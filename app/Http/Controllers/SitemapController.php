<?php

namespace App\Http\Controllers;

use App\Actions\GenerateSitemap;
use App\Services\RuneliteApiService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function __invoke(): Response
    {
        $xml = Cache::get('sitemap.xml');

        if ($xml === null) {
            $plugins = $this->runeliteApi->getPlugins([]);
            $xml = app(GenerateSitemap::class)->handle($plugins);
            Cache::put('sitemap.xml', $xml, now()->addWeek());
        }

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
