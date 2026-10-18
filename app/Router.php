<?php
namespace App;

class Router
{
    private array $routes = [];
    private string $middleware = '';

    public function get(string $path, string $handler): void
    {
        $this->routes[] = ['GET', $path, $handler, $this->middleware];
    }

    public function post(string $path, string $handler): void
    {
        $this->routes[] = ['POST', $path, $handler, $this->middleware];
    }

    public function group(string $middleware, callable $callback): void
    {
        $prev = $this->middleware;
        $this->middleware = $middleware;
        $callback($this);
        $this->middleware = $prev;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        // Quitar base path (/ppprae/public)
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as [$m, $path, $handler, $mw]) {
            if ($m !== $method) continue;

            $pattern = '#^' . preg_replace('#\{[^}]+\}#', '([^/]+)', $path) . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                if ($mw === 'auth' && !Auth::check()) {
                    redirect(base_url('login'));
                }

                array_shift($matches);
                [$class, $methodName] = explode('@', $handler);
                $fqcn = "App\\Controllers\\{$class}";

                if (!class_exists($fqcn)) {
                    http_response_code(500);
                    die("Controlador no encontrado: {$fqcn}");
                }

                call_user_func_array([new $fqcn(), $methodName], $matches);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/../views/404.php';
    }
}
