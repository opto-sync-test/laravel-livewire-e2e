<?php

declare(strict_types=1);

use App\Http\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/api/sync/{lane}', SyncController::class)
    ->whereIn('lane', ['upload', 'realtime'])
    ->name('opto-sync.fixture.sync');

