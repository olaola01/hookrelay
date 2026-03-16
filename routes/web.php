<?php

use App\Http\Controllers\WebhookIngestionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('/webhooks/{source}', WebhookIngestionController::class)->name('webhooks.ingest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
