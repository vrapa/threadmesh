<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

use RuntimeException;

final class SourceMessageAbsent extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The message is no longer in its recorded IMAP folder. Check Trash or other folders.');
    }
}
