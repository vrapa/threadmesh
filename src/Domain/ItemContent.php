<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

final class ItemContent
{
    public function __construct(
        public readonly ?string $text = null,
        public readonly ?string $html = null,
    ) {
    }
}
