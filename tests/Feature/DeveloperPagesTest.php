<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The API resolves any spelling of a developer's name to their profile, so the
 * site redirects every variant to the canonical slug rather than serving
 * duplicate pages.
 */
class DeveloperPagesTest extends TestCase
{
    public function test_developer_profile_redirects_other_spellings_to_the_canonical_slug(): void
    {
        $this->fakeRuneliteApi();

        $this->get('/developers/'.rawurlencode('Mr. Sableye'))
            ->assertMovedPermanently()
            ->assertRedirect(route('developers.show', ['username' => 'mr-sableye']));
    }

    public function test_developer_profile_renders_at_its_canonical_slug(): void
    {
        $this->fakeRuneliteApi();

        $this->get(route('developers.show', ['username' => 'mr-sableye']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('developer.slug', 'mr-sableye'));
    }

    public function test_unknown_developer_returns_404(): void
    {
        $this->fakeRuneliteApi();

        $this->get('/developers/nobody-at-all')->assertNotFound();
    }

    private function fakeRuneliteApi(): void
    {
        $profile = [
            'slug' => 'mr-sableye',
            'name' => 'Mr. Sableye',
            'aliases' => ['Mr Sableye', 'Mr. Sableye'],
            'plugin_count' => 0,
            'contributing_since' => null,
            'days_contributing' => 0,
            'last_updated' => null,
            'days_since_update' => 0,
            'total_installs' => 0,
            'peak_installs' => 0,
            'ranks' => ['installs' => 1],
            'plugins' => [],
        ];

        Http::fake([
            '*/developers/Mr.%20Sableye' => Http::response(['success' => true, 'data' => $profile]),
            '*/developers/mr-sableye' => Http::response(['success' => true, 'data' => $profile]),
            '*/developers/nobody-at-all' => Http::response(['success' => false, 'data' => 'Developer not found'], 404),
            '*' => Http::response(['success' => true, 'data' => []]),
        ]);
    }
}
