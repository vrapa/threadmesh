<?php

declare(strict_types=1);

namespace ThreadMesh\Mail;

use ThreadMesh\Imap\MimeHeaderDecoder;

final class MailHeaderNormalizer
{
    private readonly MimeHeaderDecoder $decoder;

    public function __construct(?MimeHeaderDecoder $decoder = null)
    {
        $this->decoder = $decoder ?? new MimeHeaderDecoder();
    }

    /**
     * @param list<array<string, mixed>> $emails
     * @return list<array<string, mixed>>
     */
    public function emails(array $emails): array
    {
        $normalized = [];
        foreach ($emails as $email) {
            $normalized[] = $this->email($email);
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $email
     * @return array<string, mixed>
     */
    public function email(array $email): array
    {
        foreach (['title', 'subject'] as $field) {
            if (is_string($email[$field] ?? null)) {
                $email[$field] = $this->decoder->decode($email[$field]);
            }
        }
        if (is_array($email['author'] ?? null)) {
            $email['author'] = $this->actor($email['author']);
        }
        if (is_array($email['recipients'] ?? null)) {
            $email['recipients'] = $this->actors($email['recipients']);
        }
        if (is_array($email['attachments'] ?? null)) {
            $attachments = [];
            foreach ($email['attachments'] as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                if (is_string($attachment['name'] ?? null)) {
                    $attachment['name'] = $this->decoder->decode($attachment['name']);
                }
                $attachments[] = $attachment;
            }
            $email['attachments'] = $attachments;
        }
        return $email;
    }

    /**
     * @param array<array-key, mixed> $actors
     * @return list<array<string, mixed>>
     */
    private function actors(array $actors): array
    {
        $normalized = [];
        foreach ($actors as $actor) {
            if (is_array($actor)) {
                $normalized[] = $this->actor($actor);
            }
        }
        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $actor
     * @return array<string, mixed>
     */
    private function actor(array $actor): array
    {
        $normalized = [];
        foreach ($actor as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }
        if (is_string($normalized['displayName'] ?? null)) {
            $normalized['displayName'] = $this->decoder->decode($normalized['displayName']);
        }
        return $normalized;
    }
}
