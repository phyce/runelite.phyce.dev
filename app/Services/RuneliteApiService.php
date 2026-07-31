<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RuneliteApiService
{
    private string $baseUrl;

    /** @var array{0: array<string, array<mixed>>, 1: array<string, array<mixed>>}|null */
    private ?array $developerLookups = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.runelite_api.url'), '/');
    }

    /** @return array<mixed> */
    public function getPlugins(array $params = []): array
    {
        return $this->cached('plugins', $params, 60, function () use ($params) {
            return $this->fetch('plugins', $params) ?? [];
        });
    }

    /** @return array<mixed>|null */
    public function getPlugin(string $name, array $params = []): ?array
    {
        return $this->cached("plugin/{$name}", $params, 60, function () use ($name, $params) {
            return $this->fetch("plugin/{$name}", $params);
        });
    }

    /** @return array<mixed> */
    public function getPluginHistory(string $name, array $params = []): array
    {
        return $this->cached("plugin/{$name}/history", $params, 30, function () use ($name, $params) {
            return $this->fetch("plugin/{$name}/history", $params) ?? [];
        });
    }

    /** @return array<mixed> */
    public function getTopHundred(): array
    {
        return $this->cached('plugins/top', [], 300, function () {
            return $this->fetch('plugins/top', []) ?? [];
        });
    }

    /** @return array<mixed> */
    public function getTopAbsolute(string $period): array
    {
        return $this->cached('plugins/top/absolute', ['period' => $period], 300, function () use ($period) {
            return $this->fetch('plugins/top/absolute', ['period' => $period]) ?? [];
        });
    }

    /** @return array<mixed> */
    public function getTopRelative(string $period): array
    {
        return $this->cached('plugins/top/relative', ['period' => $period], 300, function () use ($period) {
            return $this->fetch('plugins/top/relative', ['period' => $period]) ?? [];
        });
    }

    /** @return array<mixed> */
    public function getGreatest(): array
    {
        return $this->cached('plugins/greatest', [], 86400, function () {
            return $this->fetch('plugins/greatest', []) ?? [];
        });
    }

    /** @return array<mixed>|null */
    public function getRandomPlugin(): ?array
    {
        return $this->fetch('plugins/random', []);
    }

    public function getDevelopers(): array
    {
        return $this->cached('developers', [], 3600, function () {
            return $this->fetch('developers', []) ?? [];
        });
    }

    public function getDeveloper(string $slug): ?array
    {
        return $this->cached("developers/{$slug}", [], 3600, function () use ($slug) {
            return $this->fetch("developers/{$slug}", []);
        });
    }

    public function getDeveloperTop(): array
    {
        return $this->cached('developers/top', [], 3600, function () {
            return $this->fetch('developers/top', []) ?? [];
        });
    }

    public function getDevelopersPopular(string $period): array
    {
        return $this->cached('developers/top/popular', ['period' => $period], 3600, function () use ($period) {
            return $this->fetch('developers/top/popular', ['period' => $period]) ?? [];
        });
    }

    public function getDevelopersGrowing(string $period): array
    {
        return $this->cached('developers/top/growing', ['period' => $period], 3600, function () use ($period) {
            return $this->fetch('developers/top/growing', ['period' => $period]) ?? [];
        });
    }

    public function getPluginDevelopers(string $author): array
    {
        $author = trim($author);

        if ($author === '') {
            return [];
        }

        $names = $this->matchDeveloper($author) !== null ? [$author] : $this->splitAuthors($author);

        return array_values(array_map(fn (string $name): array => $this->matchDeveloper($name) ?? [
            'slug' => null,
            'name' => $name,
            'plugin_count' => null,
            'total_installs' => null,
            'contributing_since' => null,
        ], $names));
    }

    /** @return array<mixed> */
    public function getRelatedPlugins(string $name, int $limit = 12): array
    {
        return $this->cached("plugin/{$name}/related", ['limit' => $limit], 3600, function () use ($name, $limit) {
            return $this->fetch("plugin/{$name}/related", ['limit' => $limit]) ?? [];
        });
    }

    private function matchDeveloper(string $name): ?array
    {
        [$exact, $loose] = $this->developerLookups();

        return $exact[mb_strtolower($name)] ?? $loose[$this->looseKey($name)] ?? null;
    }

    private function developerLookups(): array
    {
        if ($this->developerLookups !== null) {
            return $this->developerLookups;
        }

        $developers = $this->getDevelopers()['developers'] ?? [];

        $exact = [];
        $loose = [];

        foreach ($developers as $developer) {
            $entry = [
                'slug' => $developer['slug'],
                'name' => $developer['name'],
                'plugin_count' => $developer['plugin_count'],
                'total_installs' => $developer['total_installs'],
                'contributing_since' => $developer['contributing_since'],
            ];

            $exact[mb_strtolower($developer['name'])] ??= $entry;

            $key = $this->looseKey($developer['name']);

            if ($key !== '') {
                $loose[$key] ??= $entry;
            }
        }

        return $this->developerLookups = [$exact, $loose];
    }

    private function looseKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($name)) ?? '';
    }

    private function splitAuthors(string $author): array
    {
        $expanded = preg_replace_callback(
            '#https?://\S+#i',
            fn (array $match): string => basename(rtrim($match[0], '/')),
            $author,
        ) ?? $author;

        $separated = preg_replace('/\band\b/i', ',', $expanded) ?? $expanded;
        $parts = preg_split('#[,&|/]| \+ #', $separated) ?: [];
        $parts = array_map(fn (string $part): string => trim($part, " \t-_()[]."), $parts);

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /** @return array<mixed>|null */
    private function fetch(string $endpoint, array $params): ?array
    {
        $response = Http::get("{$this->baseUrl}/{$endpoint}", $params);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        if (! ($json['success'] ?? false)) {
            return null;
        }

        return $json['data'] ?? null;
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function cached(string $endpoint, array $params, int $ttl, callable $callback): mixed
    {
        ksort($params);
        $key = 'runelite:'.md5($endpoint.':'.serialize($params));

        return Cache::remember($key, $ttl, $callback);
    }
}
