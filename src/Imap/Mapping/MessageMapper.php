<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Mapping;

use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\Attachment;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\ItemContent;
use ThreadMesh\Domain\ItemId;
use ThreadMesh\Domain\ItemStatus;
use ThreadMesh\Domain\ItemType;
use ThreadMesh\Domain\SourceReference;
use ThreadMesh\Imap\Client\EmailAddress;
use ThreadMesh\Imap\Client\MessageData;

final class MessageMapper
{
    public function map(string $accountId, string $folderId, MessageData $message): Item
    {
        $author = $message->from[0] ?? new EmailAddress('unknown', 'Unknown sender');
        $externalId = $folderId . ':' . $message->uid;

        return new Item(
            id: new ItemId(hash('sha256', 'imap:' . $accountId . ':' . $externalId)),
            source: new SourceReference('imap', $accountId, $externalId),
            type: ItemType::Email,
            title: trim($message->subject) !== '' ? $message->subject : '(no subject)',
            content: new ItemContent($message->text, $message->html),
            author: $this->mapActor($author),
            status: ItemStatus::New,
            createdAt: $message->date,
            recipients: array_map($this->mapActor(...), [...$message->to, ...$message->cc]),
            threadReferences: array_values(array_unique(array_filter([
                $message->messageId,
                ...$message->references,
            ], static fn (?string $value): bool => $value !== null && trim($value) !== ''))),
            attachments: array_map(
                static fn ($attachment): Attachment => new Attachment(
                    $attachment->partId,
                    $attachment->name,
                    $attachment->mediaType,
                    $attachment->size,
                    $attachment->inline,
                    $attachment->contentId,
                ),
                $message->attachments,
            ),
            metadata: ['folder' => $folderId, 'uid' => $message->uid, ...$message->headers],
        );
    }

    private function mapActor(EmailAddress $address): Actor
    {
        $normalized = strtolower(trim($address->address));
        $displayName = trim((string) $address->name);
        return new Actor(
            $normalized !== '' ? $normalized : 'unknown',
            $displayName !== '' ? $displayName : ($normalized !== '' ? $normalized : 'Unknown'),
            $normalized !== '' ? $normalized : null,
        );
    }
}
