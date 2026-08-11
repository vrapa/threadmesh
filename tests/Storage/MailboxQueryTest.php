<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Storage;

use DateTimeImmutable;
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

final class MailboxQueryTest extends TestCase
{
    private string $path;
    private SqliteConnection $connection;
    private SqliteStore $store;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/threadmesh-mailbox-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->connection = new SqliteConnection($this->path);
        $this->store = new SqliteStore($this->connection, new SecretCipher(random_bytes(32)));
        $this->store->configureAccount('work', 'Work mail', [
            'host' => 'imap.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'me@example.test',
        ], 'app-password');
        $this->store->upsert($this->email('old', 'Old message', '2026-07-20T10:00:00+00:00'));
        $this->store->upsert($this->email('normal', 'Normal message', '2026-07-25T10:00:00+00:00'));
        $this->store->upsert($this->email('critical', 'Critical message', '2026-07-26T10:00:00+00:00'));
        $this->store->storeAssessment(
            'normal',
            'normal',
            'project',
            'Routine update.',
            false,
            null,
            null,
            null,
            'Read when convenient.',
            'No urgent request.',
        );
        $this->store->storeAssessment(
            'critical',
            'critical',
            'security',
            'Account access requires review.',
            true,
            null,
            null,
            null,
            'Review account activity.',
            'The message reports an account security event.',
        );
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

    public function testMailboxReturnsAssessedAndUnassessedOverviewWithinTimeRange(): void
    {
        $emails = $this->store->mailboxEmails(new DateTimeImmutable('2026-07-24T00:00:00+00:00'));

        self::assertCount(2, $emails);
        self::assertSame(['critical', 'normal'], array_column($emails, 'id'));
        self::assertTrue($emails[0]['assessed']);
        self::assertTrue($emails[0]['requires_action']);
        self::assertIsArray($emails[0]['author']);
        self::assertSame('sender@example.test', $emails[0]['author']['address']);
        self::assertIsArray($emails[0]['recipients']);
        self::assertIsArray($emails[0]['recipients'][0]);
        self::assertSame('recipient@example.test', $emails[0]['recipients'][0]['address']);
        self::assertArrayNotHasKey('text_body', $emails[0]);
        self::assertArrayNotHasKey('html_body', $emails[0]);
    }

    public function testMailboxFiltersImportanceAndAssessmentState(): void
    {
        $critical = $this->store->mailboxEmails(
            new DateTimeImmutable('2026-07-24T00:00:00+00:00'),
            importance: ['critical'],
            assessed: true,
            requiresAction: true,
        );
        $unassessed = $this->store->mailboxEmails(
            new DateTimeImmutable('2026-07-24T00:00:00+00:00'),
            assessed: false,
        );

        self::assertSame(['critical'], array_column($critical, 'id'));
        self::assertSame([], $unassessed);
    }

    private function email(string $id, string $title, string $date): Item
    {
        return new Item(
            new ItemId($id),
            new SourceReference('imap', 'work', 'INBOX:' . $id),
            ItemType::Email,
            $title,
            new ItemContent('Private body', '<p>Private body</p>'),
            new Actor('sender@example.test', 'Sender', 'sender@example.test'),
            ItemStatus::New,
            new DateTimeImmutable($date),
            recipients: [new Actor('recipient@example.test', 'Recipient', 'recipient@example.test')],
        );
    }
}
