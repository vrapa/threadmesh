<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Client;

final class EmailAddress
{
    public function __construct(
        public readonly string $address,
        public readonly ?string $name = null,
    ) {
    }
}
