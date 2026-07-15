<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Exception\CursorInvalidException;
use ThreadMesh\Imap\Cursor\ImapCursor;
use ThreadMesh\Imap\Cursor\ImapCursorCodec;

final class ImapCursorCodecTest extends TestCase
{
    public function testCursorRoundTripsWithVersionAndUidValidity(): void
    {
        $codec = new ImapCursorCodec();
        $decoded = $codec->decode($codec->encode(new ImapCursor(1234, 42)));
        self::assertSame(1234, $decoded->uidValidity);
        self::assertSame(42, $decoded->lastUid);
    }

    public function testMalformedCursorIsRejected(): void
    {
        $this->expectException(CursorInvalidException::class);
        (new ImapCursorCodec())->decode(new SyncCursor('not-json'));
    }
}
