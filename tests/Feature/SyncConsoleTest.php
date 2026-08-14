<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\SyncConsole;
use Livewire\Livewire;
use Tests\TestCase;

final class SyncConsoleTest extends TestCase
{
    public function test_livewire_queues_and_dispatches_an_offline_edit(): void
    {
        $component = Livewire::test(SyncConsole::class)
            ->assertSee('Laravel Livewire OptoSync')
            ->set('title', 'Edited while offline')
            ->call('queueEdit')
            ->assertSet('title', '')
            ->assertSee('Edited while offline')
            ->assertDispatched('opto-sync-enqueue');

        $queued = $component->get('queued');
        self::assertCount(1, $queued);
        self::assertSame('Edited while offline', $queued[0]['title']);
        self::assertNotSame('', $queued[0]['id']);
    }
}

