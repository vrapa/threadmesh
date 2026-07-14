<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

final class ActionResult
{
    /** @param array<string, scalar|null> $metadata */
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $message,
        public readonly array $metadata,
    ) {
    }

    /** @param array<string, scalar|null> $metadata */
    public static function success(?string $message = null, array $metadata = []): self
    {
        return new self(true, $message, $metadata);
    }

    /** @param array<string, scalar|null> $metadata */
    public static function failure(string $message, array $metadata = []): self
    {
        return new self(false, $message, $metadata);
    }
}
