<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;
use Stringable;

final class SourceStream implements Stringable
{
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
    ) {
        if (trim($id) === '' || trim($displayName) === '') {
            throw new InvalidArgumentException('Source stream ID and display name must not be empty.');
        }
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
