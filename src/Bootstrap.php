<?php

declare(strict_types=1);

namespace ThreadMesh;

use ThreadMesh\Application\InitializeAccount;
use ThreadMesh\Application\SynchronizeStream;
use ThreadMesh\Mail\ThreadMeshService;
use ThreadMesh\Storage\SecretCipher;
use ThreadMesh\Storage\SqliteConnection;
use ThreadMesh\Storage\SqliteConnectorProvider;
use ThreadMesh\Storage\SqliteStore;
use ThreadMesh\Imap\ImapDraftWriter;

final class Bootstrap
{
    public static function create(?string $databasePath = null): ThreadMeshService
    {
        $path = $databasePath ?? self::databasePath();
        $store = new SqliteStore(new SqliteConnection($path), SecretCipher::fromEnvironment());
        $connectors = new SqliteConnectorProvider($store);

        return new ThreadMeshService(
            $store,
            $connectors,
            new InitializeAccount($store, $connectors, $store, $store),
            new SynchronizeStream($store, $connectors, $store, $store, $store, $store),
            $connectors,
            new ImapDraftWriter(),
        );
    }

    public static function databasePath(): string
    {
        $configured = getenv('THREADMESH_DB');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        return dirname(__DIR__) . '/var/threadmesh.sqlite';
    }
}
