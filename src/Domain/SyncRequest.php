<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;

final class SyncRequest
{
    public function __construct(
        public readonly SourceStream $stream,
        public readonly SyncCursor $cursor,
        public readonly int $limit = 100,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('Synchronization limit must be positive.');
        }
    }
}
