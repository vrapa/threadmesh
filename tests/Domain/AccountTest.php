<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\Account;

final class AccountTest extends TestCase
{
    public function testItKeepsPublicIdentityWithoutCredentials(): void
    {
        $account = new Account('personal', 'imap', 'Personal inbox');
        self::assertSame('personal', $account->id);
        self::assertSame('imap', $account->connector);
        self::assertSame('Personal inbox', $account->displayName);
    }

    #[DataProvider('invalidValues')]
    public function testItRejectsEmptyIdentityValues(string $id, string $connector, string $displayName): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Account($id, $connector, $displayName);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidValues(): iterable
    {
        yield 'id' => ['', 'imap', 'Inbox'];
        yield 'connector' => ['personal', ' ', 'Inbox'];
        yield 'display name' => ['personal', 'imap', ''];
    }
}
