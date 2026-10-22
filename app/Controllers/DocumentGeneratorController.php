<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Documento;
use App\Models\Tenant;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Sanitizer;
use App\Support\Validator;

final class DocumentGeneratorController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $filters = [
            'q' => (string) $this->query('q'),
            'fase' => (string) $this->query('fase'),
            'estado' => (string) $this->query('estado'),
        ];
        $this->view('tenant/documentos/index', [
            'title' => 'Documentos del SG-SST',
            'documents' => Documento::forTenant($tenantId, $filters),
            'stats' => Documento::stats($tenantId),
            'filters' => $filters,
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function show(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId);
        if ($document === null) {
            $this->notFound();
        }
        $this->view('tenant/documentos/show', [
            'title' => $document['nombre_documento'],
            'document' => $document,
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId);
        if ($document === null) {
            $this->notFound();
        }
        $content = (string) $this->input('contenido_html');
        $validator = new Validator(['contenido_html' => $content]);
        $validator->required('contenido_html', 'contenido del documento')->max('contenido_html', 'contenido', 300000);
        if ($validator->fails()) {
            fail_validation($validator->errors(), [], '/documentos/' . $id);
        }
        $content = Sanitizer::html($content);
        if (trim(strip_tags($content)) === '') {
            flash('error', 'El documento debe conservar contenido legible.');
            redirect('/documentos/' . $id);
        }
        Documento::saveDraft($id, $tenantId, $content);
        Audit::record('Documento editado; nueva versión en borrador', 'Documento', $id, ['version_anterior' => (int) $document['version']]);
        flash('success', 'Se creó una nueva versión en borrador. Debe revisarse y aprobarse nuevamente.');
        redirect('/documentos/' . $id);
    }

    public function approve(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId);
        if ($document === null) {
            $this->notFound();
        }
        Documento::approve($id, $tenantId, Auth::id());
        Audit::record('Documento aprobado', 'Documento', $id, ['version' => (int) $document['version']]);
        flash('success', 'Documento aprobado. La aprobación quedó registrada en la auditoría.');
        redirect('/documentos/' . $id);
    }

    public function obsolete(int $id): void
    {
        $tenantId = $this->tenantId();
        if (Documento::findForTenant($id, $tenantId) === null) {
            $this->notFound();
        }
        Documento::setObsolete($id, $tenantId);
        Audit::record('Documento marcado como obsoleto', 'Documento', $id);
        flash('success', 'El documento fue marcado como obsoleto.');
        redirect('/documentos/' . $id);
    }

    public function print(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId);
        if ($document === null) {
            $this->notFound();
        }
        $this->view('tenant/documentos/print', [
            'title' => $document['nombre_documento'],
            'document' => $document,
            'tenant' => Tenant::find($tenantId),
        ], 'layouts/print');
    }
}
