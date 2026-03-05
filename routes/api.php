<?php

use App\Http\Controllers\Api\PluginHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/plugin/{name}/history', PluginHistoryController::class)->name('api.plugin.history');
