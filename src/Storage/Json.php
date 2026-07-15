<?php

declare(strict_types=1);

namespace ThreadMesh\Storage;

use JsonException;
use RuntimeException;

final class Json
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    public static function object(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored JSON is invalid.', 0, $error);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Stored JSON must be an object.');
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Stored JSON object has an invalid key.');
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
