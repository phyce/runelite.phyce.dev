<?php

namespace Tests\Unit;

use App\Services\PluginTagService;
use PHPUnit\Framework\TestCase;

class PluginTagMapGraphTest extends TestCase
{
    public function test_it_orders_every_tag_and_keeps_the_strongest_links(): void
    {
        $tags = [
            ['slug' => 'd', 'name' => 'D', 'plugin_count' => 1, 'total_installs' => 9000],
            ['slug' => 'b', 'name' => 'b', 'plugin_count' => 3, 'total_installs' => 10],
            ['slug' => 'a', 'name' => 'a', 'plugin_count' => 4, 'total_installs' => 10],
            ['slug' => 'c', 'name' => 'c', 'plugin_count' => 2, 'total_installs' => 10],
        ];
        $plugins = [
            ['tags' => 'a, b'],
            ['tags' => ' A , B '],
            ['tags' => 'a, c'],
            ['tags' => 'a, c'],
            ['tags' => 'b, d'],
        ];

        $map = (new PluginTagService)->mapGraph($tags, $plugins, 1);

        $this->assertSame([
            ['a', 'a', 4, 10],
            ['b', 'b', 3, 10],
            ['c', 'c', 2, 10],
            ['d', 'D', 1, 9000],
        ], $map['nodes']);
        $this->assertSame([[0, 1, 0.4], [0, 2, 0.5], [1, 3, 0.333]], $map['links']);
    }

    public function test_a_tag_sharing_one_plugin_is_anchored_to_its_biggest_partner(): void
    {
        $tags = [
            ['slug' => 'big', 'name' => 'big', 'plugin_count' => 3, 'total_installs' => 10],
            ['slug' => 'small', 'name' => 'small', 'plugin_count' => 1, 'total_installs' => 10],
            ['slug' => 'lone', 'name' => 'lone', 'plugin_count' => 1, 'total_installs' => 10],
        ];
        $plugins = [
            ['tags' => 'big'],
            ['tags' => 'big'],
            ['tags' => 'big, small, lone'],
        ];

        $map = (new PluginTagService)->mapGraph($tags, $plugins, 3);

        $this->assertSame([[0, 1, 0.333], [0, 2, 0.333]], $map['links']);
    }

    public function test_a_tag_on_no_shared_plugin_has_no_link(): void
    {
        $tags = [
            ['slug' => 'a', 'name' => 'a', 'plugin_count' => 1, 'total_installs' => 10],
            ['slug' => 'b', 'name' => 'b', 'plugin_count' => 1, 'total_installs' => 10],
        ];

        $map = (new PluginTagService)->mapGraph($tags, [['tags' => 'a'], ['tags' => 'b']], 3);

        $this->assertCount(2, $map['nodes']);
        $this->assertSame([], $map['links']);
    }
}
