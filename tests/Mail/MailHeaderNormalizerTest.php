<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Mail;

use PHPUnit\Framework\TestCase;
use ThreadMesh\Mail\MailHeaderNormalizer;

final class MailHeaderNormalizerTest extends TestCase
{
    public function testNormalizesPreviouslyStoredMailMetadataForApiConsumers(): void
    {
        $email = (new MailHeaderNormalizer())->email([
            'title' => '=?UTF-8?Q?Pracovn=C3=AD_nab=C3=ADdka?=',
            'author' => [
                'displayName' => '=?UTF-8?B?SmFuIE5vdsOhaw==?=',
                'address' => 'jan@example.test',
            ],
            'recipients' => [[
                'displayName' => '=?UTF-8?Q?Petr_=C4=8Cern=C3=BD?=',
                'address' => 'petr@example.test',
            ]],
            'attachments' => [[
                'name' => '=?UTF-8?Q?p=C5=99=C3=ADloha.pdf?=',
                'mediaType' => 'application/pdf',
            ]],
        ]);

        self::assertSame('Pracovní nabídka', $email['title']);
        $author = $email['author'];
        $recipients = $email['recipients'];
        $attachments = $email['attachments'];
        self::assertIsArray($author);
        self::assertIsArray($recipients);
        self::assertIsArray($attachments);
        self::assertIsArray($recipients[0]);
        self::assertIsArray($attachments[0]);
        self::assertSame('Jan Novák', $author['displayName']);
        self::assertSame('Petr Černý', $recipients[0]['displayName']);
        self::assertSame('příloha.pdf', $attachments[0]['name']);
    }
}
