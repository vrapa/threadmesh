<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ThreadMesh\Imap\TrashSafety;

final class TrashSafetyTest extends TestCase
{
    public function testUidValidityMismatchStopsMove(): void
    {
        $safety = new TrashSafety();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UIDVALIDITY changed');
        $safety->validateUidValidity(['uidValidity' => 42], 43);
    }

    public function testLegacyEmailRequiresMatchingOriginalHeader(): void
    {
        $safety = new TrashSafety();
        $safety->validateUidValidity(['raw' => 'Message-ID: <original>'], 43);
        $safety->validateRawHeader(['raw' => 'Message-ID: <original>'], 'Message-ID: <original>');
        $this->expectExceptionMessage('headers differ');
        $safety->validateRawHeader(['raw' => 'Message-ID: <original>'], 'Message-ID: <other>');
    }

    public function testLegacyEmailWithoutRawHeaderIsRejected(): void
    {
        $this->expectExceptionMessage('Legacy message lacks data');
        (new TrashSafety())->validateUidValidity([], 42);
    }

    public function testTrashDiscoveryRejectsMissingAndAmbiguousFolders(): void
    {
        $safety = new TrashSafety();
        foreach ([[], [
            'Trash' => ['flags' => ['\\Trash']],
            'OtherTrash' => ['flags' => ['\\Trash']],
        ]] as $listing) {
            try {
                $safety->specialUseTrashPath($listing);
                self::fail('Expected an unsafe Trash folder list to be rejected.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('missing or ambiguous', $error->getMessage());
            }
        }
        self::assertSame('Trash', $safety->specialUseTrashPath(['Trash' => ['flags' => ['\\Trash']]]));
        self::assertSame('Trash', $safety->specialUseTrashPath(['Trash' => ['flags' => ['\\TRASH']]]));
    }

    public function testUnsupportedMoveCapabilityIsRejected(): void
    {
        $safety = new TrashSafety();
        self::assertFalse($safety->supportsMove(['IMAP4rev1', 'UIDPLUS']));
        self::assertTrue($safety->supportsMove(['IMAP4rev1', 'MOVE']));
    }

    public function testUidSearchRequiresTheExactUid(): void
    {
        $safety = new TrashSafety();
        self::assertTrue($safety->uidExists(['41487'], 41487));
        self::assertFalse($safety->uidExists([], 41487));
        self::assertFalse($safety->uidExists(['41488'], 41487));
        self::assertFalse($safety->uidExists(['41487x'], 41487));
    }
}
