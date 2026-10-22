<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BibliotecaRespuesta;
use App\Models\Documento;
use App\Services\ExcelCalculator;
use App\Services\LibraryOriginal;
use App\Services\SgsstLibrary;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Sanitizer;
use App\Support\Validator;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class BibliotecaController extends BaseController
{
    public function index(): void
    {
        $filters = ['q' => (string) $this->query('q'), 'tipo' => (string) $this->query('tipo'), 'estandar' => (int) $this->query('estandar', 0)];
        if (!in_array($filters['tipo'], ['', 'document', 'form'], true)) $filters['tipo'] = '';
        if ($filters['estandar'] < 0 || $filters['estandar'] > 60) $filters['estandar'] = 0;
        $tenantId = $this->tenantId();
        $this->view('tenant/biblioteca/index', [
            'title' => 'Biblioteca SG-SST',
            'items' => SgsstLibrary::filter($filters),
            'filters' => $filters,
            'stats' => SgsstLibrary::stats(),
            'generated' => Documento::libraryCounts($tenantId),
            'responses' => BibliotecaRespuesta::counts($tenantId),
        ]);
    }

    public function show(int $number): void
    {
        $template = SgsstLibrary::find($number);
        if ($template === null) $this->notFound('La plantilla solicitada no existe.');
        if ($template['kind'] === 'document') {
            $this->view('tenant/biblioteca/documento', [
                'title' => $template['title'], 'libraryItem' => $template,
                'content' => Sanitizer::html(SgsstLibrary::content($template)),
                'generated' => Documento::findByLibrary($template['id'], $this->tenantId()),
            ]);
            return;
        }
        $form = SgsstLibrary::form($template);
        $this->view('tenant/biblioteca/formulario', [
            'title' => $template['title'], 'libraryItem' => $template, 'form' => $form,
            'values' => ExcelCalculator::initialValues($form), 'calculated' => [], 'unsupported' => [],
            'response' => null, 'responses' => BibliotecaRespuesta::forTenant($this->tenantId(), $template['id']),
        ]);
    }

    public function generate(int $number): void
    {
        $template = SgsstLibrary::find($number);
        if ($template === null || $template['kind'] !== 'document') $this->notFound('La plantilla documental solicitada no existe.');
        $content = Sanitizer::html(SgsstLibrary::content($template));
        if (trim(strip_tags($content)) === '') $this->abort(500, 'La plantilla no tiene contenido editable; descarga el original para revisarlo.');
        $id = Documento::createOperationalIfMissing([
            'tenant_id' => $this->tenantId(), 'codigo' => $template['code'], 'nombre' => $template['title'],
            'fase' => $template['phase'], 'contenido' => $content, 'tipo' => $template['document_type'], 'biblioteca_id' => $template['id'],
        ]);
        $created = $id !== null;
        $document = $created ? Documento::findForTenant($id, $this->tenantId()) : Documento::findByLibrary($template['id'], $this->tenantId());
        if ($document === null) throw new RuntimeException('No fue posible recuperar el documento generado.');
        if ($created) Audit::record('Documento de biblioteca generado', 'Documento', $id, ['biblioteca' => $template['id']]);
        flash($created ? 'success' : 'info', $created ? 'Documento creado como borrador editable. Revísalo y adáptalo antes de aprobarlo.' : 'Esta plantilla ya había sido generada; se conservó el documento existente.');
        redirect('/documentos/' . $document['id']);
    }

    public function storeResponse(int $number): void
    {
        $template = SgsstLibrary::find($number);
        if ($template === null || $template['kind'] !== 'form') $this->notFound('El formulario solicitado no existe.');
        [$title, $status, $data] = $this->validatedForm($template, '/biblioteca-sgsst/' . $number);
        $id = BibliotecaRespuesta::create([
            'tenant_id' => $this->tenantId(), 'plantilla_id' => $template['id'], 'titulo' => $title,
            'datos_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'estado' => $status, 'created_by' => Auth::id(),
        ]);
        Audit::record('Respuesta de formulario creada', 'BibliotecaRespuesta', $id, ['biblioteca' => $template['id']]);
        $this->flashCalculation($data);
        redirect('/biblioteca-sgsst/respuestas/' . $id);
    }

    public function editResponse(int $id): void
    {
        $response = BibliotecaRespuesta::findForTenant($id, $this->tenantId());
        if ($response === null) $this->notFound();
        $template = SgsstLibrary::findById($response['plantilla_id']);
        if ($template === null || $template['kind'] !== 'form') $this->notFound('La plantilla asociada ya no está disponible.');
        $data = json_decode($response['datos_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->view('tenant/biblioteca/formulario', [
            'title' => $response['titulo'], 'libraryItem' => $template, 'form' => SgsstLibrary::form($template),
            'values' => $data['values'] ?? [], 'calculated' => $data['calculated'] ?? [], 'unsupported' => $data['unsupported'] ?? [],
            'response' => $response, 'responses' => BibliotecaRespuesta::forTenant($this->tenantId(), $template['id']),
        ]);
    }

    public function updateResponse(int $id): void
    {
        $response = BibliotecaRespuesta::findForTenant($id, $this->tenantId());
        if ($response === null) $this->notFound();
        $template = SgsstLibrary::findById($response['plantilla_id']);
        if ($template === null) $this->notFound('La plantilla asociada ya no está disponible.');
        [$title, $status, $data] = $this->validatedForm($template, '/biblioteca-sgsst/respuestas/' . $id);
        BibliotecaRespuesta::update($id, $this->tenantId(), [
            'titulo' => $title, 'datos_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'estado' => $status,
        ]);
        Audit::record('Respuesta de formulario actualizada', 'BibliotecaRespuesta', $id, ['biblioteca' => $template['id']]);
        $this->flashCalculation($data);
        redirect('/biblioteca-sgsst/respuestas/' . $id);
    }

    public function deleteResponse(int $id): void
    {
        $response = BibliotecaRespuesta::findForTenant($id, $this->tenantId());
        if ($response === null) $this->notFound();
        $template = SgsstLibrary::findById($response['plantilla_id']);
        BibliotecaRespuesta::delete($id, $this->tenantId());
        Audit::record('Respuesta de formulario eliminada', 'BibliotecaRespuesta', $id, ['biblioteca' => $response['plantilla_id']]);
        flash('success', 'Respuesta eliminada. La plantilla y el archivo original no fueron modificados.');
        redirect('/biblioteca-sgsst/' . ($template['number'] ?? ''));
    }

    public function original(int $number): void
    {
        $template = SgsstLibrary::find($number);
        if ($template === null) $this->notFound();
        try {
            $path = LibraryOriginal::ensureAvailable($template);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('/biblioteca-sgsst/' . $number);
        }
        $name = basename(str_replace(["\r", "\n", '/', '\\'], '-', $template['original_name']));
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'documento-sgsst.' . $template['extension'];
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: ' . LibraryOriginal::mime($template['extension']));
        header('Content-Length: ' . filesize($path));
        header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private function validatedForm(array $template, string $return): array
    {
        $title = (string) $this->input('titulo', $template['title']);
        $status = (string) $this->input('estado', 'Borrador');
        $validator = new Validator(['titulo' => $title, 'estado' => $status]);
        $validator->required('titulo', 'nombre del registro')->max('titulo', 'nombre del registro', 180)->in('estado', 'estado', ['Borrador', 'Completado']);
        if ($validator->errors() !== []) fail_validation($validator->errors(), ['titulo' => $title], $return);
        $raw = (string) $this->input('datos_json');
        if ($raw === '' || strlen($raw) > 12 * 1024 * 1024) {
            flash('error', 'No se recibió una estructura válida o supera el tamaño permitido.');
            redirect($return);
        }
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($input)) throw new InvalidArgumentException('Estructura no válida.');
            $form = SgsstLibrary::form($template);
            $values = ExcelCalculator::normalizeInput($form, $input);
            $data = ExcelCalculator::calculate($form, $values);
        } catch (JsonException | InvalidArgumentException $exception) {
            flash('error', 'No fue posible validar las celdas: ' . $exception->getMessage());
            redirect($return);
        }
        return [$title, $status, $data];
    }

    private function flashCalculation(array $data): void
    {
        $count = count($data['unsupported'] ?? []);
        flash($count > 0 ? 'warning' : 'success', $count > 0
            ? "Registro guardado. {$count} fórmula(s) no compatibles conservan el resultado del original y requieren revisión."
            : 'Registro guardado y fórmulas compatibles recalculadas. Verifica el resultado antes de usarlo como evidencia.');
    }
}
