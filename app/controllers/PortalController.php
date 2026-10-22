<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;
use App\GeneradorPlantilla;
use App\Indicadores;
use App\PlantillasCatalogo;
use App\Sgsst;
use App\SgsstCatalogo;

/**
 * Portal de la empresa cliente: el representante ve la documentación que le exige
 * su nivel de riesgo/tamaño, la corrige en línea (queda en la BD de su empresa),
 * genera e imprime documentos actualizados y recibe reportes de los operarios.
 */
class PortalController extends BaseController
{
    /** Empresa compartida con el usuario (representante o lectura). Devuelve [empresa, permiso]. */
    private function acceso(int $id): array
    {
        $permiso = Sgsst::permiso($id, (int) Auth::id());
        $e = Database::first('SELECT * FROM empresas WHERE id = ?', [$id]);
        if (!$e || $permiso === null || $permiso === 'propietario') {
            flash('error', 'No tienes acceso compartido a esa empresa');
            $this->redirect(base_url('mi-empresa'));
        }
        return [$e, $permiso];
    }

    private function puedeEditar(array $acceso): void
    {
        if ($acceso[1] !== 'representante') {
            flash('error', 'Tu acceso es de solo lectura: pide al consultor el nivel «Representante» para poder corregir información.');
            $this->redirect(base_url("mi-empresa/{$acceso[0]['id']}"));
        }
    }

    /** GET /mi-empresa */
    public function index(): void
    {
        $empresas = Database::query(
            'SELECT e.*, ea.permiso FROM empresa_accesos ea JOIN empresas e ON e.id = ea.empresa_id
             WHERE ea.usuario_id = ? ORDER BY e.razon_social',
            [Auth::id()]
        );
        if (count($empresas) === 1) $this->redirect(base_url("mi-empresa/{$empresas[0]['id']}"));
        $this->view('portal/index', ['titulo' => 'Mi empresa', 'empresas' => $empresas]);
    }

    /** GET /mi-empresa/{id} */
    public function empresa(string $id): void
    {
        [$empresa, $permiso] = $this->acceso((int) $id);
        $eid = (int) $empresa['id'];
        $req = Sgsst::requisitos($empresa);
        $evaluaciones = array_values(array_filter(
            Sgsst::evaluaciones($eid),
            fn($e) => in_array($e['codigo'], $req['codigos'], true)
        ));
        $puntaje = Sgsst::puntaje($evaluaciones);
        $this->view('portal/empresa', [
            'titulo' => 'Mi empresa — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'permiso' => $permiso, 'requisitos' => $req,
            'evaluaciones' => $evaluaciones, 'puntaje' => $puntaje,
            'semaforo' => Sgsst::semaforo($puntaje['total']),
            'alertas' => Sgsst::alertas($eid),
            'actividades' => Database::query("SELECT * FROM sgsst_actividades WHERE empresa_id=? AND estado IN ('pendiente','en_proceso') ORDER BY fecha_vencimiento LIMIT 12", [$eid]),
            'registros' => Database::query('SELECT * FROM sgsst_registros WHERE empresa_id=? ORDER BY fecha DESC LIMIT 30', [$eid]),
            'periodos' => Indicadores::porEmpresa($eid),
            'resumenMatriz' => Database::query('SELECT nr_nivel, COUNT(*) c FROM sgsst_peligros WHERE empresa_id=? GROUP BY nr_nivel', [$eid]),
            'grupos' => PlantillasCatalogo::listar(),
            'generados' => (function () use ($eid) {
                $paquete = Database::query('SELECT * FROM sgsst_documentos WHERE empresa_id=? AND carpeta IS NOT NULL ORDER BY carpeta, nombre_archivo LIMIT 300', [$eid]);
                return $paquete ?: Database::query('SELECT * FROM sgsst_documentos WHERE empresa_id=? ORDER BY created_at DESC LIMIT 30', [$eid]);
            })(),
            'tiposRegistro' => SgsstCatalogo::tiposRegistro(),
            'reporteToken' => Sgsst::tokenReporte($eid),
            'csrf' => $this->csrfToken(),
        ]);
    }

    /** POST /mi-empresa/{id}/datos — el representante corrige los datos de SU empresa */
    public function guardarDatos(string $id): void
    {
        $this->verifyCsrf();
        [$empresa, $permiso] = $this->acceso((int) $id); $this->puedeEditar([$empresa, $permiso]);
        $eid = (int) $empresa['id'];
        $campos = ['razon_social','nit','ciiu','actividad_economica','direccion','ciudad','departamento',
                   'telefono','email','arl','representante_legal','representante_cc','resp_sgsst_nombre','resp_sgsst_cc','areas_alto_riesgo','horarios'];
        $sets = []; $vals = [];
        foreach ($campos as $c) { $sets[] = "{$c} = ?"; $vals[] = trim($_POST[$c] ?? '') ?: null; }
        foreach (['num_trabajadores','aforo_max','num_pisos'] as $c) { $sets[] = "{$c} = ?"; $vals[] = (int)($_POST[$c] ?? 0); }
        $sets[] = 'area_m2 = ?'; $vals[] = (float)($_POST['area_m2'] ?? 0);
        $riesgo = in_array($_POST['nivel_riesgo'] ?? '', ['I','II','III','IV','V'], true) ? $_POST['nivel_riesgo'] : $empresa['nivel_riesgo'];
        $sets[] = 'nivel_riesgo = ?'; $vals[] = $riesgo;
        // Logo de la empresa (encabezado de los documentos)
        if (!empty($_FILES['logo']['name']) && ($_FILES['logo']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
                $dir = BASE_PATH . '/public/uploads/logos';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $nombreLogo = 'empresa_' . $eid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $nombreLogo)) {
                    $sets[] = 'logo_url = ?'; $vals[] = 'uploads/logos/' . $nombreLogo;
                }
            }
        }
        $vals[] = $eid;
        Database::execute('UPDATE empresas SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
        Auditoria::registrar('portal.datos_actualizados', $eid, 'empresa', $eid, 'Representante actualizó los datos de la empresa');
        flash('success', 'Datos actualizados. Los documentos que generes de ahora en adelante saldrán con esta información.');
        $this->redirect(base_url("mi-empresa/{$eid}#datos"));
    }

    /** POST /mi-empresa/{id}/evaluacion */
    public function guardarEvaluacion(string $id): void
    {
        $this->verifyCsrf();
        [$empresa, $permiso] = $this->acceso((int) $id); $this->puedeEditar([$empresa, $permiso]);
        $eid = (int) $empresa['id'];
        foreach (($_POST['estado'] ?? []) as $codigo => $estado) {
            if (!in_array($estado, ['cumple','parcial','no_cumple','no_aplica'], true)) continue;
            Database::execute(
                'UPDATE sgsst_evaluaciones SET estado=?, evidencia=?, observacion=?, actualizado_por=?, updated_at=CURRENT_TIMESTAMP WHERE empresa_id=? AND codigo=?',
                [$estado, trim($_POST['evidencia'][$codigo] ?? '') ?: null, trim($_POST['observacion'][$codigo] ?? '') ?: null, Auth::id(), $eid, (string) $codigo]
            );
        }
        Auditoria::registrar('portal.evaluacion_actualizada', $eid, 'empresa', $eid);
        flash('success', 'Evaluación actualizada: tu porcentaje de cumplimiento se recalculó.');
        $this->redirect(base_url("mi-empresa/{$eid}#cumplimiento"));
    }

    /** POST /mi-empresa/{id}/registros — registros del representante (inspecciones, capacitaciones, etc.) */
    public function crearRegistro(string $id): void
    {
        $this->verifyCsrf();
        [$empresa, $permiso] = $this->acceso((int) $id); $this->puedeEditar([$empresa, $permiso]);
        $eid = (int) $empresa['id'];
        $tipos = SgsstCatalogo::tiposRegistro();
        $tipo = array_key_exists($_POST['tipo'] ?? '', $tipos) ? $_POST['tipo'] : 'otro';
        $titulo = trim($_POST['titulo'] ?? '');
        if ($titulo === '') { flash('error', 'El registro requiere un título'); $this->redirect(base_url("mi-empresa/{$eid}#registros")); }
        Database::execute(
            'INSERT INTO sgsst_registros (empresa_id,tipo,titulo,descripcion,fecha,fecha_vencimiento,responsable,severidad,evidencia,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$eid, $tipo, $titulo, trim($_POST['descripcion'] ?? '') ?: null, ($_POST['fecha'] ?? '') ?: date('Y-m-d'),
             ($_POST['fecha_vencimiento'] ?? '') ?: null, trim($_POST['responsable'] ?? '') ?: null,
             in_array($_POST['severidad'] ?? '', ['baja','media','alta','critica'], true) ? $_POST['severidad'] : 'media',
             trim($_POST['evidencia'] ?? '') ?: null, Auth::id()]
        );
        Auditoria::registrar('portal.registro_creado', $eid, 'registro', Database::lastId(), $titulo);
        flash('success', 'Registro guardado en el SG-SST de tu empresa.');
        $this->redirect(base_url("mi-empresa/{$eid}#registros"));
    }

    /** POST /mi-empresa/{id}/documentos/generar — documento diligenciado actualizado, listo para descargar/imprimir */
    public function generarDocumento(string $id): void
    {
        $this->verifyCsrf();
        [$empresa, $permiso] = $this->acceso((int) $id); $this->puedeEditar([$empresa, $permiso]);
        $eid = (int) $empresa['id'];
        $ruta = PlantillasCatalogo::resolver((string) ($_POST['f'] ?? ''));
        if (!$ruta || !in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['docx','xlsx'], true)) {
            flash('error', 'Documento no disponible para generación automática');
            $this->redirect(base_url("mi-empresa/{$eid}#documentos"));
        }
        // Se lee la empresa FRESCA de la BD: el documento sale con los datos corregidos
        $fresca = Database::first('SELECT * FROM empresas WHERE id = ?', [$eid]) ?: $empresa;
        try { $res = GeneradorPlantilla::generar($ruta, $fresca, $eid); }
        catch (\Throwable $e) { flash('error', 'No se pudo generar: ' . $e->getMessage()); $this->redirect(base_url("mi-empresa/{$eid}#documentos")); }
        Database::execute(
            'INSERT INTO sgsst_documentos (empresa_id, plantilla, nombre_archivo, ruta, hash_sha256, generado_por) VALUES (?,?,?,?,?,?)',
            [$eid, basename($ruta), $res['nombre'], $res['ruta'], hash_file('sha256', $res['ruta']), Auth::id()]
        );
        Auditoria::registrar('portal.documento_generado', $eid, 'sgsst_documento', Database::lastId(), $res['nombre']);
        flash('success', 'Documento actualizado generado. Descárgalo de la lista para revisarlo, firmarlo e imprimirlo.');
        $this->redirect(base_url("mi-empresa/{$eid}#documentos"));
    }
}
