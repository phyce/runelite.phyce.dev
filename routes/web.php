<?php

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
Route::get('/{name}', [PluginController::class, 'show'])->name('plugin.show');
