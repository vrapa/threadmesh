<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\Account;

interface AccountRepository
{
    public function get(string $accountId): Account;
}
