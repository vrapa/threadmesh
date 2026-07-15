<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\Account;

interface ConnectorProvider
{
    public function forAccount(Account $account): SourceConnector;
}
