<?php

declare(strict_types=1);

namespace App;

use App\Livewire\SyncConsole;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class OptoSyncFixtureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__) . '/routes/api.php');
        $this->loadViewsFrom(dirname(__DIR__) . '/resources/views', 'opto-sync-fixture');
        Livewire::component('sync-console', SyncConsole::class);
    }
}

