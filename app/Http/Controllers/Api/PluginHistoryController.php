<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RuneliteApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginHistoryController extends Controller
{
    public function __construct(private RuneliteApiService $runeliteApi) {}

    public function __invoke(Request $request, string $name): JsonResponse
    {
        $params = array_filter($request->only(['range']), fn ($value) => $value !== null && $value !== '');

        $history = $this->runeliteApi->getPluginHistory($name, $params);

        return response()->json(['data' => $history]);
    }
}
