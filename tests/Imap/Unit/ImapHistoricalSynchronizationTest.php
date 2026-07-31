<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\HistoricalSyncRequest;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Imap\Client\EmailAddress;
use ThreadMesh\Imap\Client\MessageData;
use ThreadMesh\Imap\Cursor\ImapCursorCodec;
use ThreadMesh\Imap\ImapConfiguration;
use ThreadMesh\Imap\ImapConnector;

final class ImapHistoricalSynchronizationTest extends TestCase
{
    public function testHistoricalSynchronizationIsPagedWithoutChangingTheLiveCursor(): void
    {
        $gateway = new FakeGateway();
        $gateway->messages = [
            $this->message(10, 'Too old', '2026-07-20T10:00:00+02:00'),
            $this->message(11, 'First historical', '2026-07-25T10:00:00+02:00'),
            $this->message(12, 'Second historical', '2026-07-26T10:00:00+02:00'),
        ];
        $connector = new ImapConnector(
            new ImapConfiguration('mail-1', 'imap.example.com', 993, 'ssl', true, 'user@example.com', 'app-password'),
            $gateway,
        );
        $stream = new SourceStream('INBOX', 'Inbox');
        $liveCursor = $connector->initialize($stream);
        $since = new DateTimeImmutable('2026-07-24T00:00:00+02:00');

        $first = $connector->synchronizeHistory(new HistoricalSyncRequest($stream, $since, limit: 2));
        $second = $connector->synchronizeHistory(new HistoricalSyncRequest($stream, $since, $first->nextCursor, 2));

        self::assertTrue($first->hasMore);
        self::assertCount(1, $first->items);
        self::assertSame('First historical', $first->items[0]->title);
        self::assertFalse($second->hasMore);
        self::assertCount(1, $second->items);
        self::assertSame('Second historical', $second->items[0]->title);
        self::assertSame(40, (new ImapCursorCodec())->decode($liveCursor)->lastUid);
        self::assertSame([0, 3], $gateway->requestedSinceAfter[0]);
        self::assertSame([11, 3], $gateway->requestedSinceAfter[1]);
    }

    private function message(int $uid, string $subject, string $date): MessageData
    {
        return new MessageData(
            $uid,
            sprintf('<message-%d@example.com>', $uid),
            $subject,
            [new EmailAddress('sender@example.com', 'Sender')],
            [new EmailAddress('recipient@example.com', 'Recipient')],
            [],
            [],
            'Body',
            '<p>Body</p>',
            new DateTimeImmutable($date),
        );
    }
}
