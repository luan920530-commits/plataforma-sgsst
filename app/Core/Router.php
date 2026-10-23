<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\ModuleAccess;
use App\Support\Auth;
use Throwable;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, ?array $roles = [], ?string $module = null): void
    {
        $this->add('GET', $path, $handler, $roles, $module);
    }

    public function post(string $path, array $handler, ?array $roles = [], ?string $module = null): void
    {
        $this->add('POST', $path, $handler, $roles, $module);
    }

    private function add(string $method, string $path, array $handler, ?array $roles, ?string $module): void
    {
        if ($module === null && $roles !== null && array_intersect($roles, ['AdminEmpresa', 'ResponsableSST']) !== []) {
            $module = ModuleAccess::SGST;
        }
        $this->routes[] = compact('method', 'path', 'handler', 'roles', 'module');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDirectory !== '/' && $scriptDirectory !== '.' && $scriptDirectory !== '') {
            $scriptDirectory = rtrim($scriptDirectory, '/');
            if (str_starts_with($path, $scriptDirectory)) {
                $path = substr($path, strlen($scriptDirectory)) ?: '/';
            }
        }
        $path = '/' . trim(rawurldecode($path), '/');

        $allowedMethods = [];
        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([1-9][0-9]*)', $route['path']);
            if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }
            $allowedMethods[] = $route['method'];
            if ($route['method'] !== $method) {
                continue;
            }

            if ($route['roles'] !== null && !Auth::check()) {
                if ($method === 'GET') {
                    $_SESSION['_intended'] = $path;
                }
                flash('warning', 'Inicia sesión para continuar.');
                redirect('/login');
            }
            if ($route['roles'] !== null && $route['roles'] !== [] && !Auth::hasRole($route['roles'])) {
                $this->error(403, 'No tienes permisos para acceder a este módulo.');
            }
            if ($route['module'] !== null && !ModuleAccess::allows($route['module'])) {
                $module = ModuleAccess::current();
                $this->error(403, 'El plan asignado habilita únicamente ' . ModuleAccess::shortLabel($module) . '. Este módulo no está incluido.');
            }

            array_shift($matches);
            [$class, $action] = $route['handler'];
            $controller = new $class();
            if (!method_exists($controller, $action)) {
                $this->error(500, 'La acción solicitada no está disponible.');
            }
            $controller->{$action}(...array_map('intval', $matches));
            return;
        }

        if ($allowedMethods !== []) {
            $this->error(405, 'El método HTTP no está permitido para esta ruta.');
        }
        $this->error(404, 'No encontramos la página que buscas.');
    }

    private function error(int $status, string $message): never
    {
        http_response_code($status);
        $title = match ($status) {
            403 => 'Acceso restringido',
            404 => 'Página no encontrada',
            405 => 'Método no permitido',
            default => 'Error de la aplicación',
        };
        $contentView = BASE_PATH . '/app/Views/errors/error.php';
        $user = Auth::user();
        $config = $GLOBALS['config'];
        require BASE_PATH . '/app/Views/layouts/error.php';
        exit;
    }
}
