<?php

namespace App\Services;

class PluginTagService
{
    /**
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

    public function mapGraph(array $tags, array $plugins, int $linksPerTag): array
    {
        usort($tags, fn (array $a, array $b): int => [$b['plugin_count'] ?? 0, $b['total_installs'] ?? 0, $a['slug']]
            <=> [$a['plugin_count'] ?? 0, $a['total_installs'] ?? 0, $b['slug']]);

        $nodes = array_map(fn (array $tag): array => [
            $tag['slug'],
            $tag['name'],
            $tag['plugin_count'] ?? 0,
            $tag['total_installs'] ?? 0,
        ], $tags);

        $position = array_flip(array_column($nodes, 0));
        $members = array_fill(0, count($nodes), 0);
        $shared = [];

        foreach ($plugins as $plugin) {
            $present = [];

            foreach ($this->tagsForPlugin($plugin) as $link) {
                if (isset($position[$link['slug']])) {
                    $present[] = $position[$link['slug']];
                }
            }

            sort($present);

            foreach ($present as $i => $a) {
                $members[$a]++;

                foreach (array_slice($present, $i + 1) as $b) {
                    $shared[$a][$b] = ($shared[$a][$b] ?? 0) + 1;
                }
            }
        }

        $strengths = [];
        $partners = [];

        foreach ($shared as $a => $row) {
            foreach ($row as $b => $count) {
                $partners[$a][] = $b;
                $partners[$b][] = $a;

                if ($count >= 2) {
                    $strengths[$a][$b] = $strengths[$b][$a] = $count / ($members[$a] + $members[$b] - $count);
                }
            }
        }

        $links = [];
        $add = function (int $a, int $b, float $strength) use (&$links): void {
            $key = min($a, $b).':'.max($a, $b);
            $links[$key] = [min($a, $b), max($a, $b), max($links[$key][2] ?? 0, round($strength, 3), 0.001)];
        };

        foreach ($strengths as $a => $row) {
            arsort($row);

            foreach (array_slice($row, 0, $linksPerTag, true) as $b => $strength) {
                $add($a, $b, $strength);
            }
        }

        foreach ($partners as $a => $candidates) {
            if (isset($strengths[$a])) {
                continue;
            }

            $anchor = array_reduce($candidates, fn (?int $best, int $b): int => $best === null || $members[$b] > $members[$best] ? $b : $best);
            $add($a, $anchor, 1 / ($members[$a] + $members[$anchor] - 1));
        }

        usort($links, fn (array $x, array $y): int => [$x[0], $x[1]] <=> [$y[0], $y[1]]);

        return ['nodes' => $nodes, 'links' => $links];
    }

    public static function slug(string $tag): string
    {
        return trim(preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($tag))) ?? '', '-');
    }
}
