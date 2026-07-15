<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

use InvalidArgumentException;
use SensitiveParameter;

final class ImapConfiguration
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $host,
        public readonly int $port,
        public readonly string $encryption,
        public readonly bool $validateCertificate,
        public readonly string $username,
        #[SensitiveParameter]
        public readonly string $password,
    ) {
        if (trim($accountId) === '' || trim($host) === '' || trim($username) === '') {
            throw new InvalidArgumentException('Account ID, host, and username are required.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('IMAP port must be between 1 and 65535.');
        }
        if (!in_array($encryption, ['ssl', 'tls', 'starttls'], true)) {
            throw new InvalidArgumentException('IMAP encryption must be ssl, tls, or starttls.');
        }
        if ($password === '') {
            throw new InvalidArgumentException('IMAP password must not be empty.');
        }
    }
}
