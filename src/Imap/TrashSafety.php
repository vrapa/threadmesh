<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

use RuntimeException;

final class TrashSafety
{
    /** @param array<string, mixed> $metadata */
    public function validateUidValidity(array $metadata, int $current): void
    {
        if ($current < 1) {
            throw new RuntimeException('IMAP did not report UIDVALIDITY.');
        }
        $stored = $metadata['uidValidity'] ?? null;
        if (is_int($stored)) {
            if ($stored !== $current) {
                throw new RuntimeException('IMAP UIDVALIDITY changed; the message cannot be moved safely.');
            }
            return;
        }
        if (!is_string($metadata['raw'] ?? null) || $metadata['raw'] === '') {
            throw new RuntimeException('Legacy message lacks data required to verify its IMAP identity.');
        }
    }

    /** @param array<string, mixed> $metadata */
    public function validateRawHeader(array $metadata, ?string $current): void
    {
        $stored = $metadata['raw'] ?? null;
        if (is_string($stored) && $stored !== '' && $stored !== $current) {
            throw new RuntimeException('IMAP message headers differ from the stored message.');
        }
    }

    /** @param array<mixed> $listing Raw IMAP LIST response. */
    public function specialUseTrashPath(array $listing): string
    {
        $paths = [];
        foreach ($listing as $path => $attributes) {
            $flags = is_array($attributes) ? ($attributes['flags'] ?? null) : null;
            if (!is_string($path) || !is_array($flags)) {
                continue;
            }
            foreach ($flags as $flag) {
                if (is_string($flag) && strcasecmp($flag, '\\Trash') === 0) {
                    $paths[] = $path;
                    break;
                }
            }
        }
        if (count($paths) !== 1) {
            throw new RuntimeException('IMAP Trash folder is missing or ambiguous; configure trashFolder for this account.');
        }
        return $paths[0];
    }

    /** @param array<mixed> $capabilities */
    public function supportsMove(array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (is_string($capability) && strtoupper($capability) === 'MOVE') {
                return true;
            }
        }
        return false;
    }

    /** @param array<mixed> $foundUids */
    public function uidExists(array $foundUids, int $uid): bool
    {
        foreach ($foundUids as $foundUid) {
            if ((is_int($foundUid) || is_string($foundUid)) && (string) $foundUid === (string) $uid) {
                return true;
            }
        }
        return false;
    }
}
