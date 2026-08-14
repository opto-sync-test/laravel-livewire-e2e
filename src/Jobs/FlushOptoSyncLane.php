<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;

final class FlushOptoSyncLane implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly ImmutableSyncBatch $batch,
        public readonly string $lane,
        public readonly string $baseUrl,
    ) {
        if (!in_array($lane, ['upload', 'realtime'], true)) {
            throw new InvalidArgumentException('lane must be upload or realtime');
        }
        $this->onQueue("opto-sync-{$lane}");
    }

    public function uniqueId(): string
    {
        return "{$this->lane}:{$this->batch->sha256}";
    }

    public function backoff(): array
    {
        return [1, 5, 20, 60];
    }

    public function handle(Factory $http): void
    {
        $http
            ->withHeaders([
                'x-opto-sync-lane' => $this->lane,
                'x-opto-sync-batch-sha256' => $this->batch->sha256,
            ])
            ->withBody($this->batch->body, 'application/json')
            ->post(rtrim($this->baseUrl, '/') . "/api/sync/{$this->lane}")
            ->throw();
    }
}

