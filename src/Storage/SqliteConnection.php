<?php

declare(strict_types=1);

namespace ThreadMesh\Storage;

use PDO;
use RuntimeException;

final class SqliteConnection
{
    public readonly PDO $pdo;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create SQLite directory "%s".', $directory));
        }
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS accounts (
    id TEXT PRIMARY KEY,
    connector TEXT NOT NULL,
    display_name TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    config_json TEXT NOT NULL,
    encrypted_secret TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS streams (
    account_id TEXT NOT NULL,
    stream_id TEXT NOT NULL,
    display_name TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    cursor TEXT,
    updated_at TEXT NOT NULL,
    PRIMARY KEY (account_id, stream_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS items (
    id TEXT PRIMARY KEY,
    connector TEXT NOT NULL,
    account_id TEXT NOT NULL,
    external_id TEXT NOT NULL,
    type TEXT NOT NULL,
    status TEXT NOT NULL,
    title TEXT NOT NULL,
    text_body TEXT,
    html_body TEXT,
    author_json TEXT NOT NULL,
    recipients_json TEXT NOT NULL,
    thread_refs_json TEXT NOT NULL,
    attachments_json TEXT NOT NULL,
    labels_json TEXT NOT NULL,
    metadata_json TEXT NOT NULL,
    source_created_at TEXT NOT NULL,
    source_updated_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (connector, account_id, external_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS items_type_date ON items(type, source_created_at DESC);
CREATE TABLE IF NOT EXISTS sync_runs (
    id TEXT PRIMARY KEY,
    account_id TEXT NOT NULL,
    stream_id TEXT NOT NULL,
    status TEXT NOT NULL,
    item_count INTEGER NOT NULL DEFAULT 0,
    has_more INTEGER NOT NULL DEFAULT 0,
    error_type TEXT,
    error_message TEXT,
    started_at TEXT NOT NULL,
    completed_at TEXT,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS assessments (
    email_id TEXT PRIMARY KEY,
    importance TEXT NOT NULL,
    category TEXT NOT NULL,
    summary TEXT NOT NULL,
    requires_action INTEGER NOT NULL,
    due_at TEXT,
    amount REAL,
    currency TEXT,
    recommended_action TEXT NOT NULL,
    reason TEXT NOT NULL,
    assessed_at TEXT NOT NULL,
    FOREIGN KEY (email_id) REFERENCES items(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS drafts (
    id TEXT PRIMARY KEY,
    email_id TEXT NOT NULL,
    subject TEXT NOT NULL,
    body_text TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'local',
    imap_reference TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (email_id) REFERENCES items(id) ON DELETE CASCADE
);
SQL);
    }
}
