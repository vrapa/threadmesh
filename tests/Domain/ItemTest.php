<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\ItemId;
use ThreadMesh\Domain\ItemStatus;
use ThreadMesh\Domain\ItemType;
use ThreadMesh\Domain\SourceReference;

final class ItemTest extends TestCase
{
    public function testItRepresentsANormalizedExternalItem(): void
    {
        $item = new Item(
            id: new ItemId('item-1'),
            source: new SourceReference('imap', 'personal', '42'),
            type: ItemType::Email,
            title: 'Production deployment failed',
            body: 'The deployment needs attention.',
            author: new Actor('ops@example.com', 'Operations', 'ops@example.com'),
            status: ItemStatus::New,
            createdAt: new DateTimeImmutable('2026-07-14T10:00:00+02:00'),
            labels: ['production', 'urgent'],
        );
        self::assertSame(ItemType::Email, $item->type);
        self::assertSame('imap:personal:42', $item->source->key());
        self::assertSame(['production', 'urgent'], $item->labels);
    }

    public function testItRejectsAnEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Item(
            id: new ItemId('item-1'),
            source: new SourceReference('imap', 'personal', '42'),
            type: ItemType::Email,
            title: ' ',
            body: null,
            author: new Actor('sender', 'Sender'),
            status: ItemStatus::New,
            createdAt: new DateTimeImmutable(),
        );
    }
}
