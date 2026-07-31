<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The plugin list is shared with every page but only the header search reads it,
 * so it is deferred out of the initial document. The homepage is the exception:
 * it renders the list itself and passes its own eager prop.
 */
class DeferredPluginPropTest extends TestCase
{
    private ?string $assetVersion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRuneliteApi();
    }

    public function test_the_list_is_absent_from_a_non_homepage_document(): void
    {
        $page = $this->get(route('top'))->assertOk()->viewData('page');

        $this->assertArrayNotHasKey('plugins', $page['props']);
    }

    public function test_the_list_is_advertised_as_deferred_so_the_client_fetches_it(): void
    {
        $page = $this->get(route('top'))->assertOk()->viewData('page');

        $this->assertContains('plugins', $page['deferredProps']['default'] ?? []);
    }

    public function test_a_partial_request_returns_the_deferred_list(): void
    {
        $response = $this->withHeaders($this->partialHeaders())->get(route('top'))->assertOk();

        $this->assertCount(1, $response->json('props.plugins'));
    }

    public function test_the_homepage_still_renders_its_list_eagerly(): void
    {
        $page = $this->get(route('home'))->assertOk()->viewData('page');

        $this->assertCount(1, $page['props']['plugins']);
        $this->assertArrayNotHasKey('deferredProps', $page);
    }

    /**
     * A partial response holds a single prop but hits the same URL, with the
     * same X-Inertia header, as a full visit. Both must not share a cache entry.
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

    /** The asset version is set per request by the middleware, so read it back off a render. */
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
