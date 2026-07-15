<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

final class ConnectionTestResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly string $message,
    ) {
    }

    public static function success(string $message = 'Connection succeeded.'): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
