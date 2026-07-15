<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Client;

final class FolderStatus
{
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly int $uidValidity,
        public readonly int $highestUid,
    ) {
    }
}
