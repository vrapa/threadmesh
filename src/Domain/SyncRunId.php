<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;
use Stringable;

final class SyncRunId implements Stringable
{
    public function __construct(public readonly string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Sync run ID must not be empty.');
        }
    }
    public function __toString(): string { return $this->value; }
}
