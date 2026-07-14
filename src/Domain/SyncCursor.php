<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;
use Stringable;

final class SyncCursor implements Stringable
{
    public function __construct(public readonly string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Sync cursor must not be empty.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
