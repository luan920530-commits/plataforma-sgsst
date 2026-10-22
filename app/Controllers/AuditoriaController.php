<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Auditoria;
use App\Models\Tenant;

final class AuditoriaController extends BaseController
{
    public function index(): void
    {
        $tenantId = (int) $this->query('tenant_id', 0);
        $action = (string) $this->query('accion');
        $this->view('admin/auditoria', [
            'title' => 'Auditoría general del SaaS',
            'entries' => Auditoria::all($tenantId > 0 ? $tenantId : null, $action),
            'tenants' => Tenant::all(),
            'actions' => Auditoria::actions(),
            'tenantId' => $tenantId,
            'action' => $action,
        ]);
    }
}
