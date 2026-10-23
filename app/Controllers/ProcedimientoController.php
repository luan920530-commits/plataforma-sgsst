<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ProcedureLibrary;
use App\Support\Audit;
use InvalidArgumentException;

final class ProcedimientoController extends BaseController
{
    public function index(): void
    {
        $catalog = ProcedureLibrary::catalog($this->tenantId());
        $this->view('tenant/procedimientos/index', [
            'title' => 'Procedimientos y formatos operativos',
            'groups' => $catalog['grupos'],
            'tenant' => $catalog['tenant'],
            'activity' => $catalog['actividad'],
        ]);
    }

    public function generate(): void
    {
        $tenantId = $this->tenantId();
        $kit = (string) $this->input('kit');
        try {
            $created = ProcedureLibrary::generate($tenantId, [$kit]);
        } catch (InvalidArgumentException) {
            $this->abort(403, 'El kit documental solicitado no es válido.');
        }
        Audit::record('Kit de procedimientos y formatos generado', 'Documento', null, ['kit' => $kit, 'documentos_nuevos' => $created]);
        flash($created > 0 ? 'success' : 'warning', $created > 0
            ? $created . ' documento(s) en borrador fueron agregados a la biblioteca. Revísalos y apruébalos después de adaptarlos.'
            : 'Este kit ya se encontraba generado; no se duplicaron documentos.');
        $return = (string) $this->input('return', '/procedimientos');
        redirect(in_array($return, ['/procedimientos', '/pesv'], true) ? $return : '/procedimientos');
    }
}
