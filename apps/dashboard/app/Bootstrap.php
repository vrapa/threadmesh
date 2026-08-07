<?php

declare(strict_types=1);

namespace ThreadMesh\Dashboard;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;

final class Bootstrap
{
    public static function boot(): Container
    {
        $configurator = new Configurator();
        $configurator->setTempDirectory(dirname(__DIR__) . '/temp');
        $configurator->addStaticParameters([
            'threadmeshApiUrl' => (string) getenv('THREADMESH_API_URL'),
            'threadmeshApiToken' => (string) getenv('THREADMESH_API_TOKEN'),
        ]);
        $configurator->addConfig(dirname(__DIR__) . '/config/common.neon');

        return $configurator->createContainer();
    }
}
