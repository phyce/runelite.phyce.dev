<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RuneliteApiService
{
    private string $baseUrl;

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

    /** @return array<mixed>|null */
    public function getRandomPlugin(): ?array
    {
        return $this->fetch('plugins/random', []);
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
