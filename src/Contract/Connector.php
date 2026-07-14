<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\Account;
use ThreadMesh\Domain\ActionResult;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Domain\SyncResult;

interface Connector
{
    /** Stable machine-readable name, for example "imap" or "jira". */
    public function name(): string;

    /** Returns records changed after the supplied opaque cursor. */
    public function synchronize(Account $account, ?SyncCursor $cursor = null): SyncResult;

    /** Executes an action supported by this connector. */
    public function execute(Account $account, Action $action): ActionResult;
}
