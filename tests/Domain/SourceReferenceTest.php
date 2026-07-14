<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ThreadMesh\Domain\SourceReference;

final class SourceReferenceTest extends TestCase
{
    public function testItBuildsAStableCompositeKey(): void
    {
        $reference = new SourceReference('jira', 'company', 'DEV-123', 'https://example.atlassian.net/browse/DEV-123');
        self::assertSame('jira:company:DEV-123', $reference->key());
    }

    public function testItRejectsAnInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SourceReference('jira', 'company', 'DEV-123', 'not-a-url');
    }
}
