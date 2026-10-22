<?php
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Calculadora;
use App\Models\Analisis;
use App\Models\Empresa;

class AnalisisController extends BaseController
{
  /**
     * POST /analisis
     * Crea un nuevo análisis para una empresa y redirige al wizard.
     */
    public function crear(): void
    {
        $this->verifyCsrf();

        $empresaId = (int) ($_POST['empresa_id'] ?? 0);
        if (!$empresaId) {
            flash('error', 'Empresa no especificada');
            $this->redirect(base_url('empresas'));
        }

        // Verificar que la empresa pertenece al usuario
        $empresa = Empresa::buscar($empresaId);
        if (!$empresa || $empresa['usuario_id'] != Auth::id()) {
            flash('error', 'Empresa no encontrada');
            $this->redirect(base_url('empresas'));
        }

        // Calcular versión: siguiente número respecto al último análisis
        $ultimo = Analisis::ultimoPorEmpresa($empresaId);
        $version = $ultimo ? (string) ((int) $ultimo['version'] + 1) : '1';

        $analisisId = Analisis::crear($empresaId, [
            'codigo_documento'  => 'SST-F-1',
            'version'           => $version,
            'fecha_elaboracion' => date('Y-m-d'),
            'estado'            => 'borrador',
        ]);

        flash('success', "Análisis v{$version} creado. Empecemos.");
        $this->redirect(base_url("analisis/{$analisisId}/wizard/paso1"));
    }
    /**
 * GET /analisis
 * Lista todos los análisis del usuario.
 */
public function index(): void
{
    $analisis = Database::query(
        'SELECT a.*,
                e.razon_social, e.nit, e.num_trabajadores,
                (SELECT COUNT(*) FROM amenazas am
                 WHERE am.analisis_id = a.id AND am.aplica = 1) AS total_amenazas,
                (SELECT COUNT(*) FROM documentos d
                 WHERE d.analisis_id = a.id) AS total_documentos
         FROM analisis a
         JOIN empresas e ON e.id = a.empresa_id
         WHERE e.usuario_id = ?
         ORDER BY a.created_at DESC',
        [Auth::id()]
    );

    // KPIs
    $totalAnalisis  = count($analisis);
    $totalBorrador  = count(array_filter($analisis, fn($a) => $a['estado'] === 'borrador'));
    $totalGenerados = count(array_filter($analisis, fn($a) => $a['estado'] === 'generado'));
    $totalCompletos = count(array_filter($analisis, fn($a) => $a['estado'] === 'completo'));

    $this->view('analisis/index', [
        'titulo'         => 'Análisis',
        'analisis'       => $analisis,
        'totalAnalisis'  => $totalAnalisis,
        'totalBorrador'  => $totalBorrador,
        'totalGenerados' => $totalGenerados,
        'totalCompletos' => $totalCompletos,
        'csrf'           => $this->csrfToken(),
    ]);
}
    public function paso1(string $id): void
    {
        $analisis = $this->obtenerAnalisis((int) $id);
        $this->view('wizard/paso1_empresa', [
            'titulo'     => 'Paso 1 — Datos de empresa',
            'analisis'   => $analisis,
            'empresa'    => $analisis,
            'analisisId' => (int) $id,
            'paso'       => 1,
            'csrf'       => $this->csrfToken(),
        ]);
    }

    public function guardarPaso1(string $id): void
    {
        $this->verifyCsrf();
        // Aquí iría la lógica real de actualización
        $this->redirect(base_url("analisis/{$id}/wizard/paso2"));
    }

    public function paso3(string $id): void
    {
        $analisisId = (int) $id;
        $analisis   = $this->obtenerAnalisis($analisisId);

        $amenazas = Database::query(
            'SELECT * FROM amenazas WHERE analisis_id = ? ORDER BY id',
            [$analisisId]
        );

        // Si no hay, sembrar las por defecto
        if (empty($amenazas)) {
            $this->seedAmenazas($analisisId);
            $amenazas = Database::query(
                'SELECT * FROM amenazas WHERE analisis_id = ? ORDER BY id',
                [$analisisId]
            );
        }

        $this->view('wizard/paso3_amenazas', [
            'titulo'     => 'Paso 3 — Amenazas',
            'analisis'   => $analisis,
            'amenazas'   => $amenazas,
            'analisisId' => $analisisId,
            'paso'       => 3,
            'csrf'       => $this->csrfToken(),
        ]);
    }
    public function guardarPaso3(string $id): void
    {
        $this->verifyCsrf();

        $analisisId = (int) $id;
        $analisis   = $this->obtenerAnalisis($analisisId);

        // Cargar todas las amenazas del análisis
        $amenazas = Database::query(
            'SELECT * FROM amenazas WHERE analisis_id = ?',
            [$analisisId]
        );

        // Marcar/desmarcar según checkboxes enviados
        // El formulario envía: amenaza_aplica[ID] = "1" o nada
        $aplican = $_POST['amenaza_aplica'] ?? [];

        foreach ($amenazas as $a) {
            $amenazaId = (int) $a['id'];
            $aplica    = isset($aplican[$amenazaId]) ? 1 : 0;
            $origen    = $_POST['origen'][$amenazaId] ?? $a['origen'];

            Database::execute(
                'UPDATE amenazas SET aplica = ?, origen = ? WHERE id = ?',
                [$aplica, $origen, $amenazaId]
            );
        }

        flash('success', 'Amenazas guardadas. Ahora responde el cuestionario.');
        $this->redirect(base_url("analisis/{$analisisId}/wizard/paso4"));
    }
    public function paso4(string $id): void
{
    $analisisId = (int) $id;
    $analisis   = $this->obtenerAnalisis($analisisId);

    // Cargar amenazas
    $amenazas = Database::query(
        'SELECT * FROM amenazas WHERE analisis_id = ? AND aplica = 1 ORDER BY id',
        [$analisisId]
    );

    if (empty($amenazas)) {
        $this->seedAmenazas($analisisId);
        $amenazas = Database::query(
            'SELECT * FROM amenazas WHERE analisis_id = ? AND aplica = 1 ORDER BY id',
            [$analisisId]
        );
    }

    // Asegurar que cada amenaza tenga sus preguntas cargadas
    foreach ($amenazas as $a) {
        $this->seedPreguntas((int) $a['id'], $a['nombre']);
    }

    // Determinar amenaza activa (la primera o la que venga por query)
    $amenazaActivaId = (int) ($_GET['amenaza'] ?? ($amenazas[0]['id'] ?? 0));

    // Cargar respuestas de la amenaza activa
    $datos = null;
    if ($amenazaActivaId) {
        $datos = $this->cargarCuestionario($amenazaActivaId);
    }

    $this->view('wizard/paso4_vulnerabilidad', [
        'titulo'         => 'Paso 4 — Análisis de vulnerabilidad',
        'analisis'       => $analisis,
        'amenazas'       => $amenazas,
        'amenazaActivaId'=> $amenazaActivaId,
        'datos'          => $datos,
        'analisisId'     => $analisisId,
        'paso'           => 4,
        'csrf'           => $this->csrfToken(),
    ]);
}

/**
 * Carga el cuestionario de una amenaza agrupado por categorías.
 */
private function cargarCuestionario(int $amenazaId): array
{
    $amenaza = Database::first('SELECT * FROM amenazas WHERE id = ?', [$amenazaId]);
    if (!$amenaza) return [];

    $respuestas = Database::query(
        'SELECT * FROM respuestas WHERE amenaza_id = ? ORDER BY formato, orden, id',
        [$amenazaId]
    );

    // Agrupar por categoría manteniendo orden
    $categorias = [];
    foreach ($respuestas as $r) {
        $cat = $r['categoria'];
        if (!isset($categorias[$cat])) {
            $categorias[$cat] = [
                'nombre'    => $cat,
                'formato'   => (int) $r['formato'],
                'preguntas' => [],
            ];
        }
        $categorias[$cat]['preguntas'][] = [
            'id'           => (int) $r['id'],
            'texto'        => $r['pregunta'],
            'respuesta'    => $r['respuesta'],
            'calificacion' => $r['calificacion'] !== null ? (float) $r['calificacion'] : null,
            'observacion'  => $r['observacion'],
        ];
    }

    return [
        'id'         => (int) $amenaza['id'],
        'nombre'     => $amenaza['nombre'],
        'categorias' => array_values($categorias),
    ];
}

    public function guardarPaso4(string $id): void
    {
        $this->verifyCsrf();
        $this->redirect(base_url("analisis/{$id}/wizard/paso5"));
    }

    public function paso6(string $id): void
    {
        $analisisId = (int) $id;
        $analisis   = $this->obtenerAnalisis($analisisId);

        // 🔄 RECALCULAR TODAS las amenazas antes de mostrar la matriz
        $amenazasBrutas = Database::query(
            'SELECT id FROM amenazas WHERE analisis_id = ? AND aplica = 1',
            [$analisisId]
        );
        foreach ($amenazasBrutas as $a) {
            Calculadora::recalcularAmenaza((int) $a['id']);
        }

        // Ahora sí, cargar los datos ya actualizados
        $amenazas = Database::query(
            'SELECT * FROM amenazas WHERE analisis_id = ? AND aplica = 1 ORDER BY id',
            [$analisisId]
        );

        $alertas = \App\Validador::validar($analisisId);

        $this->view('wizard/paso6_revision', [
            'titulo'     => 'Paso 6 — Revisión',
            'analisis'   => $analisis,
            'amenazas'   => $amenazas,
            'alertas'    => $alertas,
            'analisisId' => $analisisId,
            'paso'       => 6,
            'csrf'       => $this->csrfToken(),
        ]);
    }

    /** POST /analisis/{id}/recalcular */
    public function recalcular(string $id): void
    {
        $this->verifyCsrf();
        $analisis = $this->obtenerAnalisis((int) $id);
        foreach (Database::query('SELECT id FROM amenazas WHERE analisis_id = ?', [(int) $id]) as $a) {
            Calculadora::recalcularAmenaza((int) $a['id']);
        }
        flash('success', 'Análisis recalculado');
        $this->redirect(base_url("analisis/{$id}/wizard/paso6"));
    }

    public function guardarRespuesta(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $id   = (int) ($body['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido']);
            return;
        }

        $calificacion = Calculadora::calificar($body['respuesta'] ?? null);

        // 1. Guardar la respuesta
        Database::execute(
            'UPDATE respuestas SET respuesta = ?, calificacion = ?, observacion = ? WHERE id = ?',
            [
                $body['respuesta']   ?? null,
                $calificacion,
                $body['observacion'] ?? null,
                $id,
            ]
        );

        // 2. Obtener el amenaza_id y verificar que pertenece al usuario (anti-IDOR)
        $respuesta = Database::first(
            'SELECT r.amenaza_id FROM respuestas r
             JOIN amenazas am ON am.id = r.amenaza_id
             JOIN analisis a ON a.id = am.analisis_id
             JOIN empresas e ON e.id = a.empresa_id
             WHERE r.id = ? AND e.usuario_id = ?',
            [$id, \App\Auth::id()]
        );
        if (!$respuesta && Database::first('SELECT id FROM respuestas WHERE id = ?', [$id])) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }
        if ($respuesta) {
            // 3. Recalcular promedios de esa amenaza
            Calculadora::recalcularAmenaza((int) $respuesta['amenaza_id']);
        }

        echo json_encode(['ok' => true, 'calificacion' => $calificacion]);
    }
    public function promedios(string $id): void
    {
        $propia = Database::first(
            'SELECT am.id FROM amenazas am JOIN analisis a ON a.id = am.analisis_id
             JOIN empresas e ON e.id = a.empresa_id WHERE am.id = ? AND e.usuario_id = ?',
            [(int) $id, \App\Auth::id()]
        );
        if (!$propia) { $this->json(['error' => 'No autorizado'], 403); }
        Calculadora::recalcularAmenaza((int) $id);
        $amenaza = Database::first('SELECT * FROM amenazas WHERE id = ?', [(int) $id]);
        $this->json($amenaza);
    }

    private function obtenerAnalisis(int $id): array
    {
        $analisis = Database::first(
            'SELECT a.*, e.razon_social, e.nit, e.num_trabajadores
             FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE a.id = ? AND e.usuario_id = ?',
            [$id, Auth::id()]
        );

        if (!$analisis) {
            flash('error', 'Análisis no encontrado');
            $this->redirect(base_url('empresas'));
        }

        return $analisis;
    }

    private function seedAmenazas(int $analisisId): void
  {
      $amenazas = [
          ['natural',   'Sismo',                'externa', 'AMARILLO'],
          ['natural',   'Vendaval',             'externa', 'AMARILLO'],
          ['natural',   'Inundación',           'externa', 'AMARILLO'],
          ['tecnico',   'Incendio técnico',     'interna', 'AMARILLO'],
          ['tecnico',   'Colapso estructural',  'interna', 'AMARILLO'],
          ['tecnico',   'Explosión',            'interna', 'AMARILLO'],
          ['social',    'Hurto',                'externa', 'AMARILLO'],
          ['social',    'Desórdenes civiles',   'externa', 'AMARILLO'],
          ['social',    'Atentado / Terrorismo','externa', 'AMARILLO'],
          ['biologico', 'COVID-19',             'externa', 'AMARILLO'],
          ['biologico', 'Contaminación biológica','interna','AMARILLO'],
      ];

      foreach ($amenazas as [$tipo, $nombre, $origen, $color]) {
          Database::execute(
              'INSERT INTO amenazas (analisis_id, tipo, nombre, origen, calificacion_color)
               VALUES (?, ?, ?, ?, ?)',
              [$analisisId, $tipo, $nombre, $origen, $color]
          );
      }
  }
  /**
 * Puebla la tabla `respuestas` con las preguntas del cuestionario
 * para una amenaza dada.
 */
private function seedPreguntas(int $amenazaId, string $nombreAmenaza): void
{
    // Ya existen respuestas para esta amenaza?
    $existe = Database::first(
        'SELECT COUNT(*) c FROM respuestas WHERE amenaza_id = ?',
        [$amenazaId]
    )['c'];

    if ((int) $existe > 0) return;

    $cuestionario = self::plantillaCuestionario($nombreAmenaza);

    $orden = 0;
    foreach ($cuestionario as $formato => $categorias) {
        foreach ($categorias as $categoria => $preguntas) {
            foreach ($preguntas as $pregunta) {
                $orden++;
                Database::execute(
                    'INSERT INTO respuestas
                     (amenaza_id, formato, categoria, pregunta, respuesta, calificacion, orden)
                     VALUES (?, ?, ?, ?, NULL, NULL, ?)',
                    [$amenazaId, $formato, $categoria, $pregunta, $orden]
                );
            }
        }
    }
}

/**
 * Plantilla base del cuestionario. Se personaliza ligeramente
 * según la amenaza (ej: Sismo pregunta por sismo, Hurto por hurto).
 */
public static function plantillaCuestionario(string $amenaza): array
{
    $a = strtolower($amenaza);

    return [
        // ===== FORMATO 2 — PERSONAS =====
        2 => [
            '1. GESTIÓN ORGANIZACIONAL' => [
                '¿Está conformada la brigada de emergencia?',
                "¿Se cuenta con apoyo externo en caso de {$a}?",
                '¿Existe y se mantiene actualizado el Plan de Prevención, Preparación y Respuesta ante Emergencias?',
                "¿Existe personal para realizar reparaciones a las instalaciones en caso de {$a}?",
            ],
            '2. CAPACITACIÓN Y ENTRENAMIENTO' => [
                '¿Está capacitada la brigada de emergencia en evacuación y primeros auxilios?',
                "¿Está capacitado todo el personal de la empresa con el procedimiento operativo en caso de {$a}?",
            ],
            '3. DOTACIÓN' => [
                '¿La empresa cuenta con la cantidad suficiente de camillas, botiquín de emergencia y extintores?',
                '¿Se cuenta con elementos de protección suficientes y adecuados para el personal en sus actividades de rutina?',
            ],
        ],

        // ===== FORMATO 3 — RECURSOS =====
        3 => [
            '1. SUMINISTROS' => [
                '¿Los materiales de la estructura de la empresa son resistentes?',
                '¿Los materiales de la estructura son de buena calidad y de primera clase?',
            ],
            '2. EDIFICACIÓN Y SEÑALIZACIÓN' => [
                '¿Se tuvieron en cuenta las especificaciones técnicas en la construcción?',
                '¿Existe una ruta de evacuación segura y señalizada?',
                '¿Las escaleras de emergencia están en buen estado, con pasamanos, señalización y antideslizantes?',
                '¿Se tiene identificado el punto de encuentro?',
                '¿Las ventanas cuentan con película de seguridad?',
            ],
            '3. EQUIPOS' => [
                '¿Se cuenta con algún sistema de alarma en caso de emergencia?',
                '¿Se cuenta con un sistema de comunicaciones internas para la respuesta a emergencias?',
                '¿Se cuenta con medios de transporte para el apoyo logístico en una emergencia?',
                '¿Existe un sistema de comunicación efectivo para solicitar apoyo externo?',
            ],
        ],

        // ===== FORMATO 4 — SISTEMAS Y PROCESOS =====
        4 => [
            '1. SERVICIOS PÚBLICOS' => [
                '¿Los proveedores de servicios públicos son eficaces y eficientes?',
                '¿Se cuenta con buen servicio de telefonía móvil?',
                '¿Se cuenta con servicio de comunicaciones internas?',
            ],
            '2. SISTEMAS ALTERNOS' => [
                '¿Existe posibilidad de seguir operando en otro lugar?',
                '¿Se cuenta con planta eléctrica?',
                '¿Se cuenta con suministro de agua alterno?',
            ],
            '3. RECUPERACIÓN' => [
                '¿Todos los empleados están afiliados a la seguridad social (EPS, ARL, Fondo de Pensiones)?',
                "¿La empresa está en capacidad de dar continuidad al proceso productivo después de {$a}?",
                '¿Se tienen aseguradas las edificaciones y los bienes en general?',
                '¿Se encuentra asegurada la información digital y análoga de la organización?',
            ],
        ],
    ];
}
}
