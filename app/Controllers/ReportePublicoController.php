<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\AccesoReporte;
use App\Models\Autoreporte;
use App\Support\Audit;
use App\Support\Validator;
use DomainException;

final class ReportePublicoController extends BaseController
{
    public function show(): void
    {
        $token = (string) $this->query('token');
        $access = AccesoReporte::findByToken($token);
        $success = $_SESSION['_public_report_success'] ?? null;
        unset($_SESSION['_public_report_success']);
        if (is_array($success) && hash_equals((string) $success['token_hash'], hash('sha256', $token))) {
            $this->view('public/reporte-exito', ['title' => 'Reporte recibido', 'folio' => (int) $success['folio'], 'tenantName' => $success['empresa']], 'layouts/public');
            return;
        }
        if (!AccesoReporte::isUsable($access)) {
            $this->unavailable();
        }
        $this->view('public/reporte-form', [
            'title' => 'Autoreporte de seguridad y salud', 'access' => $access, 'token' => $token,
        ], 'layouts/public');
    }

    public function store(): void
    {
        $token = (string) $this->input('token');
        $access = AccesoReporte::findByToken($token);
        if (!AccesoReporte::isUsable($access)) {
            $this->unavailable();
        }
        $returnTo = '/reportar?token=' . rawurlencode($token);
        if ((string) $this->input('sitio_web') !== '') {
            // Campo trampa: respuesta genérica sin revelar si se descartó un envío automatizado.
            flash('success', 'Gracias. La información fue procesada.');
            redirect($returnTo);
        }

        $tokenHash = hash('sha256', $token);
        $attempts = array_values(array_filter($_SESSION['_public_report_attempts'][$tokenHash] ?? [], static fn($time) => (int) $time >= time() - 600));
        if (count($attempts) >= 20 || AccesoReporte::recentUseCount((int) $access['id']) >= 200) {
            $this->rateLimited();
        }

        $anonymous = (string) $this->input('anonimo') === '1';
        $data = [
            'tipo' => (string) $this->input('tipo'), 'descripcion' => (string) $this->input('descripcion'),
            'ubicacion' => (string) $this->input('ubicacion'), 'fecha_evento' => (string) $this->input('fecha_evento'),
            'nombre_externo' => $anonymous ? '' : (string) $this->input('nombre_externo'),
            'contacto_externo' => $anonymous ? '' : (string) $this->input('contacto_externo'),
            'anonimo' => $anonymous ? 1 : 0,
        ];
        $validator = new Validator($data);
        $validator->required('tipo', 'tipo de reporte')->in('tipo', 'tipo', ['Condicion insegura', 'Acto inseguro', 'Incidente', 'Sintoma', 'Sugerencia'])
            ->required('descripcion', 'descripción')->max('descripcion', 'descripción', 3000)
            ->max('ubicacion', 'ubicación', 200)->required('fecha_evento', 'fecha del evento')->date('fecha_evento', 'fecha del evento')
            ->max('nombre_externo', 'nombre', 180)->max('contacto_externo', 'contacto', 250);
        $errors = $validator->errors();
        if (!$anonymous) {
            if ($data['nombre_externo'] === '') {
                $errors['nombre_externo'] = 'Indica tu nombre o selecciona la opción de reporte anónimo.';
            }
        }
        if (text_length($data['descripcion']) < 20) {
            $errors['descripcion'] = 'Describe la condición con al menos 20 caracteres para facilitar su gestión.';
        }
        if ($data['fecha_evento'] !== '' && strtotime($data['fecha_evento']) > strtotime('today')) {
            $errors['fecha_evento'] = 'La fecha del evento no puede estar en el futuro.';
        }
        if ((string) $this->input('acepta_tratamiento') !== '1') {
            $errors['acepta_tratamiento'] = 'Debes confirmar que leíste la nota de tratamiento responsable.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data + ['anonimo' => $anonymous ? '1' : ''], $returnTo);
        }

        try {
            $reportId = Database::transaction(static function () use ($access, $data): int {
                if (!AccesoReporte::consume((int) $access['id'])) {
                    throw new DomainException('El acceso ya no está disponible.');
                }
                return Autoreporte::create($data + [
                    'tenant_id' => (int) $access['tenant_id'], 'user_id' => (int) $access['created_by'],
                    'acceso_reporte_id' => (int) $access['id'], 'origen' => 'Enlace externo',
                ]);
            });
        } catch (DomainException) {
            $this->unavailable();
        }

        $attempts[] = time();
        $_SESSION['_public_report_attempts'][$tokenHash] = $attempts;
        $_SESSION['_public_report_success'] = ['token_hash' => $tokenHash, 'folio' => $reportId, 'empresa' => $access['razonsocial']];
        Audit::record('Autoreporte creado desde enlace externo', 'Autoreporte', $reportId, ['acceso_id' => (int) $access['id'], 'anonimo' => $anonymous], (int) $access['tenant_id']);
        redirect($returnTo);
    }

    private function unavailable(): never
    {
        http_response_code(404);
        $this->view('public/reporte-no-disponible', ['title' => 'Acceso no disponible'], 'layouts/public');
        exit;
    }

    private function rateLimited(): never
    {
        http_response_code(429);
        $this->view('public/reporte-no-disponible', [
            'title' => 'Demasiados envíos', 'message' => 'Se alcanzó temporalmente el límite de envíos. Espera unos minutos o contacta al responsable SG-SST.',
        ], 'layouts/public');
        exit;
    }
}
