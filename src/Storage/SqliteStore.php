<?php

declare(strict_types=1);

namespace ThreadMesh\Storage;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use SensitiveParameter;
use Throwable;
use ThreadMesh\Contract\AccountRepository;
use ThreadMesh\Contract\ItemRepository;
use ThreadMesh\Contract\SyncRunRepository;
use ThreadMesh\Contract\SyncStateRepository;
use ThreadMesh\Contract\TransactionManager;
use ThreadMesh\Domain\Account;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\Attachment;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Domain\SyncRunId;

final class SqliteStore implements AccountRepository, ItemRepository, SyncStateRepository, SyncRunRepository, TransactionManager
{
    private readonly PDO $pdo;

    public function __construct(
        SqliteConnection $connection,
        private readonly SecretCipher $cipher,
    ) {
        $this->pdo = $connection->pdo;
    }

    /** @param array<string, scalar|null> $configuration */
    public function configureAccount(
        string $id,
        string $displayName,
        array $configuration,
        #[SensitiveParameter] ?string $secret,
        bool $enabled = true,
    ): void {
        $existing = $this->fetchOne('SELECT encrypted_secret FROM accounts WHERE id = :id', ['id' => $id]);
        $encryptedSecret = $secret !== null && $secret !== ''
            ? $this->cipher->encrypt($secret, 'account:' . $id)
            : ($existing['encrypted_secret'] ?? null);
        if (!is_string($encryptedSecret) || $encryptedSecret === '') {
            throw new RuntimeException('A password or app password is required for a new account.');
        }
        $now = gmdate(DATE_ATOM);
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO accounts (id, connector, display_name, enabled, config_json, encrypted_secret, created_at, updated_at)
VALUES (:id, 'imap', :display_name, :enabled, :config_json, :encrypted_secret, :created_at, :updated_at)
ON CONFLICT(id) DO UPDATE SET
    display_name = excluded.display_name,
    enabled = excluded.enabled,
    config_json = excluded.config_json,
    encrypted_secret = excluded.encrypted_secret,
    updated_at = excluded.updated_at
SQL);
        $statement->execute([
            'id' => $id,
            'display_name' => $displayName,
            'enabled' => $enabled ? 1 : 0,
            'config_json' => Json::encode($configuration),
            'encrypted_secret' => $encryptedSecret,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function get(string $accountId): Account
    {
        $row = $this->fetchOne(
            'SELECT id, connector, display_name, enabled FROM accounts WHERE id = :id',
            ['id' => $accountId],
        );
        if ($row === null) {
            throw new RuntimeException(sprintf('Account "%s" was not found.', $accountId));
        }
        return new Account(
            $this->string($row, 'id'),
            $this->string($row, 'connector'),
            $this->string($row, 'display_name'),
            $this->boolean($row, 'enabled'),
        );
    }

    /** @return array{config:array<string, mixed>, secret:string} */
    public function accountConnection(string $accountId): array
    {
        $row = $this->fetchOne(
            'SELECT config_json, encrypted_secret FROM accounts WHERE id = :id',
            ['id' => $accountId],
        );
        if ($row === null) {
            throw new RuntimeException(sprintf('Account "%s" was not found.', $accountId));
        }
        return [
            'config' => Json::object($this->string($row, 'config_json')),
            'secret' => $this->cipher->decrypt($this->string($row, 'encrypted_secret'), 'account:' . $accountId),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function accounts(): array
    {
        return $this->fetchAll('SELECT id, connector, display_name, enabled, config_json, created_at, updated_at FROM accounts ORDER BY display_name');
    }

    public function load(string $accountId, string $streamId): ?SyncCursor
    {
        $row = $this->fetchOne(
            'SELECT cursor FROM streams WHERE account_id = :account_id AND stream_id = :stream_id',
            ['account_id' => $accountId, 'stream_id' => $streamId],
        );
        $cursor = $row['cursor'] ?? null;
        return is_string($cursor) && $cursor !== '' ? new SyncCursor($cursor) : null;
    }

    public function save(string $accountId, string $streamId, SyncCursor $cursor): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO streams (account_id, stream_id, display_name, enabled, cursor, updated_at)
VALUES (:account_id, :stream_id, :stream_id, 1, :cursor, :updated_at)
ON CONFLICT(account_id, stream_id) DO UPDATE SET cursor = excluded.cursor, updated_at = excluded.updated_at
SQL);
        $statement->execute([
            'account_id' => $accountId,
            'stream_id' => $streamId,
            'cursor' => $cursor->value,
            'updated_at' => gmdate(DATE_ATOM),
        ]);
    }

    /** @return list<array{accountId:string, streamId:string, displayName:string}> */
    public function enabledStreams(?string $accountId = null): array
    {
        $sql = 'SELECT s.account_id, s.stream_id, s.display_name FROM streams s JOIN accounts a ON a.id = s.account_id WHERE s.enabled = 1 AND a.enabled = 1';
        $parameters = [];
        if ($accountId !== null) {
            $sql .= ' AND s.account_id = :account_id';
            $parameters['account_id'] = $accountId;
        }
        $rows = $this->fetchAll($sql . ' ORDER BY s.account_id, s.stream_id', $parameters);
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'accountId' => $this->string($row, 'account_id'),
                'streamId' => $this->string($row, 'stream_id'),
                'displayName' => $this->string($row, 'display_name'),
            ];
        }
        return $result;
    }

    public function upsert(Item $item): void
    {
        $now = gmdate(DATE_ATOM);
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO items (
    id, connector, account_id, external_id, type, status, title, text_body, html_body,
    author_json, recipients_json, thread_refs_json, attachments_json, labels_json, metadata_json,
    source_created_at, source_updated_at, created_at, updated_at
) VALUES (
    :id, :connector, :account_id, :external_id, :type, :status, :title, :text_body, :html_body,
    :author_json, :recipients_json, :thread_refs_json, :attachments_json, :labels_json, :metadata_json,
    :source_created_at, :source_updated_at, :created_at, :updated_at
)
ON CONFLICT(connector, account_id, external_id) DO UPDATE SET
    status = excluded.status,
    title = excluded.title,
    text_body = excluded.text_body,
    html_body = excluded.html_body,
    author_json = excluded.author_json,
    recipients_json = excluded.recipients_json,
    thread_refs_json = excluded.thread_refs_json,
    attachments_json = excluded.attachments_json,
    labels_json = excluded.labels_json,
    metadata_json = excluded.metadata_json,
    source_updated_at = excluded.source_updated_at,
    updated_at = excluded.updated_at
SQL);
        $statement->execute([
            'id' => $item->id->value,
            'connector' => $item->source->connector,
            'account_id' => $item->source->accountId,
            'external_id' => $item->source->externalId,
            'type' => $item->type->value,
            'status' => $item->status->value,
            'title' => $item->title,
            'text_body' => $item->content->text,
            'html_body' => $item->content->html,
            'author_json' => Json::encode($this->actor($item->author)),
            'recipients_json' => Json::encode(array_map($this->actor(...), $item->recipients)),
            'thread_refs_json' => Json::encode($item->threadReferences),
            'attachments_json' => Json::encode(array_map($this->attachment(...), $item->attachments)),
            'labels_json' => Json::encode($item->labels),
            'metadata_json' => Json::encode($item->metadata),
            'source_created_at' => $item->createdAt->format(DATE_ATOM),
            'source_updated_at' => $item->updatedAt?->format(DATE_ATOM),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function unassessedEmails(int $limit = 20): array
    {
        $emails = [];
        foreach ($this->fetchAll(
            'SELECT i.* FROM items i LEFT JOIN assessments a ON a.email_id = i.id WHERE i.type = :type AND a.email_id IS NULL ORDER BY i.source_created_at ASC LIMIT :limit',
            ['type' => 'email', 'limit' => $limit],
        ) as $row) {
            $emails[] = $this->hydrateEmail($row);
        }
        return $emails;
    }

    /**
     * @param list<string> $importance
     * @return list<array<string, mixed>>
     */
    public function mailboxEmails(
        DateTimeImmutable $since,
        ?DateTimeImmutable $until = null,
        ?string $accountId = null,
        array $importance = [],
        ?bool $assessed = null,
        ?bool $requiresAction = null,
        int $limit = 100,
    ): array {
        $where = ['i.type = :type', 'julianday(i.source_created_at) >= julianday(:since)'];
        $parameters = ['type' => 'email', 'since' => $since->format(DATE_ATOM), 'limit' => $limit];
        if ($until !== null) {
            $where[] = 'julianday(i.source_created_at) < julianday(:until)';
            $parameters['until'] = $until->format(DATE_ATOM);
        }
        if ($accountId !== null) {
            $where[] = 'i.account_id = :account_id';
            $parameters['account_id'] = $accountId;
        }
        if ($importance !== []) {
            $placeholders = [];
            foreach ($importance as $index => $value) {
                $parameter = 'importance_' . $index;
                $placeholders[] = ':' . $parameter;
                $parameters[$parameter] = $value;
            }
            $where[] = 'a.importance IN (' . implode(', ', $placeholders) . ')';
        }
        if ($assessed !== null) {
            $where[] = $assessed ? 'a.email_id IS NOT NULL' : 'a.email_id IS NULL';
        }
        if ($requiresAction !== null) {
            $where[] = 'a.requires_action = :requires_action';
            $parameters['requires_action'] = $requiresAction ? 1 : 0;
        }

        $sql = <<<'SQL'
SELECT i.id, i.account_id, i.title, i.status, i.author_json, i.recipients_json, i.source_created_at,
       a.importance, a.category, a.summary, a.requires_action, a.due_at,
       a.amount, a.currency, a.recommended_action, a.reason, a.assessed_at,
       CASE WHEN a.email_id IS NULL THEN 0 ELSE 1 END AS assessed
FROM items i
LEFT JOIN assessments a ON a.email_id = i.id
SQL;
        $rows = $this->fetchAll(
            $sql . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY julianday(i.source_created_at) DESC, i.id DESC LIMIT :limit',
            $parameters,
        );
        foreach ($rows as &$row) {
            $author = $row['author_json'] ?? null;
            $row['author'] = is_string($author) ? json_decode($author, true) : null;
            unset($row['author_json']);
            $recipients = $row['recipients_json'] ?? null;
            $row['recipients'] = is_string($recipients) ? json_decode($recipients, true) : [];
            unset($row['recipients_json']);
            $row['assessed'] = (bool) ($row['assessed'] ?? false);
            if ($row['requires_action'] !== null) {
                $row['requires_action'] = (bool) $row['requires_action'];
            }
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function email(string $id): ?array
    {
        $row = $this->fetchOne(
            'SELECT i.*, a.importance, a.category, a.summary, a.requires_action, a.due_at, a.amount, a.currency, a.recommended_action, a.reason, a.assessed_at FROM items i LEFT JOIN assessments a ON a.email_id = i.id WHERE i.id = :id',
            ['id' => $id],
        );
        return $row !== null ? $this->hydrateEmail($row) : null;
    }

    public function storeAssessment(
        string $emailId,
        string $importance,
        string $category,
        string $summary,
        bool $requiresAction,
        ?string $dueAt,
        ?float $amount,
        ?string $currency,
        string $recommendedAction,
        string $reason,
    ): void {
        if (!in_array($importance, ['low', 'normal', 'high', 'critical'], true)) {
            throw new RuntimeException('Importance must be low, normal, high, or critical.');
        }
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO assessments (email_id, importance, category, summary, requires_action, due_at, amount, currency, recommended_action, reason, assessed_at)
VALUES (:email_id, :importance, :category, :summary, :requires_action, :due_at, :amount, :currency, :recommended_action, :reason, :assessed_at)
ON CONFLICT(email_id) DO UPDATE SET
    importance = excluded.importance,
    category = excluded.category,
    summary = excluded.summary,
    requires_action = excluded.requires_action,
    due_at = excluded.due_at,
    amount = excluded.amount,
    currency = excluded.currency,
    recommended_action = excluded.recommended_action,
    reason = excluded.reason,
    assessed_at = excluded.assessed_at
SQL);
        $statement->execute([
            'email_id' => $emailId,
            'importance' => $importance,
            'category' => $category,
            'summary' => $summary,
            'requires_action' => $requiresAction ? 1 : 0,
            'due_at' => $dueAt,
            'amount' => $amount,
            'currency' => $currency,
            'recommended_action' => $recommendedAction,
            'reason' => $reason,
            'assessed_at' => gmdate(DATE_ATOM),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function alerts(int $limit = 50): array
    {
        return $this->fetchAll(<<<'SQL'
SELECT i.id AS email_id, i.title, i.author_json, i.source_created_at,
       a.importance, a.category, a.summary, a.requires_action, a.due_at,
       a.amount, a.currency, a.recommended_action, a.reason, a.assessed_at
FROM assessments a
JOIN items i ON i.id = a.email_id
WHERE a.importance IN ('high', 'critical') OR a.requires_action = 1 OR a.category = 'invoice'
ORDER BY a.assessed_at DESC
LIMIT :limit
SQL, ['limit' => $limit]);
    }

    /** @return array<string, mixed> */
    public function createDraft(string $emailId, string $subject, string $bodyText): array
    {
        if ($this->email($emailId) === null) {
            throw new RuntimeException('The source email was not found.');
        }
        $id = bin2hex(random_bytes(16));
        $now = gmdate(DATE_ATOM);
        $statement = $this->pdo->prepare(
            'INSERT INTO drafts (id, email_id, subject, body_text, status, created_at, updated_at) VALUES (:id, :email_id, :subject, :body_text, :status, :created_at, :updated_at)',
        );
        $statement->execute([
            'id' => $id,
            'email_id' => $emailId,
            'subject' => $subject,
            'body_text' => $bodyText,
            'status' => 'local',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->draft($id) ?? throw new RuntimeException('Draft could not be loaded after creation.');
    }

    /** @return array<string, mixed>|null */
    public function draft(string $id): ?array
    {
        return $this->fetchOne('SELECT * FROM drafts WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed> */
    public function markDraftPublished(string $id, string $imapReference): array
    {
        $statement = $this->pdo->prepare('UPDATE drafts SET status = :status, imap_reference = :imap_reference, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'status' => 'imap_draft',
            'imap_reference' => $imapReference,
            'updated_at' => gmdate(DATE_ATOM),
            'id' => $id,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Draft was not found.');
        }
        return $this->draft($id) ?? throw new RuntimeException('Published draft could not be loaded.');
    }

    public function start(string $accountId, string $streamId): SyncRunId
    {
        $id = bin2hex(random_bytes(16));
        $statement = $this->pdo->prepare(
            'INSERT INTO sync_runs (id, account_id, stream_id, status, item_count, has_more, started_at) VALUES (:id, :account_id, :stream_id, :status, 0, 0, :started_at)',
        );
        $statement->execute([
            'id' => $id,
            'account_id' => $accountId,
            'stream_id' => $streamId,
            'status' => 'running',
            'started_at' => gmdate(DATE_ATOM),
        ]);
        return new SyncRunId($id);
    }

    public function complete(SyncRunId $runId, int $itemCount, bool $hasMore): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sync_runs SET status = :status, item_count = :item_count, has_more = :has_more, completed_at = :completed_at WHERE id = :id',
        );
        $statement->execute([
            'status' => 'completed',
            'item_count' => $itemCount,
            'has_more' => $hasMore ? 1 : 0,
            'completed_at' => gmdate(DATE_ATOM),
            'id' => $runId->value,
        ]);
    }

    public function fail(SyncRunId $runId, string $errorType, string $safeMessage): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sync_runs SET status = :status, error_type = :error_type, error_message = :error_message, completed_at = :completed_at WHERE id = :id',
        );
        $statement->execute([
            'status' => 'failed',
            'error_type' => $errorType,
            'error_message' => $safeMessage,
            'completed_at' => gmdate(DATE_ATOM),
            'id' => $runId->value,
        ]);
    }

    public function run(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        return $this->normalizeRow($statement->fetch());
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $normalized = $this->normalizeRow($row);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function normalizeRow(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateEmail(array $row): array
    {
        foreach (['author_json', 'recipients_json', 'thread_refs_json', 'attachments_json', 'labels_json', 'metadata_json'] as $field) {
            $value = $row[$field] ?? null;
            if (is_string($value)) {
                $row[substr($field, 0, -5)] = json_decode($value, true);
                unset($row[$field]);
            }
        }
        return $row;
    }

    /** @return array{id:string, displayName:string, address:?string} */
    private function actor(Actor $actor): array
    {
        return ['id' => $actor->id, 'displayName' => $actor->displayName, 'address' => $actor->address];
    }

    /** @return array{externalId:string, name:string, mediaType:string, size:?int, inline:bool, contentId:?string} */
    private function attachment(Attachment $attachment): array
    {
        return [
            'externalId' => $attachment->externalId,
            'name' => $attachment->name,
            'mediaType' => $attachment->mediaType,
            'size' => $attachment->size,
            'inline' => $attachment->inline,
            'contentId' => $attachment->contentId,
        ];
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException(sprintf('Database field "%s" is invalid.', $field));
        }
        return $value;
    }

    /** @param array<string, mixed> $row */
    private function boolean(array $row, string $field): bool
    {
        $value = $row[$field] ?? null;
        if (!is_int($value) && !is_bool($value)) {
            throw new RuntimeException(sprintf('Database field "%s" is invalid.', $field));
        }
        return (bool) $value;
    }
}
