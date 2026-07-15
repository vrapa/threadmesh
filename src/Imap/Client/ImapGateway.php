<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Client;

use ThreadMesh\Imap\ImapConfiguration;

interface ImapGateway
{
    public function connect(ImapConfiguration $configuration): void;

    /** @return list<FolderStatus> */
    public function folders(): array;

    public function status(string $folderId): FolderStatus;

    /** @return list<MessageData> ordered by ascending UID */
    public function messagesAfter(string $folderId, int $lastUid, int $limit): array;

    /** @return resource */
    public function downloadAttachment(string $folderId, int $uid, string $partId);
}
