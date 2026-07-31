<?php

declare(strict_types=1);

namespace ThreadMesh\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use ThreadMesh\Contract\AccountRepository;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Contract\HistoricalSourceConnector;
use ThreadMesh\Contract\ItemRepository;
use ThreadMesh\Contract\SyncRunRepository;
use ThreadMesh\Contract\TransactionManager;
use ThreadMesh\Domain\HistoricalSyncRequest;
use ThreadMesh\Domain\HistoricalSyncResult;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Exception\ThreadMeshException;

final class BackfillStream
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly ConnectorProvider $connectors,
        private readonly ItemRepository $items,
        private readonly SyncRunRepository $runs,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function execute(
        string $accountId,
        string $streamId,
        DateTimeImmutable $since,
        ?SyncCursor $cursor = null,
        int $limit = 100,
    ): HistoricalSyncResult {
        $account = $this->accounts->get($accountId);
        if (!$account->enabled) {
            throw new InvalidArgumentException('Disabled accounts cannot be backfilled.');
        }

        $connector = $this->connectors->forAccount($account);
        if (!$connector instanceof HistoricalSourceConnector) {
            throw new InvalidArgumentException('This source connector does not support historical synchronization.');
        }

        $stream = null;
        foreach ($connector->streams() as $available) {
            if ($available->id === $streamId) {
                $stream = $available;
                break;
            }
        }
        if (!$stream instanceof SourceStream) {
            throw new InvalidArgumentException(sprintf('Unknown source stream "%s".', $streamId));
        }

        $runId = $this->runs->start($accountId, $streamId);
        try {
            $result = $connector->synchronizeHistory(new HistoricalSyncRequest($stream, $since, $cursor, $limit));
            $this->transactions->run(function () use ($result): void {
                foreach ($result->items as $item) {
                    $this->items->upsert($item);
                }
            });
            $this->runs->complete($runId, count($result->items), $result->hasMore);
            return $result;
        } catch (Throwable $error) {
            $message = $error instanceof ThreadMeshException ? $error->getMessage() : 'Unexpected historical synchronization failure.';
            $this->runs->fail($runId, $error::class, $message);
            throw $error;
        }
    }
}
