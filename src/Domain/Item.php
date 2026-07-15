<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class Item
{
    /**
     * @param list<Actor> $recipients
     * @param list<string> $threadReferences
     * @param list<Attachment> $attachments
     * @param list<string> $labels
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly ItemId $id,
        public readonly SourceReference $source,
        public readonly ItemType $type,
        public readonly string $title,
        public readonly ItemContent $content,
        public readonly Actor $author,
        public readonly ItemStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt = null,
        public readonly array $recipients = [],
        public readonly array $threadReferences = [],
        public readonly array $attachments = [],
        public readonly array $labels = [],
        public readonly array $metadata = [],
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Item title must not be empty.');
        }
        foreach ($labels as $label) {
            if (trim($label) === '') {
                throw new InvalidArgumentException('Item labels must not be empty.');
            }
        }

        foreach ($threadReferences as $reference) {
            if (trim($reference) === '') {
                throw new InvalidArgumentException('Thread references must not be empty.');
            }
        }

    }
}
