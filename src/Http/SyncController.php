<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SyncController
{
    public function __invoke(Request $request, string $lane): JsonResponse
    {
        if (!in_array($lane, ['upload', 'realtime'], true)) {
            throw new NotFoundHttpException('unknown OptoSync lane');
        }

        $body = $request->getContent();
        $expectedHash = $request->header('x-opto-sync-batch-sha256');
        if (!is_string($expectedHash) || !hash_equals($expectedHash, hash('sha256', $body))) {
            throw new ConflictHttpException('batch hash does not match the immutable request body');
        }
        try {
            $batch = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BadRequestHttpException('sync batch is not valid JSON', $error);
        }
        if (!is_array($batch) || !array_is_list($batch) || $batch === []) {
            throw new BadRequestHttpException('sync batch must be a non-empty JSON array');
        }

        $accepted = [];
        foreach ($batch as $mutation) {
            if (!is_array($mutation) || !is_string($mutation['id'] ?? null) || $mutation['id'] === '') {
                throw new BadRequestHttpException('every mutation must have a non-empty string id');
            }
            $accepted[] = $mutation['id'];
        }

        return response()->json([
            'lane' => $lane,
            'sha256' => $expectedHash,
            'accepted' => $accepted,
        ]);
    }
}
