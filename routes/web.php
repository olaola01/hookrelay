<?php

use App\Http\Controllers\WebhookEventController;
use App\Http\Controllers\WebhookIngestionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('/webhooks/{source}', WebhookIngestionController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.ingest');

Route::post('/events/{webhookEvent}/replay', [WebhookEventController::class, 'replay'])->name('events.replay');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
