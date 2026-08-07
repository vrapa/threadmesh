<?php

declare(strict_types=1);

namespace ThreadMesh\Mail;

use DateTimeImmutable;
use RuntimeException;
use SensitiveParameter;
use ThreadMesh\Application\BackfillStream;
use ThreadMesh\Application\InitializeAccount;
use ThreadMesh\Application\SynchronizeStream;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Storage\SqliteStore;
use ThreadMesh\Storage\SqliteConnectorProvider;
use ThreadMesh\Imap\ImapDraftWriter;

final class ThreadMeshService
{
    private readonly MailHeaderNormalizer $headers;
    public function __construct(
        private readonly SqliteStore $store,
        private readonly ConnectorProvider $connectors,
        private readonly InitializeAccount $initializer,
        private readonly SynchronizeStream $synchronizer,
        private readonly SqliteConnectorProvider $imapConnections,
        private readonly ImapDraftWriter $draftWriter,
        private readonly BackfillStream $backfiller,
        ?MailHeaderNormalizer $headers = null,
    ) {
        $this->headers = $headers ?? new MailHeaderNormalizer();
    }

    /** @param array<string, scalar|null> $configuration */
    public function configureAccount(
        string $id,
        string $displayName,
        array $configuration,
        #[SensitiveParameter] ?string $secret,
        bool $enabled = true,
    ): void {
        $this->store->configureAccount($id, $displayName, $configuration, $secret, $enabled);
    }

    /** @return list<array<string, mixed>> */
    public function accounts(): array
    {
        return $this->store->accounts();
    }

    /** @return array{succeeded:bool, message:string} */
    public function testConnection(string $accountId): array
    {
        $account = $this->store->get($accountId);
        $result = $this->connectors->forAccount($account)->testConnection();
        return ['succeeded' => $result->succeeded, 'message' => $result->message];
    }

    /** @return list<array{id:string, displayName:string}> */
    public function folders(string $accountId): array
    {
        $account = $this->store->get($accountId);
        return array_map(
            static fn (SourceStream $stream): array => ['id' => $stream->id, 'displayName' => $stream->displayName],
            $this->connectors->forAccount($account)->streams(),
        );
    }

    /**
     * @param list<string> $streamIds
     * @return array<string, string>
     */
    public function initialize(string $accountId, array $streamIds = ['INBOX']): array
    {
        $cursors = $this->initializer->execute($accountId, $streamIds);
        $result = [];
        foreach ($cursors as $streamId => $cursor) {
            $result[$streamId] = $cursor->value;
        }
        return $result;
    }

    /** @return array{streams:int, items:int, hasMore:bool} */
    public function sync(?string $accountId = null, int $batchSize = 100): array
    {
        $streams = $this->store->enabledStreams($accountId);
        if ($streams === [] && $accountId !== null) {
            $this->initialize($accountId);
            $streams = $this->store->enabledStreams($accountId);
        }

        $itemCount = 0;
        $hasMore = false;
        foreach ($streams as $stream) {
            $result = $this->synchronizer->execute(
                $stream['accountId'],
                new SourceStream($stream['streamId'], $stream['displayName']),
                $batchSize,
            );
            $itemCount += count($result->items);
            $hasMore = $hasMore || $result->hasMore;
        }
        return ['streams' => count($streams), 'items' => $itemCount, 'hasMore' => $hasMore];
    }

    /** @return list<array<string, mixed>> */
    public function unassessedEmails(int $limit = 20): array
    {
        return $this->headers->emails($this->store->unassessedEmails($this->limit($limit)));
    }

    /**
     * @param list<string> $importance
     * @return list<array<string, mixed>>
     */
    public function mailbox(
        DateTimeImmutable $since,
        ?DateTimeImmutable $until = null,
        ?string $accountId = null,
        array $importance = [],
        ?bool $assessed = null,
        ?bool $requiresAction = null,
        int $limit = 100,
    ): array {
        if ($until !== null && $until <= $since) {
            throw new RuntimeException('until must be later than since.');
        }
        foreach ($importance as $value) {
            if (!in_array($value, ['low', 'normal', 'high', 'critical'], true)) {
                throw new RuntimeException('Importance must be low, normal, high, or critical.');
            }
        }
        return $this->headers->emails($this->store->mailboxEmails(
            $since,
            $until,
            $accountId,
            array_values(array_unique($importance)),
            $assessed,
            $requiresAction,
            $this->limit($limit, 200),
        ));
    }

    /** @return array{items:int, hasMore:bool, cursor:string, since:string} */
    public function backfill(
        string $accountId,
        string $streamId,
        DateTimeImmutable $since,
        ?string $cursor = null,
        int $batchSize = 100,
    ): array {
        $result = $this->backfiller->execute(
            $accountId,
            $streamId,
            $since,
            $cursor !== null ? new SyncCursor($cursor) : null,
            $this->limit($batchSize, 500),
        );
        return [
            'items' => count($result->items),
            'hasMore' => $result->hasMore,
            'cursor' => $result->nextCursor->value,
            'since' => $since->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function email(string $id): array
    {
        $email = $this->store->email($id) ?? throw new RuntimeException('Email was not found.');
        return $this->headers->email($email);
    }

    public function assess(
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
        $this->store->storeAssessment(
            $emailId,
            $importance,
            $category,
            $summary,
            $requiresAction,
            $dueAt,
            $amount,
            $currency,
            $recommendedAction,
            $reason,
        );
    }

    /** @return list<array<string, mixed>> */
    public function alerts(int $limit = 50): array
    {
        return $this->headers->emails($this->store->alerts($this->limit($limit, 200)));
    }

    /** @return array<string, mixed> */
    public function createDraft(string $emailId, string $subject, string $bodyText): array
    {
        return $this->store->createDraft($emailId, $subject, $bodyText);
    }

    /** @return array<string, mixed> */
    public function publishDraft(string $draftId): array
    {
        $draft = $this->store->draft($draftId) ?? throw new RuntimeException('Draft was not found.');
        if (($draft['status'] ?? null) !== 'local') {
            throw new RuntimeException('Only a local draft can be published to IMAP.');
        }
        $emailId = $draft['email_id'] ?? null;
        if (!is_string($emailId)) {
            throw new RuntimeException('Draft source email is invalid.');
        }
        $email = $this->email($emailId);
        $accountId = $email['account_id'] ?? null;
        if (!is_string($accountId)) {
            throw new RuntimeException('Draft source account is invalid.');
        }
        $account = $this->store->get($accountId);
        $reference = $this->draftWriter->save(
            $this->imapConnections->configuration($account),
            $this->imapConnections->draftFolder($account),
            $email,
            $draft,
        );
        return $this->store->markDraftPublished($draftId, $reference);
    }

    private function limit(int $limit, int $maximum = 100): int
    {
        return max(1, min($maximum, $limit));
    }
}
