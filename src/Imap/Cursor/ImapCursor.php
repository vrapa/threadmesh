<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Cursor;

use InvalidArgumentException;

final class ImapCursor
{
    public const FORMAT_VERSION = 1;

    public function __construct(
        public readonly int $uidValidity,
        public readonly int $lastUid,
    ) {
        if ($uidValidity < 1 || $lastUid < 0) {
            throw new InvalidArgumentException('IMAP cursor values are invalid.');
        }
    }
}
