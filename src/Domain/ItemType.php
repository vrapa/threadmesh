<?php

declare(strict_types=1);

namespace ThreadMesh\Domain;

enum ItemType: string
{
    case Email = 'email';
    case Task = 'task';
    case Comment = 'comment';
    case PullRequest = 'pull_request';
    case Build = 'build';
    case Deployment = 'deployment';
    case Other = 'other';
}
