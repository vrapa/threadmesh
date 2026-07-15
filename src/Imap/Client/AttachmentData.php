<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Client;

final class AttachmentData
{
    public function __construct(
        public readonly string $partId,
        public readonly string $name,
        public readonly string $mediaType,
        public readonly ?int $size = null,
        public readonly bool $inline = false,
        public readonly ?string $contentId = null,
    ) {
    }
}
