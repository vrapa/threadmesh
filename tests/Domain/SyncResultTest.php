<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Domain;

use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Domain\SyncResult;

final class SyncResultTest extends TestCase
{
    public function testAnEmptyPaginatedResultHasANextCursor(): void
    {
        $cursor = new SyncCursor('next');
        $result = new SyncResult([], $cursor, true);
        self::assertSame($cursor, $result->nextCursor);
        self::assertTrue($result->hasMore);
    }

    public function testAnEmptyFinalResultIsValid(): void
    {
        $result = new SyncResult([], new SyncCursor('final'));
        self::assertSame([], $result->items);
        self::assertFalse($result->hasMore);
    }
}
