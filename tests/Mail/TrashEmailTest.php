<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Mail;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\ItemContent;
use ThreadMesh\Domain\ItemId;
use ThreadMesh\Domain\ItemStatus;
use ThreadMesh\Domain\ItemType;
use ThreadMesh\Domain\SourceReference;
use ThreadMesh\Imap\ImapConfiguration;
use ThreadMesh\Imap\TrashMover;
use ThreadMesh\Mail\TrashEmail;
use ThreadMesh\Storage\SecretCipher;
use ThreadMesh\Storage\SqliteConnection;
use ThreadMesh\Storage\SqliteConnectorProvider;
use ThreadMesh\Storage\SqliteStore;

final class TrashEmailTest extends TestCase
{
    private string $path;
    private SqliteStore $store;
    private FakeTrashMover $mover;
    private TrashEmail $service;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/threadmesh-trash-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->store = new SqliteStore(new SqliteConnection($this->path), new SecretCipher(random_bytes(32)));
        foreach (['one', 'two'] as $account) {
            $this->store->configureAccount($account, $account, [
                'host' => 'imap.example.test', 'port' => 993, 'encryption' => 'ssl',
                'username' => $account . '@example.test', 'trashFolder' => 'Trash',
            ], 'test-password');
            $this->store->upsert($this->email($account));
        }
        $this->mover = new FakeTrashMover();
        $this->service = new TrashEmail($this->store, new SqliteConnectorProvider($this->store), $this->mover);
    }

    protected function tearDown(): void
    {
        unset($this->service, $this->store);
        foreach ([$this->path, $this->path . '-wal', $this->path . '-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMoveIsAccountScopedIdempotentAndSurvivesReimport(): void
    {
        $this->store->storeAssessment('one', 'high', 'project', 'Important.', true, null, null, null, 'Review.', 'Test.');
        self::assertCount(1, $this->store->alerts());
        $this->service->execute('one');
        $this->service->execute('one');
        self::assertSame(['one'], $this->mover->accounts);
        self::assertSame('done', $this->store->trashOperation('one')['status'] ?? null);
        self::assertSame(['two'], array_column($this->store->mailboxEmails(new DateTimeImmutable('2026-01-01')), 'id'));
        self::assertSame(['two'], array_column($this->store->unassessedEmails(1), 'id'));
        self::assertSame([], $this->store->alerts());
        $this->store->upsert($this->email('one'));
        self::assertSame(['two'], array_column($this->store->mailboxEmails(new DateTimeImmutable('2026-01-01')), 'id'));
        $this->store->upsert($this->email('one', 'trash-one', 'Trash'));
        self::assertSame(['two'], array_column($this->store->mailboxEmails(new DateTimeImmutable('2026-01-01')), 'id'));
    }

    public function testUncertainMoveRemainsVisibleAndChecksSourceBeforeRetry(): void
    {
        $this->mover->fail = true;
        try {
            $this->service->execute('one');
            self::fail('Expected a failed move.');
        } catch (RuntimeException $error) {
            self::assertSame('Test move failed.', $error->getMessage());
        }
        self::assertSame('uncertain', $this->store->trashOperation('one')['status'] ?? null);
        self::assertEqualsCanonicalizing(['one', 'two'], array_column($this->store->mailboxEmails(new DateTimeImmutable('2026-01-01')), 'id'));

        $this->mover->present = false;
        try {
            $this->service->execute('one');
            self::fail('Expected the uncertain move to remain blocked.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Check IMAP Trash', $error->getMessage());
        }
        self::assertSame(1, count($this->mover->accounts));
        $this->mover->present = true;
        $this->mover->fail = false;
        $this->service->execute('one');
        self::assertSame('done', $this->store->trashOperation('one')['status'] ?? null);
        self::assertSame(['one', 'one'], $this->mover->accounts);
    }

    private function email(string $account, ?string $id = null, string $folder = 'INBOX'): Item
    {
        return new Item(
            new ItemId($id ?? $account), new SourceReference('imap', $account, $folder . ':1'), ItemType::Email,
            'Test subject', new ItemContent('Test body', null), new Actor('sender', 'Sender', 'sender@example.test'),
            ItemStatus::New, new DateTimeImmutable('2026-09-29T08:00:00+00:00'),
            metadata: ['folder' => $folder, 'uid' => 1, 'uidValidity' => 42],
        );
    }
}

final class FakeTrashMover implements TrashMover
{
    /** @var list<string> */
    public array $accounts = [];
    public bool $fail = false;
    public bool $present = true;

    public function sourcePresent(ImapConfiguration $configuration, array $email): bool
    {
        return $this->present;
    }

    public function move(ImapConfiguration $configuration, array $email, ?string $configuredTrashFolder): string
    {
        $this->accounts[] = $configuration->accountId;
        if ($this->fail) {
            throw new RuntimeException('Test move failed.');
        }
        return $configuredTrashFolder ?? 'Trash';
    }
}
