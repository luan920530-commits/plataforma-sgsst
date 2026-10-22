<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Autoreporte;
use App\Models\Capacitacion;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Induccion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use PDOException;

final class TrabajadorController extends BaseController
{
    public function portal(): void
    {
        $tenantId = $this->tenantId();
        $induction = Induccion::current($tenantId);
        $signature = $induction !== null ? Induccion::signature((int) $induction['id'], $tenantId, Auth::id()) : null;
        $this->view('trabajador/portal', [
            'title' => 'Mi portal de seguridad y salud',
            'tenant' => Tenant::find($tenantId),
            'employee' => Empleado::findByUser(Auth::id(), $tenantId),
            'policies' => Documento::approvedPolicies($tenantId),
            'reports' => Autoreporte::forUser($tenantId, Auth::id()),
            'trainings' => Capacitacion::upcoming($tenantId, 3),
            'induction' => $induction,
            'signature' => $signature,
        ]);
    }

    public function policy(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId);
        if ($document === null || $document['tipo'] !== 'Politica' || $document['estado'] !== 'Aprobado') {
            $this->notFound('Esta política no está publicada en el portal.');
        }
        $this->view('trabajador/policy', [
            'title' => $document['nombre_documento'],
            'document' => $document,
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function storeReport(): void
    {
        $data = [
            'tenant_id' => $this->tenantId(), 'user_id' => Auth::id(), 'tipo' => (string) $this->input('tipo'),
            'descripcion' => (string) $this->input('descripcion'), 'ubicacion' => (string) $this->input('ubicacion'),
            'fecha_evento' => (string) $this->input('fecha_evento'),
        ];
        $validator = new Validator($data);
        $validator->required('tipo', 'tipo de reporte')->in('tipo', 'tipo', ['Condicion insegura', 'Acto inseguro', 'Incidente', 'Sintoma', 'Sugerencia'])
            ->required('descripcion', 'descripción')->max('descripcion', 'descripción', 3000)
            ->max('ubicacion', 'ubicación', 200)->required('fecha_evento', 'fecha del evento')->date('fecha_evento', 'fecha del evento');
        $errors = $validator->errors();
        if (!empty($data['fecha_evento']) && strtotime($data['fecha_evento']) > strtotime('today')) {
            $errors['fecha_evento'] = 'La fecha del evento no puede estar en el futuro.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/portal#reportar');
        }
        $id = Autoreporte::create($data);
        Audit::record('Autoreporte creado desde portal', 'Autoreporte', $id, ['tipo' => $data['tipo']]);
        flash('success', 'Gracias por reportar. El responsable SG-SST podrá hacer seguimiento y dejarte una respuesta en este mismo portal.');
        redirect('/portal');
    }

    public function induction(): void
    {
        $tenantId = $this->tenantId();
        $induction = Induccion::current($tenantId);
        if ($induction === null) {
            $this->notFound('La empresa aún no ha publicado una inducción.');
        }
        $this->view('trabajador/induccion', [
            'title' => 'Inducción general SG-SST',
            'induction' => $induction,
            'signature' => Induccion::signature((int) $induction['id'], $tenantId, Auth::id()),
            'employee' => Empleado::findByUser(Auth::id(), $tenantId),
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function signInduction(): void
    {
        $tenantId = $this->tenantId();
        $induction = Induccion::current($tenantId);
        if ($induction === null) {
            $this->notFound('La empresa aún no ha publicado una inducción.');
        }
        if (Induccion::hasSigned((int) $induction['id'], $tenantId, Auth::id())) {
            flash('warning', 'Esta versión de la inducción ya tiene tu firma registrada.');
            redirect('/portal/induccion');
        }
        $data = [
            'nombre_firmante' => (string) $this->input('nombre_firmante'),
            'documento_firmante' => (string) $this->input('documento_firmante'),
            'current_password' => (string) $this->input('current_password'),
            'aceptacion' => (string) $this->input('aceptacion'),
        ];
        $validator = new Validator($data);
        $validator->required('nombre_firmante', 'nombre del firmante')->max('nombre_firmante', 'nombre', 180)
            ->required('documento_firmante', 'documento del firmante')->max('documento_firmante', 'documento', 40)
            ->required('current_password', 'contraseña actual');
        $errors = $validator->errors();
        if ($data['aceptacion'] !== '1') {
            $errors['aceptacion'] = 'Debes declarar que leíste y comprendiste el contenido para firmar.';
        }
        $user = User::find(Auth::id());
        if ($user === null || !password_verify($data['current_password'], $user['password'])) {
            $errors['current_password'] = 'La contraseña actual no coincide.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/portal/induccion');
        }

        $signedAt = date('Y-m-d H:i:s');
        $evidence = [
            'tenant_id' => $tenantId, 'induccion_id' => (int) $induction['id'], 'version' => (int) $induction['version'],
            'user_id' => Auth::id(), 'nombre' => $data['nombre_firmante'], 'documento' => $data['documento_firmante'],
            'contenido_sha256' => hash('sha256', $induction['contenido_html']), 'firmado_at' => $signedAt,
        ];
        try {
            $signatureId = Induccion::sign($data + [
                'tenant_id' => $tenantId, 'induccion_id' => (int) $induction['id'], 'user_id' => Auth::id(),
                'hash_evidencia' => hash('sha256', json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'firmado_at' => $signedAt,
            ]);
        } catch (PDOException) {
            flash('warning', 'La firma ya se encontraba registrada; no se duplicó la evidencia.');
            redirect('/portal/induccion');
        }
        Audit::record('Inducción firmada', 'FirmaInduccion', $signatureId, ['induccion_id' => (int) $induction['id'], 'version' => (int) $induction['version']]);
        flash('success', 'Firma registrada correctamente. Conserva el comprobante y el hash de evidencia.');
        redirect('/portal/induccion');
    }
}
