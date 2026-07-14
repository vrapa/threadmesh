<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;

final class SyncResult
{
    /** @param list<Item> $items */
    public function __construct(
        public readonly array $items,
        public readonly ?SyncCursor $nextCursor,
        public readonly bool $hasMore = false,
    ) {
        if ($hasMore && $nextCursor === null) {
            throw new InvalidArgumentException('A paginated sync result requires a next cursor.');
        }
    }
}
