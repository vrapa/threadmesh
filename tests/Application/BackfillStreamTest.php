<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Application\BackfillStream;
use ThreadMesh\Contract\AccountRepository;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Contract\HistoricalSourceConnector;
use ThreadMesh\Contract\ItemRepository;
use ThreadMesh\Contract\SyncRunRepository;
use ThreadMesh\Contract\TransactionManager;
use ThreadMesh\Domain\Account;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\ConnectionTestResult;
use ThreadMesh\Domain\HistoricalSyncRequest;
use ThreadMesh\Domain\HistoricalSyncResult;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\ItemContent;
use ThreadMesh\Domain\ItemId;
use ThreadMesh\Domain\ItemStatus;
use ThreadMesh\Domain\ItemType;
use ThreadMesh\Domain\SourceReference;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Domain\SyncRequest;
use ThreadMesh\Domain\SyncResult;
use ThreadMesh\Domain\SyncRunId;

final class BackfillStreamTest extends TestCase
{
    public function testBackfillStoresItemsTransactionallyAndReturnsAnIndependentCursor(): void
    {
        $fixture = new BackfillFixture();
        $service = new BackfillStream($fixture, $fixture, $fixture, $fixture, $fixture);
        $since = new DateTimeImmutable('2026-07-24T00:00:00+00:00');

        $result = $service->execute('work', 'INBOX', $since, limit: 25);

        self::assertCount(1, $fixture->items);
        self::assertSame('history:11', $result->nextCursor->value);
        self::assertTrue($result->hasMore);
        self::assertInstanceOf(HistoricalSyncRequest::class, $fixture->request);
        self::assertSame($since, $fixture->request->since);
        self::assertSame(25, $fixture->request->limit);
        self::assertSame(1, $fixture->completedItems);
        self::assertSame(1, $fixture->commits);
    }
}

/** @internal */
final class BackfillFixture implements AccountRepository, ConnectorProvider, HistoricalSourceConnector, ItemRepository, SyncRunRepository, TransactionManager
{
    /** @var array<string, Item> */
    public array $items = [];
    public ?HistoricalSyncRequest $request = null;
    public int $completedItems = 0;
    public int $commits = 0;

    public function get(string $accountId): Account
    {
        return new Account($accountId, 'imap', 'Work mail');
    }

    public function forAccount(Account $account): HistoricalSourceConnector
    {
        return $this;
    }

    public function key(): string
    {
        return 'imap';
    }

    public function testConnection(): ConnectionTestResult
    {
        return ConnectionTestResult::success();
    }

    public function streams(): array
    {
        return [new SourceStream('INBOX', 'Inbox')];
    }

    public function initialize(SourceStream $stream): SyncCursor
    {
        return new SyncCursor('live:50');
    }

    public function synchronize(SyncRequest $request): SyncResult
    {
        return new SyncResult([], $request->cursor);
    }

    public function synchronizeHistory(HistoricalSyncRequest $request): HistoricalSyncResult
    {
        $this->request = $request;
        $item = new Item(
            new ItemId('historical-email'),
            new SourceReference('imap', 'work', 'INBOX:11'),
            ItemType::Email,
            'Historical mail',
            new ItemContent('Body'),
            new Actor('sender@example.test', 'Sender', 'sender@example.test'),
            ItemStatus::New,
            new DateTimeImmutable('2026-07-25T10:00:00+00:00'),
        );
        return new HistoricalSyncResult([$item], new SyncCursor('history:11'), true);
    }

    public function upsert(Item $item): void
    {
        $this->items[$item->source->key()] = $item;
    }

    public function start(string $accountId, string $streamId): SyncRunId
    {
        return new SyncRunId('backfill-run');
    }

    public function complete(SyncRunId $runId, int $itemCount, bool $hasMore): void
    {
        $this->completedItems = $itemCount;
    }

    public function fail(SyncRunId $runId, string $errorType, string $safeMessage): void
    {
    }

    public function run(callable $operation): mixed
    {
        $result = $operation();
        $this->commits++;
        return $result;
    }
}
