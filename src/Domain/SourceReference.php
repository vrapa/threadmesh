<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use InvalidArgumentException;

final class SourceReference
{
    public function __construct(
        public readonly string $connector,
        public readonly string $accountId,
        public readonly string $externalId,
        public readonly ?string $url = null,
    ) {
        self::requireNonEmpty($connector, 'Connector');
        self::requireNonEmpty($accountId, 'Account ID');
        self::requireNonEmpty($externalId, 'External ID');
        if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Source URL must be a valid absolute URL.');
        }
    }

    public function key(): string
    {
        return implode(':', [$this->connector, $this->accountId, $this->externalId]);
    }

    private static function requireNonEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $field));
        }
    }
}
