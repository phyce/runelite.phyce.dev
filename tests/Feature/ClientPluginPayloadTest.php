<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The plugin list is a shared Inertia prop, so it is embedded in the HTML of
 * every page. These tests pin the shape that reaches the browser.
 */
class ClientPluginPayloadTest extends TestCase
{
    public function test_it_drops_fields_the_client_never_reads(): void
    {
        $this->fakeRuneliteApi();

        $plugins = $this->sharedPlugins();

        $this->assertCount(1, $plugins);

        foreach (['git_repo', 'support', 'created_on'] as $dropped) {
            $this->assertArrayNotHasKey($dropped, $plugins[0]);
        }
    }

    public function test_it_keeps_every_field_the_table_and_search_read(): void
    {
        $this->fakeRuneliteApi();

        $plugins = $this->sharedPlugins();

        foreach ([
            'id', 'name', 'display', 'author', 'description', 'tags',
            'warning', 'updated_on', 'all_time_high', 'current_installs',
        ] as $kept) {
            $this->assertArrayHasKey($kept, $plugins[0]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function sharedPlugins(): array
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        return $response->viewData('page')['props']['plugins'];
    }

    private function fakeRuneliteApi(): void
    {
        $plugin = [
            'id' => 1,
            'name' => 'example-plugin',
            'git_repo' => 'https://github.com/example/example-plugin.git',
            'display' => 'Example Plugin',
            'author' => 'Example Author',
            'support' => 'https://discord.gg/example',
            'description' => 'An example plugin.',
            'tags' => 'example,test',
            'warning' => '',
            'created_on' => '2024-01-01T00:00:00.000Z',
            'updated_on' => '2024-06-01T00:00:00.000Z',
            'all_time_high' => 200,
            'current_installs' => 100,
        ];

        Http::fake([
            '*/plugins' => Http::response(['success' => true, 'data' => [$plugin]]),
            '*/developers' => Http::response(['success' => true, 'data' => ['developers' => []]]),
            '*' => Http::response(['success' => true, 'data' => []]),
        ]);
    }
}
