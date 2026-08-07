<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

final class MimeHeaderDecoder
{
    public function decode(string $value): string
    {
        $unfolded = preg_replace('/\r?\n[\t ]+/', ' ', $value) ?? $value;
        if (preg_match('/=\\?[^?]+\\?[bq]\\?[^?]*\\?=/i', $unfolded) !== 1) {
            return $unfolded;
        }

        preg_match_all('/=\?[^?]+\?([bq])\?([^?]*)\?=/i', $unfolded, $words, PREG_SET_ORDER);
        foreach ($words as $word) {
            $encoding = strtolower($word[1]);
            $payload = $word[2];
            if ($encoding === 'b' && base64_decode($payload, true) === false) {
                return $unfolded;
            }
            if ($encoding === 'q' && preg_match('/=(?![a-f0-9]{2})/i', $payload) === 1) {
                return $unfolded;
            }
        }
        $decoded = iconv_mime_decode(
            $unfolded,
            ICONV_MIME_DECODE_CONTINUE_ON_ERROR,
            'UTF-8',
        );
        if (!is_string($decoded) || $decoded === '') {
            $decoded = mb_decode_mimeheader($unfolded);
        }

        return $decoded !== '' ? $decoded : $unfolded;
    }
}
