<?php

declare(strict_types=1);

namespace ThreadMesh\Mail;

use DateTimeImmutable;
use RuntimeException;
use Throwable;
use ThreadMesh\Imap\TrashMover;
use ThreadMesh\Imap\SourceMessageAbsent;
use ThreadMesh\Storage\SqliteConnectorProvider;
use ThreadMesh\Storage\SqliteStore;

final class TrashEmail
{
    public function __construct(
        private readonly SqliteStore $store,
        private readonly SqliteConnectorProvider $connections,
        private readonly TrashMover $mover,
    ) {
    }

    public function execute(string $emailId): void
    {
        $email = $this->store->email($emailId) ?? throw new RuntimeException('Email was not found.');
        if (($email['connector'] ?? null) !== 'imap' || ($email['type'] ?? null) !== 'email') {
            throw new RuntimeException('Only stored IMAP email can be moved to Trash.');
        }
        $accountId = $email['account_id'] ?? null;
        if (!is_string($accountId)) {
            throw new RuntimeException('Email source account is invalid.');
        }
        $account = $this->store->get($accountId);
        $configuration = $this->connections->configuration($account);
        $trashFolder = $this->connections->trashFolder($account);

        $operation = $this->store->trashOperation($emailId);
        if (($operation['status'] ?? null) === 'done') {
            return;
        }
        if (($operation['status'] ?? null) === 'pending'
            && (new DateTimeImmutable($operation['updated_at']))->getTimestamp() > time() - 300) {
            throw new RuntimeException('A move to IMAP Trash is already in progress.');
        }
        if ($operation !== null && !$this->mover->sourcePresent($configuration, $email)) {
            throw new SourceMessageAbsent();
        }

        $claimed = $this->store->run(function () use ($emailId): bool {
            $current = $this->store->trashOperation($emailId);
            if (($current['status'] ?? null) === 'done') {
                return false;
            }
            if (($current['status'] ?? null) === 'pending'
                && (new DateTimeImmutable($current['updated_at']))->getTimestamp() > time() - 300) {
                throw new RuntimeException('A move to IMAP Trash is already in progress.');
            }
            $this->store->setTrashStatus($emailId, 'pending');
            return true;
        });
        if (!$claimed) {
            return;
        }

        try {
            $path = $this->mover->move($configuration, $email, $trashFolder);
        } catch (Throwable $error) {
            $this->store->setTrashStatus($emailId, 'uncertain');
            if ($error instanceof RuntimeException) {
                throw $error;
            }
            throw new RuntimeException('Could not move the message to IMAP Trash; the result is uncertain.', 0, $error);
        }
        $this->store->setTrashStatus($emailId, 'done', $path);
    }
}
