<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class SyncConsole extends Component
{
    #[Validate('required|string|max:120')]
    public string $title = '';

    /** @var list<array{id: string, title: string}> */
    public array $queued = [];

    public function queueEdit(): void
    {
        $this->validate();
        $mutation = ['id' => (string) Str::uuid(), 'title' => $this->title];
        $this->queued[] = $mutation;
        $this->dispatch('opto-sync-enqueue', mutation: $mutation);
        $this->reset('title');
    }

    public function render(): View
    {
        return view('livewire.sync-console');
    }
}

