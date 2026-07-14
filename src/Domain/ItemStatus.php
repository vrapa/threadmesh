<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

enum ItemStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';
}
