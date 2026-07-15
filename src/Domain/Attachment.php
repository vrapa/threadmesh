<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;

final class Attachment
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $name,
        public readonly string $mediaType,
        public readonly ?int $size = null,
        public readonly bool $inline = false,
        public readonly ?string $contentId = null,
    ) {
        if (trim($externalId) === '' || trim($name) === '' || trim($mediaType) === '') {
            throw new InvalidArgumentException('Attachment identity, name and media type must not be empty.');
        }
        if ($size !== null && $size < 0) {
            throw new InvalidArgumentException('Attachment size must not be negative.');
        }
    }
}
