<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use RuntimeException;
use Throwable;

/** Cliente HTTPS sin redirecciones, con TLS verificado, límite de respuesta y protección SSRF básica. */
final class ExternalIntegrationClient
{
    private const MAX_RESPONSE_BYTES = 1048576;

    public static function send(array $integration, string $json, string $credential, string $correlation): array
    {
        $endpoint = self::assertPublicEndpoint((string) $integration['endpoint']);
        if (!filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('PHP debe tener allow_url_fopen habilitado para usar este conector HTTPS.');
        }
        $authorization = $integration['autenticacion'] === 'X-API-Key'
            ? 'X-API-Key: ' . $credential
            : 'Authorization: Bearer ' . $credential;
        $headers = "Accept: application/json\r\nContent-Type: application/json\r\nX-Correlation-ID: {$correlation}\r\n{$authorization}\r\nConnection: close\r\n";
        $context = stream_context_create([
            'http' => [
                'method' => (string) $integration['metodo'],
                'header' => $headers,
                'content' => $json,
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $warning = '';
        $started = microtime(true);
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });
        try {
            $body = file_get_contents($endpoint, false, $context, 0, self::MAX_RESPONSE_BYTES + 1);
            $responseHeaders = $http_response_header ?? [];
        } catch (Throwable $exception) {
            $body = false;
            $responseHeaders = [];
            $warning = 'No fue posible completar la conexión TLS/HTTPS.';
        } finally {
            restore_error_handler();
        }
        $duration = (int) round((microtime(true) - $started) * 1000);
        $status = self::statusCode($responseHeaders);
        if ($body === false) {
            $detail = $status !== null ? 'El servidor no entregó un cuerpo de respuesta legible.' : 'Falló la conexión, la resolución segura o la verificación TLS.';
            if ($warning !== '' && str_contains(text_lower($warning), 'timed out')) $detail = 'El proveedor no respondió dentro del tiempo máximo de 15 segundos.';
            return ['status' => $status, 'body' => '', 'error' => $detail, 'duration_ms' => $duration, 'truncated' => false];
        }
        $truncated = strlen($body) > self::MAX_RESPONSE_BYTES;
        if ($truncated) $body = substr($body, 0, self::MAX_RESPONSE_BYTES);
        return ['status' => $status, 'body' => $body, 'error' => '', 'duration_ms' => $duration, 'truncated' => $truncated];
    }

    /** Valida sintaxis sin hacer una solicitud; se usa al configurar y antes de cada ejecución. */
    public static function validateEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if (strlen($endpoint) > 1000 || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new DomainException('El endpoint debe ser una URL HTTPS válida de máximo 1000 caracteres.');
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || text_lower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new DomainException('El conector sólo admite endpoints HTTPS.');
        }
        foreach (['user', 'pass', 'query', 'fragment'] as $part) {
            if (isset($parts[$part])) throw new DomainException('El endpoint no admite credenciales, parámetros de consulta ni fragmentos en la URL.');
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw new DomainException('Por seguridad, el conector genérico sólo admite el puerto HTTPS 443.');
        }
        $host = text_lower(rtrim((string) $parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new DomainException('El endpoint debe corresponder a un servicio externo público, no a una red local.');
        }
        return 'https://' . (str_contains($host, ':') ? '[' . $host . ']' : $host) . ($parts['path'] ?? '/');
    }

    private static function assertPublicEndpoint(string $endpoint): string
    {
        $endpoint = self::validateEndpoint($endpoint);
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) throw new DomainException('No fue posible resolver el proveedor de forma segura. No se realizó la solicitud.');
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new DomainException('La dirección resuelta es privada o reservada. No se realizó la solicitud.');
            }
        }
        return $endpoint;
    }

    private static function statusCode(array $headers): ?int
    {
        $status = null;
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $matches)) $status = (int) $matches[1];
        }
        return $status;
    }
}
