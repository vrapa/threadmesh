<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

use ThreadMesh\Domain\Item;

interface ItemRepository
{
    /** Upserts by the item's stable SourceReference. */
    public function upsert(Item $item): void;
}
