<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class HistoricalSyncRequest
{
    public function __construct(
        public readonly SourceStream $stream,
        public readonly DateTimeImmutable $since,
        public readonly ?SyncCursor $cursor = null,
        public readonly int $limit = 100,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('Historical synchronization limit must be positive.');
        }
    }
}
