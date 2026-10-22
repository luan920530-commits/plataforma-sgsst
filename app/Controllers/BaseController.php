<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Support\Auth;

abstract class BaseController
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        View::render($template, $data, $layout);
    }

    protected function tenantId(): int
    {
        $tenantId = Auth::tenantId();
        if ($tenantId === null) {
            $this->abort(403, 'Esta cuenta no tiene una empresa asociada.');
        }
        return $tenantId;
    }

    protected function input(string $key, mixed $default = ''): mixed
    {
        $value = $_POST[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }
        return is_string($value) ? trim($value) : $value;
    }

    protected function query(string $key, mixed $default = ''): mixed
    {
        $value = $_GET[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }
        return is_string($value) ? trim($value) : $value;
    }

    protected function notFound(string $message = 'El registro solicitado no existe o no pertenece a tu empresa.'): never
    {
        $this->abort(404, $message);
    }

    protected function abort(int $status, string $message): never
    {
        http_response_code($status);
        $title = match ($status) {
            403 => 'Acceso restringido',
            404 => 'No encontramos este registro',
            419 => 'La sesión del formulario venció',
            default => 'No pudimos completar la solicitud',
        };
        $contentView = BASE_PATH . '/app/Views/errors/error.php';
        $user = Auth::user();
        $config = $GLOBALS['config'];
        require BASE_PATH . '/app/Views/layouts/error.php';
        exit;
    }
}
