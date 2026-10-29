<?php

declare(strict_types=1);

namespace Lumina;

final class Router
{
    /** @var list<array{0: string, 1: string, 2: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler, bool $csrf = true): void
    {
        $this->routes[] = ['POST', $pattern, $handler, $csrf];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = $path === '/' ? '/' : rtrim($path, '/');
        foreach ($this->routes as $route) {
            [$routeMethod, $pattern, $handler] = $route;
            if ($routeMethod !== $method) {
                continue;
            }
            $regex = '#^' . $this->compile($pattern) . '$#';
            if (preg_match($regex, $path, $matches) !== 1) {
                continue;
            }
            if ($method === 'POST' && ($route[3] ?? true) === true) {
                Csrf::check();
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = $value;
                }
            }
            $handler(...$params);

            return;
        }
        throw new HttpException(404, 'Questa pagina non esiste.');
    }

    private function compile(string $pattern): string
    {
        $quoted = preg_quote($pattern, '#');

        return str_replace(['\{', '\}'], ['(?P<', '>[^/]+)'], $quoted);
    }
}
