<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThreadMesh\Imap\Client\WebklexImapGateway;
use Webklex\PHPIMAP\Message;

final class MimeFixtureTest extends TestCase
{
    public function testMultipartMessageAndAttachmentMetadataAreNormalized(): void
    {
        $message = Message::fromFile(__DIR__ . '/../Fixtures/multipart.eml');
        $data = (new WebklexImapGateway())->mapMessage($message);

        self::assertSame('MIME fixture', $data->subject);
        self::assertSame('Sender@Example.com', $data->from[0]->address);
        self::assertStringContainsString('Plain body', (string) $data->text);
        self::assertStringContainsString('<p>HTML body</p>', (string) $data->html);
        self::assertSame('report.pdf', $data->attachments[0]->name);
        self::assertSame('application/pdf', $data->attachments[0]->mediaType);
    }
}
