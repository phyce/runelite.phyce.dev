<?php

namespace Tests\Unit;

use App\Services\PluginTagService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tag links on plugin pages must use the API's own slug (metrics.Slug in the
 * Go service), or they 404. Most cases are real tags from the plugin hub.
 */
class PluginTagSlugTest extends TestCase
{
    #[DataProvider('tags')]
    public function test_slugs_match_the_api(string $tag, string $slug): void
    {
        $this->assertSame($slug, PluginTagService::slug($tag));
    }

    /** @return array<string, array{string, string}> */
    public static function tags(): array
    {
        return [
            'apostrophe becomes a dash' => ["death's coffer", 'death-s-coffer'],
            'dots become dashes' => ['wiseoldman.net', 'wiseoldman-net'],
            'trailing dots are trimmed' => ['b.a.', 'b-a'],
            'slash becomes a dash' => ['gp/hr', 'gp-hr'],
            'underscore becomes a dash' => ['xp_drop', 'xp-drop'],
            'case and whitespace runs collapse' => ['  Grand   Exchange ', 'grand-exchange'],
            'quotes and braces are dropped' => ['{"fire cape"', 'fire-cape'],
            'symbols-only suffix is trimmed' => ['C++', 'c'],
            'non-ASCII letters are kept' => ['日本語', '日本語'],
            'accents are not transliterated' => ['Über', 'über'],
            'nothing usable leaves no slug' => ['---', ''],
        ];
    }
}
