<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tag pages mirror the developer section and are backed by the API's tag
 * endpoints (/tags, /tags/top, /tags/top/popular, /tags/top/growing,
 * /tags/{slug}). These tests pin the index payload, period resolution,
 * detail pass-through, canonical redirects, 404s, the sitemap, and the
 * plugin-page tag links.
 */
class TagPagesTest extends TestCase
{
    public function test_tag_index_lists_every_tag_including_rare_ones(): void
    {
        $this->fakeRuneliteApi();

        $response = $this->get(route('tags.index'));

        $response->assertSuccessful();

        $props = $response->viewData('page')['props'];

        $this->assertSame(['overlay', 'skilling', 'rare-tag'], array_column($props['tags'], 'slug'));
        $this->assertArrayNotHasKey('first_used', $props['tags'][0]);
    }

    public function test_cloud_page_maps_every_tag_by_plugin_count(): void
    {
        $this->fakeRuneliteApi();

        $response = $this->get(route('tags.cloud'));

        $response->assertSuccessful();

        $props = $response->viewData('page')['props'];

        $this->assertSame(['overlay', 'skilling', 'rare-tag'], array_column($props['nodes'], 0));
        $this->assertSame(['rare-tag', 'rare tag', 1, 5], $props['nodes'][2]);
        $this->assertSame([], $props['links']);
        $this->assertArrayNotHasKey('searchTags', $props);
    }

    public function test_top_page_passes_rankings_through(): void
    {
        $this->fakeRuneliteApi();

        $response = $this->get(route('tags.top'));

        $response->assertSuccessful();

        $entries = $response->viewData('page')['props']['rankings']['entries'];

        $this->assertSame([1, 2], array_column($entries, 'rank'));
    }

    public function test_popular_page_resolves_period_and_rejects_invalid_values(): void
    {
        $this->fakeRuneliteApi();

        $this->get(route('tags.popular', ['period' => 'week']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('period', 'week'));

        $this->get(route('tags.popular', ['period' => 'bogus']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('period', 'month'));
    }

    public function test_growing_page_rejects_all_time_period(): void
    {
        $this->fakeRuneliteApi();

        $this->get(route('tags.growing', ['period' => 'all']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('period', 'month'));
    }

    public function test_tag_detail_passes_api_payload_through(): void
    {
        $this->fakeRuneliteApi();

        $response = $this->get(route('tag.show', ['slug' => 'skilling']));

        $response->assertSuccessful();

        $tag = $response->viewData('page')['props']['tag'];

        $this->assertSame('skilling', $tag['name']);
        $this->assertSame(['wood-helper'], array_column(array_column($tag['plugins'], 'plugin'), 'name'));
        $this->assertSame('Skilling Plugins | RuneLite Plugin Stats', $response->viewData('page')['props']['title']);
    }

    public function test_tag_detail_redirects_other_spellings_to_the_canonical_slug(): void
    {
        Http::fake(['*/tags/Skilling' => Http::response(['success' => true, 'data' => $this->skillingProfile()])]);
        $this->fakeRuneliteApi();

        $this->get('/tag/Skilling')
            ->assertMovedPermanently()
            ->assertRedirect(route('tag.show', ['slug' => 'skilling']));
    }

    public function test_unknown_tag_returns_404(): void
    {
        $this->fakeRuneliteApi();

        $this->get('/tag/not-a-real-tag')->assertNotFound();
    }

    public function test_a_tag_slug_that_reaches_an_api_ranking_returns_404(): void
    {
        $this->fakeRuneliteApi();

        $this->get('/tag/top')->assertNotFound();
    }

    public function test_sitemap_lists_every_tag_including_rare_ones(): void
    {
        $this->fakeRuneliteApi();

        $this->get('/sitemap.xml')
            ->assertSuccessful()
            ->assertSee(route('tags.cloud'), false)
            ->assertSee(route('tag.show', ['slug' => 'overlay']), false)
            ->assertSee(route('tag.show', ['slug' => 'rare-tag']), false);
    }

    public function test_plugin_detail_links_its_tags(): void
    {
        $this->fakeRuneliteApi();

        $response = $this->get(route('plugin.show', ['name' => 'better-mining']));

        $response->assertSuccessful();

        $this->assertSame(
            [
                ['slug' => 'mining', 'label' => 'mining'],
                ['slug' => 'grand-exchange', 'label' => 'Grand Exchange'],
            ],
            $response->viewData('page')['props']['tagLinks'],
        );
    }

    private function fakeRuneliteApi(): void
    {
        $overlay = ['slug' => 'overlay', 'name' => 'overlay', 'plugin_count' => 165, 'total_installs' => 2467871, 'first_used' => '2020-02-27T22:24:06Z'];
        $skilling = ['slug' => 'skilling', 'name' => 'skilling', 'plugin_count' => 63, 'total_installs' => 1000000, 'first_used' => '2020-01-03T19:14:40Z'];
        $rare = ['slug' => 'rare-tag', 'name' => 'rare tag', 'plugin_count' => 1, 'total_installs' => 5, 'first_used' => '2024-01-01T00:00:00Z'];

        $rankEntry = fn (int $rank, array $tag): array => $tag + [
            'rank' => $rank,
            'absolute_growth' => 100,
            'pct_growth' => 1.5,
            'growth_base' => 1000,
        ];

        $betterMining = [
            'id' => 2,
            'name' => 'better-mining',
            'display' => 'Better Mining',
            'author' => 'Author Two',
            'description' => 'Mining overlays.',
            'tags' => 'mining, Grand Exchange',
            'warning' => '',
            'updated_on' => '2024-06-01T00:00:00.000Z',
            'all_time_high' => 900,
            'current_installs' => 500,
        ];

        Http::fake([
            '*/tags/top/popular*' => Http::response(['success' => true, 'data' => [
                'window' => 'week', 'computed_at' => 'now', 'entries' => [$rankEntry(1, $overlay)],
            ]]),
            '*/tags/top/growing*' => Http::response(['success' => true, 'data' => [
                'window' => 'week', 'computed_at' => 'now', 'entries' => [$rankEntry(1, $skilling)],
            ]]),
            '*/tags/top' => Http::response(['success' => true, 'data' => [
                'computed_at' => 'now', 'entries' => [$rankEntry(1, $overlay), $rankEntry(2, $skilling)],
            ]]),
            '*/tags/skilling' => Http::response(['success' => true, 'data' => $this->skillingProfile()]),
            '*/tags/not-a-real-tag' => Http::response(['success' => false, 'data' => 'Tag not found'], 404),
            '*/tags' => Http::response(['success' => true, 'data' => [
                'computed_at' => 'now', 'count' => 3316, 'tags' => [$overlay, $skilling, $rare],
            ]]),
            '*/plugin/better-mining' => Http::response(['success' => true, 'data' => $betterMining]),
            '*/plugin/better-mining/*' => Http::response(['success' => true, 'data' => []]),
            '*/plugins' => Http::response(['success' => true, 'data' => [$this->woodHelper(), $betterMining]]),
            '*/developers' => Http::response(['success' => true, 'data' => ['developers' => []]]),
            '*' => Http::response(['success' => true, 'data' => []]),
        ]);
    }

    /** @return array<string, mixed> */
    private function skillingProfile(): array
    {
        return [
            'slug' => 'skilling', 'name' => 'skilling', 'aliases' => null,
            'plugin_count' => 1, 'first_used' => '2020-01-03T19:14:40Z', 'days_used' => 2413,
            'last_updated' => '2026-07-29T15:14:23Z', 'days_since_update' => 15,
            'total_installs' => 100, 'peak_installs' => 300,
            'ranks' => ['installs' => 2],
            'plugins' => [['plugin' => $this->woodHelper(), 'other_tags' => ['woodcutting']]],
        ];
    }

    /** @return array<string, mixed> */
    private function woodHelper(): array
    {
        return [
            'id' => 1,
            'name' => 'wood-helper',
            'display' => 'Wood Helper',
            'author' => 'Author One',
            'description' => 'Helps with woodcutting.',
            'tags' => 'skilling, woodcutting',
            'warning' => '',
            'updated_on' => '2024-06-01T00:00:00.000Z',
            'all_time_high' => 300,
            'current_installs' => 100,
        ];
    }
}
