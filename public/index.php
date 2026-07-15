<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use ThreadMesh\Api\Kernel;
use ThreadMesh\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

$token = getenv('THREADMESH_API_TOKEN');
$kernel = new Kernel(Bootstrap::create(), is_string($token) ? $token : '');
$kernel->handle(Request::createFromGlobals())->send();
