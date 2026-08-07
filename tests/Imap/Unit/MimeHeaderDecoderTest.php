<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Imap\MimeHeaderDecoder;

final class MimeHeaderDecoderTest extends TestCase
{
    #[DataProvider('headers')]
    public function testDecodesMimeEncodedWords(string $encoded, string $expected): void
    {
        self::assertSame($expected, (new MimeHeaderDecoder())->decode($encoded));
    }

    /** @return iterable<string, array{string, string}> */
    public static function headers(): iterable
    {
        yield 'quoted printable' => [
            '=?UTF-8?Q?Back_End_Developer_ve_spole=C4=8Dnosti_BJAK?=',
            'Back End Developer ve společnosti BJAK',
        ];
        yield 'base64' => [
            '=?UTF-8?B?VmXFmWVqbsOpIHpha8Ohemt5?=',
            'Veřejné zakázky',
        ];
        yield 'adjacent encoded words' => [
            '=?UTF-8?Q?Upozorn=C4=9Bn=C3=AD_na_novou_praco?= =?UTF-8?Q?vn=C3=AD_p=C5=99=C3=ADle=C5=BEitost?=',
            'Upozornění na novou pracovní příležitost',
        ];
        yield 'folded header' => [
            "=?UTF-8?Q?Prvn=C3=AD?=\r\n =?UTF-8?Q?_=C4=8D=C3=A1st?=",
            'První část',
        ];
        yield 'plain value' => ['Maintenance na FortiADC', 'Maintenance na FortiADC'];
        yield 'malformed value is preserved' => ['=?UTF-8?Q?unfinished', '=?UTF-8?Q?unfinished'];
        yield 'invalid base64 is preserved' => ['=?UTF-8?B?%%%?=', '=?UTF-8?B?%%%?='];
    }
}
