<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\SyncRunId;

interface SyncRunRepository
{
    public function start(string $accountId, string $streamId): SyncRunId;
    public function complete(SyncRunId $runId, int $itemCount, bool $hasMore): void;
    public function fail(SyncRunId $runId, string $errorType, string $safeMessage): void;
}
