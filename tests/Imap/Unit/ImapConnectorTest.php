<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncRequest;
use ThreadMesh\Exception\TemporarySourceException;
use ThreadMesh\Imap\Client\AttachmentData;
use ThreadMesh\Imap\Client\EmailAddress;
use ThreadMesh\Imap\Client\FolderStatus;
use ThreadMesh\Imap\Client\ImapGateway;
use ThreadMesh\Imap\Client\MessageData;
use ThreadMesh\Imap\Cursor\ImapCursorCodec;
use ThreadMesh\Imap\ImapConfiguration;
use ThreadMesh\Imap\ImapConnector;

final class ImapConnectorTest extends TestCase
{
    public function testInitializationStartsAtCurrentHighestUidWithoutHistory(): void
    {
        $gateway = new FakeGateway();
        $connector = $this->connector($gateway);
        $cursor = (new ImapCursorCodec())->decode($connector->initialize(new SourceStream('INBOX', 'Inbox')));
        self::assertSame(987, $cursor->uidValidity);
        self::assertSame(40, $cursor->lastUid);
        self::assertSame([], $gateway->requestedAfter);
    }

    public function testSynchronizationIsPagedAndNormalizesMail(): void
    {
        $gateway = new FakeGateway();
        $gateway->messages = [
            $this->message(41, 'First'),
            $this->message(42, 'Second'),
            $this->message(43, 'Third'),
        ];
        $connector = $this->connector($gateway);
        $initial = $connector->initialize(new SourceStream('INBOX', 'Inbox'));

        $result = $connector->synchronize(new SyncRequest(new SourceStream('INBOX', 'Inbox'), $initial, 2));

        self::assertCount(2, $result->items);
        self::assertTrue($result->hasMore);
        self::assertSame('imap:mail-1:INBOX:41', $result->items[0]->source->key());
        self::assertSame('sender@example.com', $result->items[0]->author->address);
        self::assertSame('Body', $result->items[0]->content->text);
        self::assertSame('report.pdf', $result->items[0]->attachments[0]->name);
        self::assertSame(42, (new ImapCursorCodec())->decode($result->nextCursor)->lastUid);
        self::assertSame([40, 3], $gateway->requestedAfter);
    }

    public function testRepeatedSyncFromSameCursorProducesSameStableReferences(): void
    {
        $gateway = new FakeGateway();
        $gateway->messages = [$this->message(41, 'First')];
        $connector = $this->connector($gateway);
        $initial = $connector->initialize(new SourceStream('INBOX', 'Inbox'));

        $first = $connector->synchronize(new SyncRequest(new SourceStream('INBOX', 'Inbox'), $initial, 100));
        $second = $connector->synchronize(new SyncRequest(new SourceStream('INBOX', 'Inbox'), $initial, 100));

        self::assertSame($first->items[0]->source->key(), $second->items[0]->source->key());
        self::assertSame((string) $first->items[0]->id, (string) $second->items[0]->id);
    }

    public function testUidValidityChangeStopsStreamAndRequiresReinitialization(): void
    {
        $gateway = new FakeGateway();
        $connector = $this->connector($gateway);
        $initial = $connector->initialize(new SourceStream('INBOX', 'Inbox'));
        $gateway->uidValidity = 988;

        $this->expectException(TemporarySourceException::class);
        $this->expectExceptionMessage('Reinitialize');
        $connector->synchronize(new SyncRequest(new SourceStream('INBOX', 'Inbox'), $initial));
    }

    public function testStreamsIncludeMultipleFolders(): void
    {
        $gateway = new FakeGateway();
        $gateway->folderList = [
            new FolderStatus('INBOX', 'Inbox', 987, 40),
            new FolderStatus('Archive', 'Archive', 444, 100),
        ];
        self::assertSame(['INBOX', 'Archive'], array_map(
            static fn ($stream): string => $stream->id,
            $this->connector($gateway)->streams(),
        ));
    }

    private function connector(FakeGateway $gateway): ImapConnector
    {
        return new ImapConnector(
            new ImapConfiguration('mail-1', 'imap.example.com', 993, 'ssl', true, 'user@example.com', 'app-password'),
            $gateway,
        );
    }

    private function message(int $uid, string $subject): MessageData
    {
        return new MessageData(
            $uid,
            sprintf('<message-%d@example.com>', $uid),
            $subject,
            [new EmailAddress('Sender@Example.com', 'Sender')],
            [new EmailAddress('recipient@example.com', 'Recipient')],
            [],
            ['<thread@example.com>'],
            'Body',
            '<p>Body</p>',
            new DateTimeImmutable('2026-07-15T10:00:00+02:00'),
            [new AttachmentData('2', 'report.pdf', 'application/pdf', 1234)],
            ['x-test' => 'fixture'],
        );
    }
}

/** @internal */
final class FakeGateway implements ImapGateway
{
    public int $uidValidity = 987;
    /** @var list<FolderStatus> */
    public array $folderList = [];
    /** @var list<MessageData> */
    public array $messages = [];
    /** @var list<int> */
    public array $requestedAfter = [];

    public function connect(ImapConfiguration $configuration): void {}
    public function folders(): array
    {
        return $this->folderList !== [] ? $this->folderList : [new FolderStatus('INBOX', 'Inbox', $this->uidValidity, 40)];
    }
    public function status(string $folderId): FolderStatus
    {
        return new FolderStatus($folderId, $folderId, $this->uidValidity, 40);
    }
    public function messagesAfter(string $folderId, int $lastUid, int $limit): array
    {
        $this->requestedAfter = [$lastUid, $limit];
        return array_slice(array_values(array_filter(
            $this->messages,
            static fn (MessageData $message): bool => $message->uid > $lastUid,
        )), 0, $limit);
    }
    public function downloadAttachment(string $folderId, int $uid, string $partId)
    {
        $stream = tmpfile();
        if ($stream === false) {
            throw new \RuntimeException('Could not create fixture stream.');
        }
        fwrite($stream, 'binary');
        rewind($stream);
        return $stream;
    }
}
