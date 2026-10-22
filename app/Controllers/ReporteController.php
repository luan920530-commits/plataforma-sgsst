<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccesoReporte;
use App\Models\Autoreporte;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class ReporteController extends BaseController
{
    public function index(): void
    {
        $this->view('tenant/reportes', [
            'title' => 'Autoreportes de trabajadores',
            'reports' => Autoreporte::forTenant($this->tenantId()),
        ]);
    }

    public function accesses(): void
    {
        $created = $_SESSION['_new_report_access'] ?? null;
        unset($_SESSION['_new_report_access']);
        $this->view('tenant/reporte-accesos', [
            'title' => 'Accesos compartibles para autoreportes',
            'accesses' => AccesoReporte::forTenant($this->tenantId()),
            'created' => is_array($created) ? $created : null,
            'extraScripts' => $created !== null ? ['js/vendor/qrcode.js'] : [],
        ]);
    }

    public function storeAccess(): void
    {
        $data = [
            'nombre' => (string) $this->input('nombre'),
            'fecha_vencimiento' => (string) $this->input('fecha_vencimiento'),
            'max_usos' => $this->input('max_usos', '0'),
        ];
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre del acceso')->max('nombre', 'nombre del acceso', 180)
            ->required('fecha_vencimiento', 'fecha de vencimiento')->date('fecha_vencimiento', 'fecha de vencimiento')
            ->integer('max_usos', 'límite de usos', 0, 10000);
        $errors = $validator->errors();
        if ($data['fecha_vencimiento'] !== '' && strtotime($data['fecha_vencimiento']) < strtotime('today')) {
            $errors['fecha_vencimiento'] = 'La fecha de vencimiento no puede estar en el pasado.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/autoreportes/accesos');
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $id = AccesoReporte::create([
            'tenant_id' => $this->tenantId(), 'nombre' => $data['nombre'], 'token_hash' => hash('sha256', $token),
            'token_hint' => text_substr($token, 0, 6) . '…' . text_substr($token, -4),
            'expira_at' => $data['fecha_vencimiento'] . ' 23:59:59', 'max_usos' => (int) $data['max_usos'], 'created_by' => Auth::id(),
        ]);
        Audit::record('Acceso externo de autoreportes creado', 'AccesoReporte', $id, ['vencimiento' => $data['fecha_vencimiento'], 'max_usos' => (int) $data['max_usos']]);
        $_SESSION['_new_report_access'] = [
            'id' => $id, 'nombre' => $data['nombre'], 'url' => absolute_url('/reportar?token=' . rawurlencode($token)),
        ];
        flash('success', 'Acceso creado. Copia ahora el enlace o descarga el QR: por seguridad, el token completo no se vuelve a mostrar.');
        redirect('/autoreportes/accesos');
    }

    public function revokeAccess(int $id): void
    {
        $access = AccesoReporte::findForTenant($id, $this->tenantId());
        if ($access === null) {
            $this->notFound();
        }
        AccesoReporte::revoke($id, $this->tenantId());
        Audit::record('Acceso externo de autoreportes revocado', 'AccesoReporte', $id);
        flash('success', 'El acceso fue revocado. El enlace y el QR anteriores ya no permiten enviar reportes.');
        redirect('/autoreportes/accesos');
    }

    public function show(int $id): void
    {
        $report = Autoreporte::findDetailedForTenant($id, $this->tenantId());
        if ($report === null) {
            $this->notFound();
        }
        $this->view('tenant/reporte-show', [
            'title' => 'Autoreporte #' . $id,
            'report' => $report,
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $report = Autoreporte::findForTenant($id, $tenantId);
        if ($report === null) {
            $this->notFound();
        }
        $data = ['estado' => (string) $this->input('estado'), 'respuesta' => (string) $this->input('respuesta')];
        $validator = new Validator($data);
        $validator->required('estado', 'estado')->in('estado', 'estado', ['Recibido', 'En revision', 'Gestionado'])
            ->required('respuesta', 'respuesta de seguimiento')->max('respuesta', 'respuesta', 3000);
        if ($validator->fails()) {
            $redirectTo = $this->input('origen') === 'detalle' ? '/autoreportes/' . $id : '/autoreportes';
            fail_validation($validator->errors(), $data, $redirectTo);
        }
        Autoreporte::updateManagement($id, $tenantId, $data['estado'], $data['respuesta']);
        Audit::record('Autoreporte gestionado', 'Autoreporte', $id, ['estado' => $data['estado']]);
        flash('success', $report['origen'] === 'Enlace externo'
            ? 'Seguimiento registrado. Comunica la respuesta por el canal de contacto indicado, cuando exista.'
            : 'El seguimiento quedó disponible para la persona que realizó el reporte.');
        redirect($this->input('origen') === 'detalle' ? '/autoreportes/' . $id : '/autoreportes');
    }
}
