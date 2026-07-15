<?php

declare(strict_types=1);

namespace ThreadMesh\Application;

use Throwable;
use ThreadMesh\Contract\AccountRepository;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Contract\ItemRepository;
use ThreadMesh\Contract\SyncRunRepository;
use ThreadMesh\Contract\SyncStateRepository;
use ThreadMesh\Contract\TransactionManager;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncRequest;
use ThreadMesh\Domain\SyncResult;
use ThreadMesh\Exception\CursorNotInitializedException;
use ThreadMesh\Exception\ThreadMeshException;

final class SynchronizeStream
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly ConnectorProvider $connectors,
        private readonly ItemRepository $items,
        private readonly SyncStateRepository $states,
        private readonly SyncRunRepository $runs,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function execute(string $accountId, SourceStream $stream, int $limit = 100): SyncResult
    {
        $account = $this->accounts->get($accountId);
        $cursor = $this->states->load($accountId, $stream->id);
        if ($cursor === null) {
            throw new CursorNotInitializedException('The source stream must be initialized before synchronization.');
        }
        $runId = $this->runs->start($accountId, $stream->id);
        try {
            $result = $this->connectors->forAccount($account)->synchronize(new SyncRequest($stream, $cursor, $limit));
            $this->transactions->run(function () use ($accountId, $stream, $result): void {
                foreach ($result->items as $item) {
                    $this->items->upsert($item);
                }
                $this->states->save($accountId, $stream->id, $result->nextCursor);
            });
            $this->runs->complete($runId, count($result->items), $result->hasMore);
            return $result;
        } catch (Throwable $error) {
            $message = $error instanceof ThreadMeshException ? $error->getMessage() : 'Unexpected synchronization failure.';
            $this->runs->fail($runId, $error::class, $message);
            throw $error;
        }
    }
}
