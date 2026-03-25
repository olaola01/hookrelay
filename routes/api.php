<?php

use App\Http\Controllers\WebhookEventController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [WebhookEventController::class, 'index'])->name('api.events.index');
Route::get('/events/failed', [WebhookEventController::class, 'failed'])->name('api.events.failed');
Route::get('/events/stats', [WebhookEventController::class, 'stats'])->name('api.events.stats');
