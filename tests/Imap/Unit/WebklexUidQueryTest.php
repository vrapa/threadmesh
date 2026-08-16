<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ThreadMesh\Imap\Client\WebklexImapGateway;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Query\WhereQuery;

final class WebklexUidQueryTest extends TestCase
{
    public function testUidRangeIsGeneratedWithoutQuotes(): void
    {
        $manager = new ClientManager([
            'options' => ['sequence' => IMAP::ST_UID],
        ]);
        $client = $manager->make([
            'host' => 'imap.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'protocol' => 'imap',
            'username' => 'user@example.test',
            'password' => 'not-a-real-password',
        ]);
        $query = new WhereQuery($client);

        $method = new ReflectionMethod(WebklexImapGateway::class, 'queryAfterUid');
        $result = $method->invoke(new WebklexImapGateway(), $query, 6614);
        self::assertInstanceOf(WhereQuery::class, $result);

        self::assertSame('UID 6615:*', $result->generate_query());
    }
}
