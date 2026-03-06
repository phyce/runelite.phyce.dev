<?php

namespace App\Http\Controllers;

use App\Services\RuneliteApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PluginController extends Controller
{
    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function index(Request $request): Response
    {
        $params = $request->only(['range']);
        $plugins = $this->runeliteApi->getPlugins($params);

        return inertia('Index', [
            'plugins' => $plugins,
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        $params = $request->only(['range']);
        $plugin = $this->runeliteApi->getPlugin($name, $params);

        if ($plugin === null) {
            abort(404);
        }

        return inertia('PluginDetail', [
            'plugin' => $plugin,
            'plugins' => $this->runeliteApi->getPlugins([]),
        ]);
    }

    public function random(Request $request): RedirectResponse
    {
        $plugin = $this->runeliteApi->getRandomPlugin();

        return redirect()->route('plugin.show', ['name' => $plugin['name']]);
    }
}
