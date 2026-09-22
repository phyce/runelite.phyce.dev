<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The API answers 404 for things that do not exist and 5xx (or nothing) when
 * it cannot answer. Only a 404 may become a 404 page; an outage must become a
 * 503 that crawlers retry, must never be cached as empty data, and must not
 * take down a page whose own data is fine.
 */
class ApiOutageTest extends TestCase
{
    public function test_an_api_error_on_a_profile_is_a_503_not_a_404(): void
    {
        $this->fakeRuneliteApi(['*/tags/skilling' => Http::response(['success' => false, 'data' => 'Error reading Tag cache'], 500)]);

        $this->get(route('tag.show', ['slug' => 'skilling']))->assertServiceUnavailable();
    }

    public function test_an_unreachable_api_is_a_503(): void
    {
        $this->fakeRuneliteApi(['*/tags/skilling' => Http::failedConnection()]);

        $this->get(route('tag.show', ['slug' => 'skilling']))->assertServiceUnavailable();
    }

    public function test_a_failed_list_is_not_cached_as_empty(): void
    {
        $this->fakeRuneliteApi(['*/tags' => Http::sequence()
            ->push(['success' => false, 'data' => 'Tag metrics cache not yet available'], 503)
            ->push(['success' => true, 'data' => ['computed_at' => 'now', 'count' => 1, 'tags' => [
                ['slug' => 'overlay', 'name' => 'overlay', 'plugin_count' => 165, 'total_installs' => 2467871, 'first_used' => null],
            ]]])]);

        $this->get(route('tags.index'))->assertServiceUnavailable();

        $this->get(route('tags.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->has('tags', 1));
    }

    public function test_the_homepage_survives_an_outage_of_the_sitemap_only_endpoints(): void
    {
        $this->fakeRuneliteApi([
            '*/tags' => Http::response(['success' => false, 'data' => 'Tag metrics cache not yet available'], 503),
            '*/developers' => Http::response(['success' => false, 'data' => 'Developer metrics cache not yet available'], 503),
        ]);

        $this->get(route('home'))->assertSuccessful();
    }

    public function test_a_plugin_page_survives_an_outage_of_its_secondary_data(): void
    {
        $this->fakeRuneliteApi([
            '*/plugin/wood-helper/related*' => Http::response(['success' => false, 'data' => 'Error reading related plugins cache'], 500),
            '*/developers' => Http::response(['success' => false, 'data' => 'Developer metrics cache not yet available'], 503),
        ]);

        $this->get(route('plugin.show', ['name' => 'wood-helper']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('related', [])
                ->where('developers.0.name', 'Author One')
                ->where('developers.0.slug', null));
    }

    /**
     * @param  array<string, mixed>  $overrides  fakes that take precedence over the defaults
     */
    private function fakeRuneliteApi(array $overrides = []): void
    {
        $woodHelper = [
            'id' => 1,
            'name' => 'wood-helper',
            'display' => 'Wood Helper',
            'author' => 'Author One',
            'description' => 'Helps with woodcutting.',
            'tags' => 'skilling',
            'warning' => '',
            'updated_on' => '2024-06-01T00:00:00.000Z',
            'all_time_high' => 300,
            'current_installs' => 100,
        ];

        Http::fake($overrides + [
            '*/plugins' => Http::response(['success' => true, 'data' => [$woodHelper]]),
            '*/plugin/wood-helper' => Http::response(['success' => true, 'data' => $woodHelper]),
            '*/developers' => Http::response(['success' => true, 'data' => ['developers' => []]]),
            '*/tags' => Http::response(['success' => true, 'data' => ['computed_at' => 'now', 'count' => 0, 'tags' => []]]),
            '*' => Http::response(['success' => true, 'data' => []]),
        ]);
    }
}
