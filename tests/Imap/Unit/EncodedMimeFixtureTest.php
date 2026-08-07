<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThreadMesh\Imap\Client\WebklexImapGateway;
use Webklex\PHPIMAP\Message;

final class EncodedMimeFixtureTest extends TestCase
{
    public function testGatewayNormalizesEncodedSubjectAndSenderName(): void
    {
        $message = Message::fromFile(__DIR__ . '/../Fixtures/encoded-headers.eml');
        $data = (new WebklexImapGateway())->mapMessage($message);

        self::assertSame('Pracovní nabídka', $data->subject);
        self::assertSame('Jan Novák', $data->from[0]->name);
    }
}
