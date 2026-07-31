<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\HistoricalSyncRequest;
use ThreadMesh\Domain\HistoricalSyncResult;

interface HistoricalSourceConnector extends SourceConnector
{
    public function synchronizeHistory(HistoricalSyncRequest $request): HistoricalSyncResult;
}
