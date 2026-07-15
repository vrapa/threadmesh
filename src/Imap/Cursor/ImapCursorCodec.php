<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Cursor;

use InvalidArgumentException;
use JsonException;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Exception\CursorInvalidException;

final class ImapCursorCodec
{
    public function encode(ImapCursor $cursor): SyncCursor
    {
        $json = json_encode([
            'version' => ImapCursor::FORMAT_VERSION,
            'uidValidity' => $cursor->uidValidity,
            'lastUid' => $cursor->lastUid,
        ], JSON_THROW_ON_ERROR);

        return new SyncCursor(rtrim(strtr(base64_encode($json), '+/', '-_'), '='));
    }

    public function decode(SyncCursor $cursor): ImapCursor
    {
        try {
            $encoded = strtr($cursor->value, '-_', '+/');
            $padding = strlen($encoded) % 4;
            if ($padding !== 0) {
                $encoded .= str_repeat('=', 4 - $padding);
            }
            $json = base64_decode($encoded, true);
            if ($json === false) {
                throw new CursorInvalidException('The IMAP cursor is not valid base64url.');
            }
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($data)
                || ($data['version'] ?? null) !== ImapCursor::FORMAT_VERSION
                || !is_int($data['uidValidity'] ?? null)
                || !is_int($data['lastUid'] ?? null)
            ) {
                throw new CursorInvalidException('The IMAP cursor has an unsupported format.');
            }

            return new ImapCursor($data['uidValidity'], $data['lastUid']);
        } catch (JsonException|InvalidArgumentException $error) {
            throw new CursorInvalidException('The IMAP cursor is malformed.', 0, $error);
        }
    }
}
