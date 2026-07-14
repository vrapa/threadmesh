<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;

final class Actor
{
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly ?string $address = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Actor ID must not be empty.');
        }
        if (trim($displayName) === '') {
            throw new InvalidArgumentException('Actor display name must not be empty.');
        }
    }
}
