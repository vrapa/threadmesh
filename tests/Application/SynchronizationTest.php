<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ThreadMesh\Application\InitializeAccount;
use ThreadMesh\Application\SynchronizeStream;
use ThreadMesh\Contract\AccountRepository;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Contract\ItemRepository;
use ThreadMesh\Contract\SourceConnector;
use ThreadMesh\Contract\SyncRunRepository;
use ThreadMesh\Contract\SyncStateRepository;
use ThreadMesh\Contract\TransactionManager;
use ThreadMesh\Domain\Account;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\ConnectionTestResult;
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
use ThreadMesh\Exception\CursorInvalidException;
use ThreadMesh\Exception\CursorNotInitializedException;
use ThreadMesh\Exception\TemporarySourceException;

final class SynchronizationTest extends TestCase
{
    public function testInitializationStoresTheConnectorHighWaterMark(): void
    {
        $fixture = new SynchronizationFixture();
        $service = new InitializeAccount($fixture, $fixture, $fixture, $fixture);

        $result = $service->execute('account-1', ['INBOX']);

        self::assertSame('cursor:42', $result['INBOX']->value);
        self::assertSame('cursor:42', $fixture->cursors['account-1:INBOX']->value);
    }

    public function testItemsAndCursorAreCommittedTogetherAndUpsertIsIdempotent(): void
    {
        $fixture = new SynchronizationFixture();
        $fixture->cursors['account-1:INBOX'] = new SyncCursor('cursor:42');
        $service = new SynchronizeStream($fixture, $fixture, $fixture, $fixture, $fixture, $fixture);

        $service->execute('account-1', new SourceStream('INBOX', 'Inbox'));
        $service->execute('account-1', new SourceStream('INBOX', 'Inbox'));

        self::assertCount(1, $fixture->items);
        self::assertSame('cursor:43', $fixture->cursors['account-1:INBOX']->value);
        self::assertSame(2, $fixture->commits);
    }

    public function testCursorIsNotAdvancedWhenAnItemCannotBeStored(): void
    {
        $fixture = new SynchronizationFixture();
        $fixture->cursors['account-1:INBOX'] = new SyncCursor('cursor:42');
        $fixture->failUpsert = true;
        $service = new SynchronizeStream($fixture, $fixture, $fixture, $fixture, $fixture, $fixture);

        try {
            $service->execute('account-1', new SourceStream('INBOX', 'Inbox'));
            self::fail('The failing repository must abort synchronization.');
        } catch (RuntimeException) {
            self::assertSame('cursor:42', $fixture->cursors['account-1:INBOX']->value);
            self::assertSame('RuntimeException', $fixture->failedType);
        }
    }

    public function testConnectorFailureIsRecordedWithoutMovingTheCursor(): void
    {
        $fixture = new SynchronizationFixture();
        $fixture->cursors['account-1:INBOX'] = new SyncCursor('cursor:42');
        $fixture->failConnector = true;
        $service = new SynchronizeStream($fixture, $fixture, $fixture, $fixture, $fixture, $fixture);

        $this->expectException(TemporarySourceException::class);
        try {
            $service->execute('account-1', new SourceStream('INBOX', 'Inbox'));
        } finally {
            self::assertSame('cursor:42', $fixture->cursors['account-1:INBOX']->value);
            self::assertSame(TemporarySourceException::class, $fixture->failedType);
        }
    }

    public function testInvalidCursorIsReinitializedAndRetried(): void
    {
        $fixture = new SynchronizationFixture();
        $fixture->cursors['account-1:INBOX'] = new SyncCursor('cursor:42');
        $fixture->invalidCursorOnce = true;
        $fixture->initializedCursor = new SyncCursor('cursor:99');
        $service = new SynchronizeStream($fixture, $fixture, $fixture, $fixture, $fixture, $fixture);

        $result = $service->execute('account-1', new SourceStream('INBOX', 'Inbox'));

        self::assertCount(1, $result->items);
        self::assertSame('cursor:100', $fixture->cursors['account-1:INBOX']->value);
        self::assertSame(1, $fixture->initializeCalls);
        self::assertSame(['cursor:42', 'cursor:99'], $fixture->synchronizeCursorValues);
    }

    public function testSynchronizationRequiresInitialization(): void
    {
        $fixture = new SynchronizationFixture();
        $service = new SynchronizeStream($fixture, $fixture, $fixture, $fixture, $fixture, $fixture);
        $this->expectException(CursorNotInitializedException::class);
        $service->execute('account-1', new SourceStream('INBOX', 'Inbox'));
    }
}

/** @internal */
final class SynchronizationFixture implements AccountRepository, ConnectorProvider, SourceConnector, ItemRepository, SyncStateRepository, SyncRunRepository, TransactionManager
{
    /** @var array<string, SyncCursor> */
    public array $cursors = [];
    /** @var array<string, Item> */
    public array $items = [];
    public bool $failUpsert = false;
    public bool $failConnector = false;
    public bool $invalidCursorOnce = false;
    public SyncCursor $initializedCursor;
    public int $initializeCalls = 0;
    /** @var list<string> */
    public array $synchronizeCursorValues = [];
    public int $commits = 0;
    public ?string $failedType = null;

    public function __construct()
    {
        $this->initializedCursor = new SyncCursor('cursor:42');
    }

    public function get(string $accountId): Account { return new Account($accountId, 'imap', 'Mail'); }
    public function forAccount(Account $account): SourceConnector { return $this; }
    public function key(): string { return 'imap'; }
    public function testConnection(): ConnectionTestResult { return ConnectionTestResult::success(); }
    public function streams(): array { return [new SourceStream('INBOX', 'Inbox')]; }
    public function initialize(SourceStream $stream): SyncCursor { $this->initializeCalls++; return $this->initializedCursor; }
    public function synchronize(SyncRequest $request): SyncResult
    {
        $this->synchronizeCursorValues[] = $request->cursor->value;
        if ($this->invalidCursorOnce) {
            $this->invalidCursorOnce = false;
            throw new CursorInvalidException('The stored cursor is invalid.');
        }
        if ($this->failConnector) { throw new TemporarySourceException('Server is temporarily unavailable.'); }
        $item = new Item(new ItemId('item-43'), new SourceReference('imap', 'account-1', 'INBOX:43'), ItemType::Email, 'New mail', new ItemContent('Body'), new Actor('sender@example.com', 'Sender'), ItemStatus::New, new DateTimeImmutable('2026-07-15T10:00:00+02:00'));
        $nextCursor = $request->cursor->value === 'cursor:99' ? new SyncCursor('cursor:100') : new SyncCursor('cursor:43');
        return new SyncResult([$item], $nextCursor);
    }
    public function upsert(Item $item): void
    {
        if ($this->failUpsert) { throw new RuntimeException('Storage failed.'); }
        $this->items[$item->source->key()] = $item;
    }
    public function load(string $accountId, string $streamId): ?SyncCursor { return $this->cursors[$accountId . ':' . $streamId] ?? null; }
    public function save(string $accountId, string $streamId, SyncCursor $cursor): void { $this->cursors[$accountId . ':' . $streamId] = $cursor; }
    public function start(string $accountId, string $streamId): SyncRunId { return new SyncRunId('run-1'); }
    public function complete(SyncRunId $runId, int $itemCount, bool $hasMore): void {}
    public function fail(SyncRunId $runId, string $errorType, string $safeMessage): void { $this->failedType = $errorType; }
    public function run(callable $operation): mixed
    {
        $before = $this->cursors;
        try { $result = $operation(); $this->commits++; return $result; }
        catch (\Throwable $error) { $this->cursors = $before; throw $error; }
    }
}
