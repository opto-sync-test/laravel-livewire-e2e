<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SyncEndpointTest extends TestCase
{
    public function test_sync_endpoint_accepts_only_a_hash_bound_immutable_batch(): void
    {
        $batch = [
            ['id' => 'web-1', 'title' => 'offline one'],
            ['id' => 'web-2', 'title' => 'offline two'],
        ];
        $body = json_encode($batch, JSON_THROW_ON_ERROR);

        $this->withHeaders(['x-opto-sync-batch-sha256' => hash('sha256', $body)])
            ->postJson('/api/sync/upload', $batch)
            ->assertOk()
            ->assertJson([
                'lane' => 'upload',
                'accepted' => ['web-1', 'web-2'],
            ]);

        $this->withHeaders(['x-opto-sync-batch-sha256' => str_repeat('0', 64)])
            ->postJson('/api/sync/realtime', $batch)
            ->assertConflict();
    }
}

