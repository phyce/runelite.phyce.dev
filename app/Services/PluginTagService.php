<?php

namespace App\Services;

class PluginTagService
{
    /**
     * The plugin's own tags in their original order, paired with the slug of
     * the tag page each one links to.
     *
     * @param  array<mixed>  $plugin
     * @return list<array{slug: string, label: string}>
     */
    public function tagsForPlugin(array $plugin): array
    {
        $raw = $plugin['tags'] ?? '';

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $links = [];

        foreach (explode(',', $raw) as $tag) {
            $tag = trim($tag);
            $slug = self::slug($tag);

            if ($slug !== '' && ! array_key_exists($slug, $links)) {
                $links[$slug] = ['slug' => $slug, 'label' => $tag];
            }
        }

        return array_values($links);
    }

    /**
     * A tag's slug exactly as the API computes it (metrics.Slug in the Go
     * service): lowercased, each run of anything that is not a letter or digit
     * collapsed to one dash, dashes trimmed from the ends. Str::slug is not a
     * substitute: it deletes punctuation rather than dashing it ("death's
     * coffer" becomes deaths-coffer, the API's is death-s-coffer) and
     * transliterates non-ASCII letters, so its links 404.
     */
    public static function slug(string $tag): string
    {
        return trim(preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($tag))) ?? '', '-');
    }
}
