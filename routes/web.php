<?php

use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/og/{name}.png', \App\Http\Controllers\OgImageController::class)->name('og.image');
Route::get('/', [PluginController::class, 'index'])->name('home');
Route::get('/random', [PluginController::class, 'random'])->name('plugin.random');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/top', [MetricsController::class, 'top100'])->name('top');
Route::get('/top/absolute', [MetricsController::class, 'topAbsolute'])->name('top.absolute');
Route::get('/top/relative', [MetricsController::class, 'topRelative'])->name('top.relative');
Route::get('/developers', [DeveloperController::class, 'index'])->name('developers.index');
Route::get('/developers/top', [DeveloperController::class, 'top'])->name('developers.top');
Route::get('/developers/popular', [DeveloperController::class, 'popular'])->name('developers.popular');
Route::get('/developers/growing', [DeveloperController::class, 'growing'])->name('developers.growing');
Route::get('/developers/{username}', [DeveloperController::class, 'show'])->name('developers.show');
Route::get('/{name}', [PluginController::class, 'show'])->name('plugin.show');
