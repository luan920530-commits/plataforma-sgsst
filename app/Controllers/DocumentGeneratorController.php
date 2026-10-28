<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Documento;
use App\Models\Permission;
use App\Models\Tenant;
use App\Services\DocumentGenerator;
use App\Services\DocumentSignature;
use App\Services\DocumentPackageExporter;
use App\Services\ModuleAccess;
use App\Support\AccessControl;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Sanitizer;
use App\Support\Validator;
use DomainException;

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
        $module = ModuleAccess::current();
        $filters['modulo'] = $module;
        $this->view('tenant/documentos/index', [
            'title' => $module === 'sgsst' ? 'Documentos del SG-SST' : 'Documentos de ' . ModuleAccess::shortLabel($module),
            'documents' => Documento::forTenant($tenantId, $filters),
            'stats' => Documento::stats($tenantId, $module),
            'filters' => $filters,
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function complement(): void
    {
        if (ModuleAccess::current() !== 'sgsst') {
            $this->abort(403, 'El paquete de inicio SG-SST no se carga en un plan exclusivo de otro módulo.');
        }
        $tenantId = $this->tenantId();
        $result = DocumentGenerator::complement($tenantId);
        Audit::record('Paquete documental SG-SST complementado', 'Documento', null, $result);
        $changes = (int) $result['created'] + (int) $result['updated'];
        flash($changes > 0 ? 'success' : 'warning', $changes > 0
            ? $result['created'] . ' documento(s) nuevo(s) y ' . $result['updated'] . ' borrador(es) automático(s) actualizado(s). Los documentos editados o aprobados se conservaron.'
            : 'El paquete ya estaba sincronizado; no se duplicaron documentos ni se modificaron versiones.');
        $return = (string) $this->input('return', '/documentos');
        redirect(in_array($return, ['/documentos', '/procedimientos'], true) ? $return : '/documentos');
    }

    public function downloadPackage(): void
    {
        if (ModuleAccess::current() !== 'sgsst') {
            $this->abort(403, 'La descarga consolidada del paquete de inicio pertenece al módulo SG-SST.');
        }
        $tenantId = $this->tenantId();
        $module = ModuleAccess::current();
        $download = DocumentPackageExporter::package(
            Tenant::find($tenantId),
            Documento::forTenant($tenantId, ['modulo' => $module])
        );
        Audit::record('Paquete documental descargado', 'Documento', null, ['modulo' => $module, 'formato' => $download['format']]);
        $this->sendDownload($download);
    }

    public function download(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId, ModuleAccess::current());
        if ($document === null) {
            $this->notFound();
        }
        $download = DocumentPackageExporter::document($document);
        Audit::record('Documento editable descargado', 'Documento', $id, ['formato' => $download['format']]);
        $this->sendDownload($download);
    }

    public function show(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId, ModuleAccess::current());
        if ($document === null) {
            $this->notFound();
        }
        $this->view('tenant/documentos/show', [
            'title' => $document['nombre_documento'],
            'document' => $document,
            'versions' => Documento::versions($id, $tenantId),
            'signatures' => DocumentSignature::forDocument($id, $tenantId),
            'signatureConsent' => DocumentSignature::consentText(),
            'currentContentHash' => DocumentSignature::contentHash($document),
            'canSign' => AccessControl::can(Permission::DOCUMENT_SIGN),
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function sign(int $id): void
    {
        $this->requirePermission(Permission::DOCUMENT_SIGN, 'Tu cuenta no tiene permiso para firmar documentos.');
        try {
            DocumentSignature::sign($id, $this->tenantId(), Auth::id(), (string) $this->input('password'), $this->input('consentimiento'));
            flash('success', 'Firma electrónica registrada y vinculada a la versión aprobada.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/documentos/' . $id . '#firmas');
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId, ModuleAccess::current());
        if ($document === null) {
            $this->notFound();
        }
        if ($document['estado'] === 'Obsoleto') {
            $this->abort(403, 'Un documento obsoleto se conserva solo para consulta.');
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
        $document = Documento::findForTenant($id, $tenantId, ModuleAccess::current());
        if ($document === null) {
            $this->notFound();
        }
        if ($document['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo un documento en borrador puede aprobarse.');
        }
        Documento::approve($id, $tenantId, Auth::id());
        Audit::record('Documento aprobado', 'Documento', $id, ['version' => (int) $document['version']]);
        flash('success', 'Documento aprobado. La aprobación quedó registrada en la auditoría.');
        redirect('/documentos/' . $id);
    }

    public function obsolete(int $id): void
    {
        $tenantId = $this->tenantId();
        if (Documento::findForTenant($id, $tenantId, ModuleAccess::current()) === null) {
            $this->notFound();
        }
        Documento::setObsolete($id, $tenantId);
        Audit::record('Documento marcado como obsoleto', 'Documento', $id);
        flash('success', 'El documento fue marcado como obsoleto.');
        redirect('/documentos/' . $id);
    }

    public function version(int $id, int $versionId): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId, ModuleAccess::current());
        $version = Documento::findVersionForTenant($versionId, $id, $tenantId);
        if ($document === null || $version === null) {
            $this->notFound();
        }
        $this->view('tenant/documentos/version', [
            'title' => $document['nombre_documento'] . ' · v' . $version['version'],
            'document' => $document,
            'version' => $version,
            'signatures' => DocumentSignature::forDocument($id, $tenantId, (int) $version['version']),
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function print(int $id): void
    {
        $tenantId = $this->tenantId();
        $document = Documento::findForTenant($id, $tenantId, ModuleAccess::current());
        if ($document === null) {
            $this->notFound();
        }
        $this->view('tenant/documentos/print', [
            'title' => $document['nombre_documento'],
            'document' => $document,
            'signatures' => DocumentSignature::forDocument($id, $tenantId, (int) $document['version']),
            'tenant' => Tenant::find($tenantId),
        ], 'layouts/print');
    }

    private function sendDownload(array $download): never
    {
        header('Content-Type: ' . $download['mime']);
        header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
        header('Content-Length: ' . strlen($download['content']));
        header('Cache-Control: no-store, private');
        header('X-Normia-Formato: ' . $download['format']);
        echo $download['content'];
        exit;
    }
}
