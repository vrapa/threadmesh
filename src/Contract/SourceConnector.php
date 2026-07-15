<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\ConnectionTestResult;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Domain\SyncRequest;
use ThreadMesh\Domain\SyncResult;

interface SourceConnector
{
    public function key(): string;
    public function testConnection(): ConnectionTestResult;
    /** @return list<SourceStream> */
    public function streams(): array;
    /** Establishes a high-water mark without importing historical records. */
    public function initialize(SourceStream $stream): SyncCursor;
    public function synchronize(SyncRequest $request): SyncResult;
}
