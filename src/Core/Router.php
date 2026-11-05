<?php
declare(strict_types=1);

namespace App\Core;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

/**
 * Router ligero sobre FastRoute con middleware por ruta.
 * Middleware: alias definidos en App (ej. 'auth', 'guest', 'admin').
 */
final class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];

    public function get(string $path, array|callable $handler, array $mw = []): void
    {
        $this->add(['GET'], $path, $handler, $mw);
    }

    public function post(string $path, array|callable $handler, array $mw = []): void
    {
        $this->add(['POST'], $path, $handler, $mw);
    }

    public function add(array $methods, string $path, array|callable $handler, array $mw = []): void
    {
        $this->routes[] = [$methods, $path, ['handler' => $handler, 'mw' => array_merge($this->groupMiddleware, $mw)]];
    }

    /** Agrupa rutas que comparten middleware */
    public function group(array $mw, callable $fn): void
    {
        $prev = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($prev, $mw);
        $fn($this);
        $this->groupMiddleware = $prev;
    }

    /** @return array{0:int,1:?array,2:array} [estado, ruta, params] */
    public function match(string $method, string $path): array
    {
        $dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as [$methods, $path, $data]) {
                $r->addRoute($methods, $path, $data);
            }
        });
        $info = $dispatcher->dispatch($method, $path);
        return match ($info[0]) {
            Dispatcher::FOUND => [200, $info[1], $info[2]],
            Dispatcher::METHOD_NOT_ALLOWED => [405, null, []],
            default => [404, null, []],
        };
    }
}
