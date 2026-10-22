<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        $contentView = BASE_PATH . '/app/Views/' . $template . '.php';
        $layoutView = BASE_PATH . '/app/Views/' . $layout . '.php';
        if (!is_file($contentView) || !is_file($layoutView)) {
            throw new \RuntimeException('Vista no encontrada: ' . $template);
        }

        $data['errors'] = $_SESSION['_errors'] ?? [];
        $data['oldInput'] = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);
        extract($data, EXTR_SKIP);
        $config = $GLOBALS['config'];
        $user = \App\Support\Auth::user();
        require $layoutView;
    }
}
