<?php

declare(strict_types=1);

use Nette\Application\Application;
use ThreadMesh\Dashboard\Bootstrap;

ini_set('display_errors', '0');

require dirname(__DIR__) . '/vendor/autoload.php';

header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; frame-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

Bootstrap::boot()->getByType(Application::class)->run();
