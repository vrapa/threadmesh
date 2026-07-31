<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

final class HistoricalSyncResult
{
    /** @param list<Item> $items */
    public function __construct(
        public readonly array $items,
        public readonly SyncCursor $nextCursor,
        public readonly bool $hasMore = false,
    ) {
    }
}
