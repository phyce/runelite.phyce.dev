<?php

use App\Http\Controllers\PluginController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PluginController::class, 'index'])->name('home');
Route::get('/random', [PluginController::class, 'random'])->name('plugin.random');
Route::get('/{name}', [PluginController::class, 'show'])->name('plugin.show');
