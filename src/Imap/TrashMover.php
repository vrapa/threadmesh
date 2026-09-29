<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

interface TrashMover
{
    /** @param array<string, mixed> $email */
    public function sourcePresent(ImapConfiguration $configuration, array $email): bool;

    /** @param array<string, mixed> $email */
    public function move(ImapConfiguration $configuration, array $email, ?string $configuredTrashFolder): string;
}
