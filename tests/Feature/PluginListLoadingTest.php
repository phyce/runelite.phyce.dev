<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The plugin list is ~640 KB and only two things read it: the homepage table
 * and the header search box. The homepage therefore ships it with the page,
 * while every other page withholds it until the search box asks for it.
 */
class PluginListLoadingTest extends TestCase
{
    private ?string $assetVersion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRuneliteApi();
    }

    public function test_the_homepage_ships_the_list_with_the_page(): void
    {
        $page = $this->get(route('home'))->assertOk()->viewData('page');

        $this->assertCount(1, $page['props']['plugins']);
    }

    /**
     * The list used to be declared twice — once as a shared prop and again by
     * the controller — which the props resolver collapsed to one copy on the
     * wire but not before fetching and reshaping it twice. A `range` in the
     * query separated the two cache keys, so it cost two API calls as well.
     */
    public function test_the_homepage_fetches_the_plugin_list_once(): void
    {
        $this->get(route('home', ['range' => 'week']))->assertOk();

        $this->assertSame(1, $this->timesPluginListWasFetched());
    }

    public function test_a_page_that_does_not_show_the_list_never_fetches_it(): void
    {
        $this->get(route('top'))->assertOk();

        $this->assertSame(0, $this->timesPluginListWasFetched());
    }

    public function test_another_page_does_not_carry_the_list(): void
    {
        $page = $this->get(route('top'))->assertOk()->viewData('page');

        $this->assertArrayNotHasKey('plugins', $page['props']);
    }

    /**
     * Deferred props are fetched by the client automatically after paint, which
     * is exactly what this must not do — the list is only worth fetching once
     * the visitor opens the search box.
     */
    public function test_another_page_does_not_advertise_the_list_for_auto_fetching(): void
    {
        $page = $this->get(route('top'))->assertOk()->viewData('page');

        $this->assertArrayNotHasKey('deferredProps', $page);
    }

    public function test_the_search_box_can_load_the_list_on_demand(): void
    {
        $response = $this->withHeaders($this->partialHeaders())->get(route('top'))->assertOk();

        $this->assertCount(1, $response->json('props.plugins'));
    }

    /**
     * A partial response holds one prop but hits the same URL, with the same
     * X-Inertia header, as a full visit. The two must not share a cache entry,
     * or a visitor arriving fresh can be served the search box's response.
     */
    public function test_partial_and_full_responses_do_not_share_a_cache_entry(): void
    {
        $partial = $this->withHeaders($this->partialHeaders())->get(route('top'))->assertOk();

        $this->assertArrayNotHasKey('metrics', $partial->json('props'));

        // withHeaders() accumulates, so the partial headers have to be cleared
        // or the second request is another partial rather than a full visit.
        $this->flushHeaders();

        $full = $this->withHeaders($this->inertiaHeaders())->get(route('top'))->assertOk();

        $this->assertArrayHasKey('metrics', $full->json('props'));
    }

    /**
     * A client that already holds the list gets a response without it. That
     * response must not then be handed to a visitor arriving fresh.
     */
    public function test_a_client_holding_the_list_does_not_poison_the_cache_for_others(): void
    {
        $repeat = $this->withHeaders($this->inertiaHeaders() + [
            'X-Inertia-Except-Once-Props' => 'plugins',
        ])->get(route('home'))->assertOk();

        $this->assertArrayNotHasKey('plugins', $repeat->json('props'));

        // Both visits carry the same X-Inertia header and hit the same URL, so
        // only the once-props header separates them.
        $this->flushHeaders();

        $fresh = $this->withHeaders($this->inertiaHeaders())->get(route('home'))->assertOk();

        $this->assertArrayHasKey('plugins', $fresh->json('props'));
        $this->assertCount(1, $fresh->json('props.plugins'));
    }

    private function timesPluginListWasFetched(): int
    {
        return collect(Http::recorded())
            ->filter(fn (array $exchange): bool => parse_url($exchange[0]->url(), PHP_URL_PATH) === '/plugins')
            ->count();
    }

    /** The asset version is set per request by the middleware, so read it off a render. */
    private function inertiaVersion(): string
    {
        return $this->assetVersion ??= (string) $this->get(route('top'))->viewData('page')['version'];
    }

    /** @return array<string, string> */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->inertiaVersion(),
        ];
    }

    /** @return array<string, string> */
    private function partialHeaders(): array
    {
        return $this->inertiaHeaders() + [
            'X-Inertia-Partial-Component' => 'Top100',
            'X-Inertia-Partial-Data' => 'plugins',
        ];
    }

    private function fakeRuneliteApi(): void
    {
        $plugin = [
            'id' => 1,
            'name' => 'example-plugin',
            'display' => 'Example Plugin',
            'author' => 'Example Author',
            'description' => 'An example plugin.',
            'tags' => 'example,test',
            'warning' => '',
            'created_on' => '2024-01-01T00:00:00.000Z',
            'updated_on' => '2024-06-01T00:00:00.000Z',
            'all_time_high' => 200,
            'current_installs' => 100,
        ];

        Http::fake([
            '*/plugins/top' => Http::response(['success' => true, 'data' => ['rankings' => []]]),
            '*/plugins' => Http::response(['success' => true, 'data' => [$plugin]]),
            '*/developers' => Http::response(['success' => true, 'data' => ['developers' => []]]),
            '*' => Http::response(['success' => true, 'data' => []]),
        ]);
    }
}
