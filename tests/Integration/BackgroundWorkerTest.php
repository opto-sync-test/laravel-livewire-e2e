<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\ImmutableSyncBatch;
use App\Jobs\ScheduleOptoSyncMultiplex;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZedPkg\OptoSync\Client;

final class BackgroundWorkerTest extends TestCase
{
    public function test_both_background_lanes_retry_the_exact_official_php_batch(): void
    {
        $client = new Client('https://sync.example.test', null, 'laravel-worker');
        $client->enqueueUpsert('documents', 'laravel-1', ['title' => 'offline Livewire edit']);
        $batch = ImmutableSyncBatch::fromClient($client);
        $scheduler = $this->app->make(ScheduleOptoSyncMultiplex::class);
        $jobs = $scheduler->jobs($batch, 'https://sync.example.test');

        self::assertSame($batch, $jobs['upload']->batch);
        self::assertSame($batch, $jobs['realtime']->batch);
        self::assertSame('opto-sync-upload', $jobs['upload']->queue);
        self::assertSame('opto-sync-realtime', $jobs['realtime']->queue);
        self::assertSame($batch->body, unserialize(serialize($jobs['upload']))->batch->body);
        self::assertSame($batch->body, unserialize(serialize($jobs['realtime']))->batch->body);

        Http::fake([
            'https://sync.example.test/api/sync/*' => Http::response(['accepted' => true], 200),
        ]);
        $http = $this->app->make(Factory::class);
        $jobs['upload']->handle($http);
        $jobs['realtime']->handle($http);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://sync.example.test/api/sync/upload'
            && $request->body() === $batch->body
            && $request->header('x-opto-sync-batch-sha256') === [$batch->sha256]
        );
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://sync.example.test/api/sync/realtime'
            && $request->body() === $batch->body
            && $request->header('x-opto-sync-batch-sha256') === [$batch->sha256]
        );
    }
}

