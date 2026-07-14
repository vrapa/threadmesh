<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\SyncResult;

final class SyncResultTest extends TestCase
{
    public function testPaginationRequiresACursor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SyncResult([], null, true);
    }

    public function testAnEmptyFinalResultIsValid(): void
    {
        $result = new SyncResult([], null);
        self::assertSame([], $result->items);
        self::assertFalse($result->hasMore);
    }
}
