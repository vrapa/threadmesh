<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\SyncCursor;

interface SyncStateRepository
{
    public function load(string $accountId, string $streamId): ?SyncCursor;
    public function save(string $accountId, string $streamId, SyncCursor $cursor): void;
}
