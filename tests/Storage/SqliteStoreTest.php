<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Storage;

use DateTimeImmutable;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\ItemContent;
use ThreadMesh\Domain\ItemId;
use ThreadMesh\Domain\ItemStatus;
use ThreadMesh\Domain\ItemType;
use ThreadMesh\Domain\SourceReference;
use ThreadMesh\Storage\SecretCipher;
use ThreadMesh\Storage\SqliteConnection;
use ThreadMesh\Storage\SqliteStore;

final class SqliteStoreTest extends TestCase
{
    private string $path;
    private SqliteConnection $connection;
    private SqliteStore $store;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/threadmesh-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->connection = new SqliteConnection($this->path);
        $this->store = new SqliteStore($this->connection, new SecretCipher(random_bytes(32)));
        $this->store->configureAccount('work', 'Work mail', [
            'host' => 'imap.example.test', 'port' => 993, 'encryption' => 'ssl', 'username' => 'me@example.test',
        ], 'app-password');
    }

    protected function tearDown(): void
    {
        unset($this->store, $this->connection);
        gc_collect_cycles();
        foreach ([$this->path, $this->path . '-shm', $this->path . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testSecretIsEncryptedAndCanBeReadThroughStore(): void
    {
        $statement = $this->connection->pdo->query('SELECT encrypted_secret FROM accounts');
        self::assertInstanceOf(PDOStatement::class, $statement);
        $encrypted = $statement->fetchColumn();
        self::assertIsString($encrypted);
        self::assertStringNotContainsString('app-password', $encrypted);
        self::assertSame('app-password', $this->store->accountConnection('work')['secret']);
    }

    public function testEmailAssessmentAlertAndDraftRoundTrip(): void
    {
        $this->store->upsert($this->email('Initial subject'));
        $this->store->upsert($this->email('Updated subject'));
        $emails = $this->store->unassessedEmails();
        self::assertCount(1, $emails);
        self::assertSame('Updated subject', $emails[0]['title']);

        $this->store->storeAssessment('email-1', 'high', 'invoice', 'Invoice is due.', true, '2026-08-01', 1250.50, 'CZK', 'Review and pay after approval.', 'The message contains an invoice and due date.');
        self::assertSame([], $this->store->unassessedEmails());
        self::assertCount(1, $this->store->alerts());
        $draft = $this->store->createDraft('email-1', 'Re: Updated subject', 'Thank you.');
        self::assertSame('local', $draft['status']);
    }

    private function email(string $title): Item
    {
        return new Item(
            new ItemId('email-1'), new SourceReference('imap', 'work', 'INBOX:42'), ItemType::Email,
            $title, new ItemContent('Please see invoice attached.', '<p>Please see invoice attached.</p>'),
            new Actor('sender@example.test', 'Sender', 'sender@example.test'), ItemStatus::New,
            new DateTimeImmutable('2026-07-15T10:00:00+00:00'),
            recipients: [new Actor('me@example.test', 'Me', 'me@example.test')],
            threadReferences: ['message-42@example.test'], metadata: ['messageId' => 'message-42@example.test'],
        );
    }
}
