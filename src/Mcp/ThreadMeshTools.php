<?php

declare(strict_types=1);

namespace ThreadMesh\Mcp;

use ThreadMesh\Mail\ThreadMeshService;

final class ThreadMeshTools
{
    public function __construct(private readonly ThreadMeshService $service)
    {
    }

    /** @return array{streams:int, items:int, hasMore:bool} */
    public function syncMail(?string $accountId = null, int $batchSize = 100): array
    {
        return $this->service->sync($accountId, $batchSize);
    }

    /** @return list<array<string, mixed>> */
    public function listUnassessedEmails(int $limit = 20): array
    {
        return $this->service->unassessedEmails($limit);
    }

    /** @return array<string, mixed> */
    public function getEmail(string $emailId): array
    {
        return $this->service->email($emailId);
    }

    /** @return array{stored:true} */
    public function storeAssessment(
        string $emailId,
        string $importance,
        string $category,
        string $summary,
        bool $requiresAction,
        ?string $dueAt = null,
        ?float $amount = null,
        ?string $currency = null,
        string $recommendedAction = '',
        string $reason = '',
    ): array {
        $this->service->assess(
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
        return ['stored' => true];
    }

    /** @return list<array<string, mixed>> */
    public function listMailAlerts(int $limit = 50): array
    {
        return $this->service->alerts($limit);
    }

    /** @return array<string, mixed> */
    public function createReplyDraft(string $emailId, string $subject, string $bodyText): array
    {
        return $this->service->createDraft($emailId, $subject, $bodyText);
    }

    /** @return array<string, mixed> */
    public function publishDraftToImap(string $draftId, bool $confirmed): array
    {
        if (!$confirmed) {
            throw new \RuntimeException('Explicit confirmation is required before modifying IMAP.');
        }
        return $this->service->publishDraft($draftId);
    }
}
