<?php

namespace App\Support;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    private string $default;

    public function __construct(string $default = 'dashboard')
    {
        $this->default = $default;
    }

    public function get(string $page, callable $handler): void
    {
        $this->routes[$page]['GET'] = $handler;
    }

    public function post(string $page, callable $handler): void
    {
        $this->routes[$page]['POST'] = $handler;
    }

    public function dispatch(): void
    {
        $page = $_GET['page'] ?? $this->default;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!isset($this->routes[$page][$method])) {
            http_response_code(404);
            View::render('errors/404', ['page' => $page]);
            return;
        }

        if ($method === 'POST') {
            Csrf::verify($_POST['_csrf'] ?? null);
        }

        ($this->routes[$page][$method])();
    }
}
