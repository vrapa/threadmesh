<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ThreadMesh\Imap\Client\WebklexImapGateway;
use ThreadMesh\Imap\ImapConfiguration;

final class TlsImapServerTest extends TestCase
{
    public function testConfiguredTlsServerCanBeExaminedReadOnly(): void
    {
        $host = getenv('THREADMESH_TEST_IMAP_HOST');
        if (!is_string($host) || $host === '') {
            self::markTestSkipped('Set THREADMESH_TEST_IMAP_HOST to run the TLS integration test.');
        }

        $gateway = new WebklexImapGateway();
        $gateway->connect(new ImapConfiguration(
            'integration',
            $host,
            (int) (getenv('THREADMESH_TEST_IMAP_PORT') ?: 993),
            (string) (getenv('THREADMESH_TEST_IMAP_ENCRYPTION') ?: 'ssl'),
            getenv('THREADMESH_TEST_IMAP_VALIDATE_CERT') !== '0',
            (string) getenv('THREADMESH_TEST_IMAP_USER'),
            (string) getenv('THREADMESH_TEST_IMAP_PASSWORD'),
        ));

        $folders = $gateway->folders();
        self::assertNotSame([], $folders);
        self::assertGreaterThan(0, $gateway->status($folders[0]->id)->uidValidity);
    }
}
