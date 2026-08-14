<?php

declare(strict_types=1);

namespace Tests;

use App\OptoSyncFixtureServiceProvider;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, OptoSyncFixtureServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        assert($app instanceof Application);
        $app['config']->set('app.key', 'base64:eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg=');
        $app['config']->set('view.paths', [dirname(__DIR__) . '/resources/views']);
        $app['config']->set('queue.default', 'sync');
    }
}
