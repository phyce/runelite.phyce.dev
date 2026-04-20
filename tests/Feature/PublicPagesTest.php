<?php

use App\Services\RuneliteApiService;
use Illuminate\Support\Facades\Cache;

function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

it('renders the home page with plugin data', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([
        [
            'id' => 1,
            'name' => 'gpu',
            'display' => 'GPU',
            'author' => 'RuneLite',
            'description' => 'GPU plugin',
            'tags' => 'graphics',
            'current_installs' => 100,
            'all_time_high' => 120,
            'updated_on' => '2026-04-19 00:00:00',
            'created_on' => '2025-01-01 00:00:00',
        ],
    ]);

    app()->instance(RuneliteApiService::class, $mock);

    $response = $this->get(route('home'), inertiaHeaders());

    $response->assertSuccessful();
    $response->assertJsonPath('component', 'Index');
    $response->assertJsonPath('props.plugins.0.name', 'gpu');
});

it('exposes shared inertia props needed by the app shell', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $response = $this->get(route('home'), inertiaHeaders());

    $response->assertSuccessful()->assertJsonPath('component', 'Index');

    $payload = $response->json();

    expect(data_get($payload, 'props.name'))->toBeString()->not->toBeEmpty()
        ->and(data_get($payload, 'props.apiUrl'))->toBeString()->toContain('http')
        ->and(data_get($payload, 'props.appUrl'))->toBeString()->toContain('http');
});

it('renders plugin detail page and returns 404 for unknown plugin', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugin')->with('gpu', \Mockery::type('array'))->once()->andReturn([
        'id' => 1,
        'name' => 'gpu',
        'display' => 'GPU',
        'author' => 'RuneLite',
        'description' => 'GPU plugin',
        'tags' => 'graphics',
        'current_installs' => 100,
        'all_time_high' => 120,
        'updated_on' => '2026-04-19 00:00:00',
        'created_on' => '2025-01-01 00:00:00',
    ]);
    $mock->shouldReceive('getPlugin')->with('missing', \Mockery::type('array'))->once()->andReturn(null);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('plugin.show', ['name' => 'gpu']), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'PluginDetail')
        ->assertJsonPath('props.plugin.name', 'gpu');

    $this->get(route('plugin.show', ['name' => 'missing']))
        ->assertNotFound();
});

it('renders top metrics pages', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getTopHundred')->once()->andReturn([
        'rankings' => [
            [
                'rank' => 1,
                'plugin' => [
                    'id' => 1,
                    'name' => 'gpu',
                    'display' => 'GPU',
                    'author' => 'RuneLite',
                    'current_installs' => 100,
                ],
            ],
        ],
    ]);
    $mock->shouldReceive('getTopAbsolute')->with('30d')->once()->andReturn([]);
    $mock->shouldReceive('getTopRelative')->with('30d')->once()->andReturn([]);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('top'), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'Top100')
        ->assertJsonPath('props.metrics.rankings.0.rank', 1)
        ->assertJsonPath('props.metrics.rankings.0.plugin.name', 'gpu');

    $this->get(route('top.absolute'), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'TopAbsolute');

    $this->get(route('top.relative'), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'TopRelative');
});

it('renders absolute and relative pages with entry rows for ui tables', function () {
    $entries = [
        [
            'plugin' => [
                'name' => 'gpu',
                'display' => 'GPU',
            ],
            'delta' => 123,
            'current_installs' => 1000,
        ],
    ];

    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getTopAbsolute')->with('30d')->once()->andReturn($entries);
    $mock->shouldReceive('getTopRelative')->with('30d')->once()->andReturn($entries);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('top.absolute'), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'TopAbsolute')
        ->assertJsonPath('props.entries.0.plugin.name', 'gpu')
        ->assertJsonPath('props.entries.0.delta', 123);

    $this->get(route('top.relative'), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'TopRelative')
        ->assertJsonPath('props.entries.0.plugin.name', 'gpu')
        ->assertJsonPath('props.entries.0.delta', 123);
});

it('redirects random route to plugin detail', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getRandomPlugin')->once()->andReturn([
        'name' => 'gpu',
    ]);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('plugin.random'))
        ->assertRedirect(route('plugin.show', ['name' => 'gpu']));
});

it('renders sitemap xml and uses cached value when present', function () {
    Cache::put('sitemap.xml', '<urlset><url><loc>https://example.test/gpu</loc></url></urlset>', now()->addMinute());

    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('sitemap'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('https://example.test/gpu', false);
});

it('generates sitemap xml when cache is missing', function () {
    Cache::forget('sitemap.xml');

    $plugins = [
        [
            'name' => 'gpu',
            'updated_on' => '2026-04-19 00:00:00',
        ],
    ];

    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn($plugins);

    app()->instance(RuneliteApiService::class, $mock);

    $response = $this->get(route('sitemap'));

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('plugin.show', ['name' => 'gpu']), false);

    expect(Cache::get('sitemap.xml'))->toBeString();
});

it('returns 404 for missing og image plugin', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugin')->with('missing-plugin', \Mockery::type('array'))->once()->andReturn(null);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('og.image', ['name' => 'missing-plugin']))
        ->assertNotFound();
});

it('passes range query to plugin detail endpoint', function () {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getPlugin')->with('gpu', ['range' => '7d'])->once()->andReturn([
        'id' => 1,
        'name' => 'gpu',
        'display' => 'GPU',
        'author' => 'RuneLite',
        'description' => 'GPU plugin',
        'tags' => 'graphics',
        'current_installs' => 100,
        'all_time_high' => 120,
        'updated_on' => '2026-04-19 00:00:00',
        'created_on' => '2025-01-01 00:00:00',
    ]);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('plugin.show', ['name' => 'gpu', 'range' => '7d']), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'PluginDetail')
        ->assertJsonPath('props.plugin.name', 'gpu');
});

it('passes selected period to absolute and relative metrics pages', function (string $period) {
    $mock = \Mockery::mock(RuneliteApiService::class);
    $mock->shouldReceive('getTopAbsolute')->with($period)->once()->andReturn([]);
    $mock->shouldReceive('getTopRelative')->with($period)->once()->andReturn([]);
    $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);

    app()->instance(RuneliteApiService::class, $mock);

    $this->get(route('top.absolute', ['period' => $period]), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'TopAbsolute')
        ->assertJsonPath('props.period', $period);

    $this->get(route('top.relative', ['period' => $period]), inertiaHeaders())
        ->assertSuccessful()
        ->assertJsonPath('component', 'TopRelative')
        ->assertJsonPath('props.period', $period);
})->with([
    '1 day' => '24h',
    '7 days' => '7d',
    '30 days' => '30d',
    '6 months' => '6m',
    '1 year' => '1y',
]);
