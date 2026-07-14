<?php

declare(strict_types=1);

namespace ThreadMesh\Contract;

/** Marker for an immutable action understood by a connector. */
interface Action
{
    public function name(): string;
}
