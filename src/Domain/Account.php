<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;

final class Account
{
    public function __construct(
        public readonly string $id,
        public readonly string $connector,
        public readonly string $displayName,
        public readonly bool $enabled = true,
    ) {
        self::requireNonEmpty($id, 'Account ID');
        self::requireNonEmpty($connector, 'Connector name');
        self::requireNonEmpty($displayName, 'Display name');
    }

    private static function requireNonEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $field));
        }
    }
}
