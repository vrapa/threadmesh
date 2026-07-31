<?php

declare(strict_types=1);

namespace ThreadMesh\Dashboard\Router;

use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList();
        $router->addRoute('dashboard/email/<id [a-f0-9]+>/content', 'Dashboard:content');
        $router->addRoute('dashboard/email/<id [a-f0-9]+>', 'Dashboard:detail');
        $router->addRoute('dashboard[/]', 'Dashboard:default');

        return $router;
    }
}
