<main data-runtime="laravel-livewire">
    <h1>Laravel Livewire OptoSync</h1>

    <form wire:submit="queueEdit">
        <label for="title">Offline document title</label>
        <input id="title" wire:model="title" autocomplete="off">
        @error('title') <p role="alert">{{ $message }}</p> @enderror
        <button type="submit">Queue durable edit</button>
    </form>

    <ol aria-label="Locally queued mutations">
        @foreach ($queued as $mutation)
            <li wire:key="{{ $mutation['id'] }}">{{ $mutation['title'] }}</li>
        @endforeach
    </ol>

    @script
    <script>
        const registration = await navigator.serviceWorker.register('/service-worker.js');
        const ready = await navigator.serviceWorker.ready;

        document.addEventListener('opto-sync-enqueue', (event) => {
            const worker = registration.active ?? ready.active;
            worker?.postMessage({
                type: 'OPTO_SYNC_ENQUEUE',
                mutation: event.detail.mutation,
            });
        });

        window.addEventListener('online', () => {
            (registration.active ?? ready.active)?.postMessage({ type: 'OPTO_SYNC_WAKE' });
        });
    </script>
    @endscript
</main>

