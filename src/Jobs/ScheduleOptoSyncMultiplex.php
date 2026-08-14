<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;

final readonly class ScheduleOptoSyncMultiplex
{
    public function __construct(private Dispatcher $dispatcher)
    {
    }

    /** @return array{upload: FlushOptoSyncLane, realtime: FlushOptoSyncLane} */
    public function jobs(ImmutableSyncBatch $batch, string $baseUrl): array
    {
        return [
            'upload' => new FlushOptoSyncLane($batch, 'upload', $baseUrl),
            'realtime' => new FlushOptoSyncLane($batch, 'realtime', $baseUrl),
        ];
    }

    public function dispatch(ImmutableSyncBatch $batch, string $baseUrl): void
    {
        foreach ($this->jobs($batch, $baseUrl) as $job) {
            $this->dispatcher->dispatch($job);
        }
    }
}

