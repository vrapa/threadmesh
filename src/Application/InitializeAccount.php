<?php

declare(strict_types=1);

namespace ThreadMesh\Application;

use InvalidArgumentException;
use ThreadMesh\Contract\AccountRepository;
use ThreadMesh\Contract\ConnectorProvider;
use ThreadMesh\Contract\SyncStateRepository;
use ThreadMesh\Contract\TransactionManager;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncCursor;

final class InitializeAccount
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly ConnectorProvider $connectors,
        private readonly SyncStateRepository $states,
        private readonly TransactionManager $transactions,
    ) {
    }

    /**
     * @param list<string> $streamIds
     * @return array<string, SyncCursor>
     */
    public function execute(string $accountId, array $streamIds): array
    {
        $account = $this->accounts->get($accountId);
        if (!$account->enabled) {
            throw new InvalidArgumentException('Disabled accounts cannot be initialized.');
        }
        $connector = $this->connectors->forAccount($account);
        $available = [];
        foreach ($connector->streams() as $stream) {
            $available[$stream->id] = $stream;
        }
        $initialized = [];
        foreach ($streamIds as $streamId) {
            $stream = $available[$streamId] ?? null;
            if (!$stream instanceof SourceStream) {
                throw new InvalidArgumentException(sprintf('Unknown source stream "%s".', $streamId));
            }
            $initialized[$streamId] = $connector->initialize($stream);
        }
        $this->transactions->run(function () use ($accountId, $initialized): void {
            foreach ($initialized as $streamId => $cursor) {
                $this->states->save($accountId, $streamId, $cursor);
            }
        });
        return $initialized;
    }
}
