<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Client;

use DateTimeImmutable;

final class MessageData
{
    /**
     * @param list<EmailAddress> $from
     * @param list<EmailAddress> $to
     * @param list<EmailAddress> $cc
     * @param list<string> $references
     * @param list<AttachmentData> $attachments
     * @param array<string, scalar|null> $headers
     */
    public function __construct(
        public readonly int $uid,
        public readonly ?string $messageId,
        public readonly string $subject,
        public readonly array $from,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $references,
        public readonly ?string $text,
        public readonly ?string $html,
        public readonly DateTimeImmutable $date,
        public readonly array $attachments = [],
        public readonly array $headers = [],
    ) {
    }
}
