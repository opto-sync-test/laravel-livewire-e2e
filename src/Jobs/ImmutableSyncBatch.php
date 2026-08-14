<?php

declare(strict_types=1);

namespace App\Jobs;

use JsonSerializable;
use RuntimeException;
use ZedPkg\OptoSync\Client;

final readonly class ImmutableSyncBatch implements JsonSerializable
{
    public string $sha256;

    public function __construct(
        public array $request,
        public string $body,
    ) {
        if (($request['mutations'] ?? []) === []) {
            throw new RuntimeException('a background sync batch must contain at least one mutation');
        }
        $this->sha256 = hash('sha256', $body);
    }

    public static function fromClient(Client $client, int $limit = 100): self
    {
        $request = $client->buildPushRequest($limit);
        $body = json_encode(
            $request,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return new self($request, $body);
    }

    public function jsonSerialize(): array
    {
        return [
            'request' => $this->request,
            'body' => $this->body,
            'sha256' => $this->sha256,
        ];
    }
}

