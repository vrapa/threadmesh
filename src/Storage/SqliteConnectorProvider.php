<?php

declare(strict_types=1);

namespace ThreadMesh\Storage;

use RuntimeException;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Contract\SourceConnector;
use ThreadMesh\Domain\Account;
use ThreadMesh\Imap\Client\WebklexImapGateway;
use ThreadMesh\Imap\ImapConfiguration;
use ThreadMesh\Imap\ImapConnector;

final class SqliteConnectorProvider implements ConnectorProvider
{
    public function __construct(private readonly SqliteStore $store)
    {
    }

    public function forAccount(Account $account): SourceConnector
    {
        if ($account->connector !== 'imap') {
            throw new RuntimeException(sprintf('Unsupported connector "%s".', $account->connector));
        }
        return new ImapConnector(
            $this->configuration($account),
            new WebklexImapGateway(),
        );
    }

    public function configuration(Account $account): ImapConfiguration
    {
        $connection = $this->store->accountConnection($account->id);
        $config = $connection['config'];
        return new ImapConfiguration(
            $account->id,
            $this->requiredString($config, 'host'),
            $this->integer($config, 'port', 993),
            $this->string($config, 'encryption', 'ssl'),
            $this->boolean($config, 'validateCertificate', true),
            $this->requiredString($config, 'username'),
            $connection['secret'],
        );
    }

    public function draftFolder(Account $account): string
    {
        $config = $this->store->accountConnection($account->id)['config'];
        return $this->requiredString($config, 'draftFolder');
    }

    /** @param array<string, mixed> $config */
    private function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf('Account configuration field "%s" is required.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $config */
    private function string(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** @param array<string, mixed> $config */
    private function integer(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? null;
        return is_int($value) ? $value : $default;
    }

    /** @param array<string, mixed> $config */
    private function boolean(array $config, string $key, bool $default): bool
    {
        $value = $config[$key] ?? null;
        return is_bool($value) ? $value : $default;
    }
}
