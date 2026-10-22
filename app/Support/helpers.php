<?php
declare(strict_types=1);

use App\Core\Csrf;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function text_substr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length);
    }
    return substr($value, $start, $length);
}

function base_path(): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return ($directory === '/' || $directory === '.' || $directory === '') ? '' : rtrim($directory, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $configured = (string) ($GLOBALS['config']['url'] ?? '');
    if ($configured !== '') {
        return $configured . '/' . ltrim($path, '/');
    }
    $forwardedProto = text_lower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    return ($https ? 'https://' : 'http://') . $host . url($path);
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function fail_validation(array $errors, array $input, string $redirectTo): never
{
    $_SESSION['_errors'] = $errors;
    unset($input['password'], $input['password_confirmation'], $input['_csrf'], $input['current_password']);
    $_SESSION['_old'] = $input;
    redirect($redirectTo);
}

function old(string $key, mixed $default = ''): mixed
{
    global $oldInput;
    return $oldInput[$key] ?? $default;
}

function field_error(string $key): string
{
    global $errors;
    if (empty($errors[$key])) {
        return '';
    }
    return '<p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600">' . e($errors[$key]) . '</p>';
}

function selected(mixed $actual, mixed $expected): string
{
    return (string) $actual === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function money(mixed $amount): string
{
    return '$ ' . number_format((float) $amount, 0, ',', '.');
}

function format_date(?string $date, bool $long = false): string
{
    if ($date === null || $date === '') {
        return 'Sin definir';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return e($date);
    }
    if (!$long) {
        return date('d/m/Y', $timestamp);
    }
    $months = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = text_substr($parts[0] ?? 'U', 0, 1);
    if (count($parts) > 1) {
        $letters .= text_substr($parts[count($parts) - 1], 0, 1);
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($letters) : strtoupper($letters);
}

function excerpt(string $text, int $length = 90): string
{
    $plain = trim(strip_tags($text));
    return text_length($plain) <= $length ? $plain : text_substr($plain, 0, $length - 1) . '…';
}

function role_label(?string $role): string
{
    return match ($role) {
        'SuperAdmin' => 'SuperAdmin',
        'AdminEmpresa' => 'Administrador de empresa',
        'ResponsableSST' => 'Responsable SG-SST',
        'Trabajador' => 'Trabajador / Contratista',
        default => 'Usuario',
    };
}

function status_class(string $status): string
{
    return match ($status) {
        'Activa', 'Activo', 'Aprobado', 'Completado', 'Cerrado', 'Gestionado', 'Realizada', 'Realizado', 'Verificado', 'Bajo' => 'badge-green',
        'En Proceso', 'En revision', 'En gestion', 'Programada', 'Programado', 'Medio' => 'badge-blue',
        'Pendiente', 'Recibido', 'Borrador', 'Alto' => 'badge-amber',
        'Suspendida', 'Obsoleto', 'Cancelada', 'Cancelado', 'Critico', 'Vencido' => 'badge-red',
        default => 'badge-gray',
    };
}

function risk_class(string $risk): string
{
    return match ($risk) {
        'I', 'Bajo' => 'badge-green',
        'II', 'III', 'Medio' => 'badge-amber',
        'IV', 'V', 'Alto', 'Critico' => 'badge-red',
        default => 'badge-gray',
    };
}

function icon(string $name, string $classes = 'h-5 w-5'): string
{
    static $icons;
    $icons ??= require BASE_PATH . '/app/Support/icons.php';
    $path = $icons[$name] ?? $icons['dot'];
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="' . e($classes) . '" aria-hidden="true">' . $path . '</svg>';
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return '/' . trim($path, '/');
}

function nav_active(string $path, bool $exact = false): string
{
    $current = current_path();
    $active = $exact ? $current === $path : ($current === $path || str_starts_with($current, rtrim($path, '/') . '/'));
    return $active ? ' is-active' : '';
}

function is_overdue(array $item): bool
{
    $state = $item['estado'] ?? '';
    $date = $item['fecha_limite'] ?? $item['fecha_programada'] ?? null;
    return $date !== null && !in_array($state, ['Completado', 'Cerrado', 'Realizada', 'Cancelada'], true) && strtotime($date) < strtotime('today');
}
