<?php
namespace App;

use App\Models\Documento;
use ZipArchive;

class GeneradorPlan
{
    private int $analisisId;
    private array $datos;

    public function __construct(int $analisisId)
    {
        $this->analisisId = $analisisId;
        $this->datos = $this->cargarDatos();
    }

    public function generar(): array
    {
        $config  = require BASE_PATH . '/config/config.php';
        $tmpDir  = $config['storage']['documentos'] . "tmp_{$this->analisisId}_" . time() . '/';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0775, true);

        $rutas = [
            $this->docListaChequeo($tmpDir . '01_Lista_Chequeo.docx'),
            $this->docAnalisisVulnerabilidad($tmpDir . '02_Analisis_Vulnerabilidad.xlsx'),
            $this->docMatrizConsolidada($tmpDir . '03_Matriz_Consolidada.xlsx'),
            $this->docMedevac($tmpDir . '04_MEDEVAC.xlsx'),
            $this->docPlanCompleto($tmpDir . '05_PPPRAE.docx'),
        ];

        $nit       = $this->datos['empresa']['nit'] ?? 'SIN_NIT';
        $fecha     = date('Ymd_His');
        $nombreZip = "PPPRAE_{$nit}_{$fecha}.zip";
        $rutaZip   = $config['storage']['documentos'] . $nombreZip;

        $zip = new ZipArchive();
        $zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($rutas as $ruta) {
            if (file_exists($ruta)) $zip->addFile($ruta, basename($ruta));
        }
        $zip->close();

        foreach ($rutas as $ruta) if (file_exists($ruta)) unlink($ruta);
        @rmdir($tmpDir);

        Documento::crear($this->analisisId, [
            'tipo'           => 'zip',
            'nombre_archivo' => $nombreZip,
            'ruta'           => $rutaZip,
            'generado_por'   => \App\Auth::id(),
        ]);

        return ['ruta' => $rutaZip, 'nombre' => $nombreZip];
    }

    // ============================================================
    // 01 — LISTA DE CHEQUEO
    // ============================================================

    private function docListaChequeo(string $ruta): string
    {
        $d   = new DocxBuilder();
        $emp = $this->datos['empresa'];

        $d->portada([
            'empresa'   => $emp['razon_social'] ?? '',
            'titulo'    => 'LISTA DE CHEQUEO PPPRAE',
            'subtitulo' => 'Verificación de cumplimiento',
            'ciudad'    => ($emp['ciudad'] ?? '') . ' / ' . ($emp['departamento'] ?? ''),
            'fecha'     => date('d/m/Y'),
        ]);

        // ====== INFORMACIÓN GENERAL ======
        $d->heading('Información general', 1);
        $d->lead('Datos básicos de la empresa y su clasificación de riesgo.');

        $d->tabla(
            ['Campo', 'Información'],
            [
                ['Razón social',         $emp['razon_social'] ?? ''],
                ['NIT',                  $emp['nit'] ?? ''],
                ['Actividad económica',  $emp['actividad_economica'] ?? ''],
                ['CIIU',                 $emp['ciiu'] ?? ''],
                ['Dirección',            $emp['direccion'] ?? ''],
                ['Ciudad / Departamento', ($emp['ciudad'] ?? '') . ' / ' . ($emp['departamento'] ?? '')],
                ['Teléfono',             $emp['telefono'] ?? ''],
                ['Correo electrónico',   $emp['email'] ?? ''],
                ['ARL',                  $emp['arl'] ?? ''],
                ['Nivel de riesgo',      $emp['nivel_riesgo'] ?? ''],
                ['Representante legal',  $emp['representante_legal'] ?? ''],
                ['N° trabajadores',      (string) ($emp['num_trabajadores'] ?? 0)],
                ['Aforo máximo',         (string) ($emp['aforo_max'] ?? 0)],
                ['Área total',           ($emp['area_m2'] ?? 0) . ' m²'],
                ['N° de pisos',          (string) ($emp['num_pisos'] ?? 1)],
                ['Horarios',             $emp['horarios'] ?? 'No especificado'],
                ['Áreas de alto riesgo', $emp['areas_alto_riesgo'] ?? 'No especificadas'],
            ],
            ['ancho' => [35, 65]]
        );

        $d->saltoPagina();

        // ====== AMENAZAS ======
        $d->heading('Amenazas identificadas', 1);
        $d->lead('Amenazas de origen natural, técnico, social y biológico evaluadas.');

        $filasAm = [];
        foreach ($this->datos['amenazas'] as $a) {
            $filasAm[] = [
                $a['nombre'] ?? '',
                ucfirst($a['tipo'] ?? ''),
                ucfirst($a['origen'] ?? ''),
                ((int)($a['aplica'] ?? 1) === 1) ? 'Sí' : 'No',
            ];
        }
        $d->tabla(
            ['Amenaza', 'Categoría', 'Origen', 'Aplica'],
            $filasAm ?: [['—', '—', '—', '—']],
            ['ancho' => [40, 25, 20, 15]]
        );

        $d->saltoPagina();

        // ====== INVENTARIO ======
        $d->heading('Inventario de emergencias', 1);
        $d->lead('Recursos disponibles para la atención de emergencias.');

        $d->heading('Extintores', 2);
        $filasExt = [];
        foreach ($this->datos['extintores'] as $e) {
            $filasExt[] = [
                $e['numero'] ?? '',
                $e['ubicacion'] ?? '',
                $e['tipo'] ?? '',
                $e['peso'] ?? '',
                $e['fecha_recarga'] ?? '',
                ((int)($e['cumple'] ?? 1) === 1) ? '✓' : '✗',
            ];
        }
        $d->tabla(
            ['N°', 'Ubicación', 'Tipo', 'Peso', 'Recarga', 'OK'],
            $filasExt ?: [['—', '—', '—', '—', '—', '—']],
            ['ancho' => [8, 25, 15, 12, 20, 10]]
        );

        $d->espacio();

        $d->heading('Botiquín', 2);
        $filasBot = [];
        foreach ($this->datos['botiquin'] as $b) {
            $filasBot[] = [
                $b['elemento'] ?? '',
                (string) ($b['cantidad'] ?? 0),
                $b['fecha_vencimiento'] ?? '',
                ((int)($b['apto_para_uso'] ?? 1) === 1) ? '✓' : '✗',
            ];
        }
        $d->tabla(
            ['Elemento', 'Cantidad', 'Vencimiento', 'Apto'],
            $filasBot ?: [['—', '—', '—', '—']],
            ['ancho' => [50, 15, 20, 15]]
        );

        $d->espacio();

        $d->heading('Otros recursos', 2);
        $filasOtros = [];
        foreach ($this->datos['otros'] as $o) {
            $filasOtros[] = [
                $o['categoria'] ?? '',
                (string) ($o['cantidad'] ?? 0),
                $o['descripcion'] ?? '—',
            ];
        }
        $d->tabla(
            ['Categoría', 'Cantidad', 'Observaciones'],
            $filasOtros ?: [['—', '—', '—']],
            ['ancho' => [35, 15, 50]]
        );

        $d->saltoPagina();

        // ====== LISTA DE CHEQUEO ======
        $d->heading('Lista de chequeo', 1);
        $d->lead('Marque cada ítem según corresponda. Deje observaciones al final.');

        $preguntas = [
            '¿La empresa cuenta con grupo de brigadas de emergencia?',
            '¿El grupo de brigada está debidamente capacitado y entrenado?',
            '¿La empresa tiene señalizada las rutas de evacuación?',
            '¿La empresa cuenta con un plano de evacuación visible?',
            '¿La empresa cuenta con un punto de encuentro definido?',
            '¿La empresa ha realizado mínimo 1 simulacro al año?',
            '¿Se cuenta con sistema de detección, alarma y control de incendios?',
            '¿Se cuenta con luces de emergencia operativas?',
            '¿Se cuenta con equipos de comunicaciones (radios, celulares)?',
            '¿Se cuenta con camillas de emergencia?',
            '¿Se cuenta con gabinetes contra incendios?',
            '¿Se cuenta con reserva de agua?',
            '¿Se tienen inventariados los productos químicos peligrosos?',
            '¿Se cuenta con un plano de las instalaciones con las áreas críticas?',
        ];

        $filasChk = [];
        foreach ($preguntas as $p) {
            $filasChk[] = [$p, '☐  Sí    ☐  No', ''];
        }
        $d->tabla(
            ['Pregunta', 'Respuesta', 'Observaciones'],
            $filasChk,
            ['ancho' => [50, 20, 30]]
        );

        $d->espacio(24);
        $d->caja(
            'Recomendación',
            'Las respuestas "No" deben generar un plan de acción con responsable y fecha. ' .
            'Se recomienda revisar esta lista cada 6 meses.',
            'info'
        );

        $d->firmas([
            ['nombre' => $emp['representante_legal'] ?? '', 'cargo' => 'Representante legal'],
            ['nombre' => '', 'cargo' => 'Profesional SST'],
        ]);

        $d->guardar($ruta);
        return $ruta;
    }

    // ============================================================
    // 02 — ANÁLISIS DE VULNERABILIDAD (.xlsx)
    // ============================================================

    private function docAnalisisVulnerabilidad(string $ruta): string
    {
        $x = new XlsxBuilder();

        foreach ($this->datos['amenazas'] as $amenaza) {
            if ((int)($amenaza['aplica'] ?? 1) !== 1) continue;

            $respuestas = Database::query(
                'SELECT formato, categoria, pregunta, respuesta, calificacion, observacion
                 FROM respuestas
                 WHERE amenaza_id = ?
                 ORDER BY formato, orden, id',
                [(int) $amenaza['id']]
            );

            $filas = [];
            $merges = [];
            $coloresCelda = []; // no usado ahora, usamos estilos por celda

            // Fila 1: Título
            $filas[] = [
                ['valor' => 'ANÁLISIS DE VULNERABILIDAD', 'estilo' => XlsxBuilder::S_TITLE],
            ];
            $merges[] = 'A1:F1';

            // Fila 2: Subtítulo (amenaza)
            $filas[] = [
                ['valor' => $amenaza['nombre'], 'estilo' => XlsxBuilder::S_SUBTITLE],
            ];
            $merges[] = 'A2:F2';

            // Fila 3: vacía
            $filas[] = [];

            // Fila 4: Encabezados
            $filas[] = [
                ['valor' => 'PUNTO A EVALUAR', 'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => 'RESPUESTA',       'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => '',                'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => '',                'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => 'CALIFICACIÓN',    'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => 'OBSERVACIONES',   'estilo' => XlsxBuilder::S_HEADER],
            ];
            $merges[] = 'B4:D4';

            // Fila 5: Sub-encabezados SI / NO / PARCIAL
            $filas[] = [
                ['valor' => '',        'estilo' => XlsxBuilder::S_DEFAULT],
                ['valor' => 'SI',      'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => 'NO',      'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => 'PARCIAL', 'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => '',        'estilo' => XlsxBuilder::S_HEADER],
                ['valor' => '',        'estilo' => XlsxBuilder::S_HEADER],
            ];

            $categoriaAnterior = '';
            foreach ($respuestas as $r) {
                // Encabezado de categoría
                if ($r['categoria'] !== $categoriaAnterior) {
                    $filas[] = [
                        ['valor' => '► ' . strtoupper($r['categoria']), 'estilo' => XlsxBuilder::S_LABEL],
                        ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                        ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                        ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                        ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                        ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                    ];
                    $filaIdx = count($filas);
                    $merges[] = "A{$filaIdx}:F{$filaIdx}";
                    $categoriaAnterior = $r['categoria'];
                }

                // Fila de pregunta
                $cal = $r['calificacion'] !== null ? (float) $r['calificacion'] : null;
                $estiloCal = match(true) {
                    $cal === null => XlsxBuilder::S_VALUE,
                    $cal >= 1     => XlsxBuilder::S_VERDE,
                    $cal >= 0.5   => XlsxBuilder::S_AMBAR,
                    default       => XlsxBuilder::S_ROJO,
                };

                $resp = strtoupper($r['respuesta'] ?? '—');

                $filas[] = [
                    ['valor' => $r['pregunta'],                            'estilo' => XlsxBuilder::S_VALUE],
                    ['valor' => $resp === 'SI'      ? '✓' : '',            'estilo' => XlsxBuilder::S_CENTER],
                    ['valor' => $resp === 'NO'      ? '✓' : '',            'estilo' => XlsxBuilder::S_CENTER],
                    ['valor' => $resp === 'PARCIAL' ? '✓' : '',            'estilo' => XlsxBuilder::S_CENTER],
                    ['valor' => $cal !== null ? number_format($cal, 2) : '—', 'estilo' => $estiloCal],
                    ['valor' => $r['observacion'] ?? '',                   'estilo' => XlsxBuilder::S_WRAP],
                ];
            }

            // Fila vacía
            $filas[] = [];

            // Resumen
            $filas[] = [
                ['valor' => 'PROMEDIO PERSONAS',  'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => number_format((float)($amenaza['promedio_personas'] ?? 0), 2), 'estilo' => XlsxBuilder::S_BOLD],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
            ];
            $filas[] = [
                ['valor' => 'PROMEDIO RECURSOS',  'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => number_format((float)($amenaza['promedio_recursos'] ?? 0), 2), 'estilo' => XlsxBuilder::S_BOLD],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
            ];
            $filas[] = [
                ['valor' => 'PROMEDIO SISTEMAS',  'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => number_format((float)($amenaza['promedio_sistemas'] ?? 0), 2), 'estilo' => XlsxBuilder::S_BOLD],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
            ];

            $nivel = $amenaza['nivel_riesgo'] ?? 'Sin evaluar';
            $estiloNivel = match($nivel) {
                'ALTO'  => XlsxBuilder::S_ROJO,
                'MEDIO' => XlsxBuilder::S_AMBAR,
                'BAJO'  => XlsxBuilder::S_VERDE,
                default => XlsxBuilder::S_BOLD,
            };
            $filas[] = [
                ['valor' => 'NIVEL DE RIESGO', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
                ['valor' => $nivel, 'estilo' => $estiloNivel],
                ['valor' => '', 'estilo' => XlsxBuilder::S_LABEL],
            ];

            $x->agregarHoja(
                substr($amenaza['nombre'], 0, 31),
                $filas,
                [
                    'anchos'  => ['A' => 55, 'B' => 6, 'C' => 6, 'D' => 10, 'E' => 14, 'F' => 35],
                    'merges'  => $merges,
                    'freeze'  => 'A5',
                ]
            );
        }

        $x->guardar($ruta);
        return $ruta;
    }

    // ============================================================
    // 03 — MATRIZ CONSOLIDADA
    // ============================================================

    private function docMatrizConsolidada(string $ruta): string
    {
        $x = new XlsxBuilder();
        $emp = $this->datos['empresa'];

        $filas = [];

        // Título
        $filas[] = [
            ['valor' => 'MATRIZ CONSOLIDADA DE ANÁLISIS DEL RIESGO', 'estilo' => XlsxBuilder::S_TITLE],
        ];
        $merges = ['A1:H1'];

        $filas[] = [
            ['valor' => $emp['razon_social'] . '  ·  NIT: ' . $emp['nit'], 'estilo' => XlsxBuilder::S_SUBTITLE],
        ];
        $merges[] = 'A2:H2';

        $filas[] = [];
        $filas[] = [];

        // Encabezados
        $filas[] = [
            ['valor' => 'AMENAZA',          'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'PERSONAS',         'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'RECURSOS',         'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'SISTEMAS',         'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'TOTAL PERSONAS',   'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'TOTAL RECURSOS',   'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'TOTAL SISTEMAS',   'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'NIVEL DE RIESGO',  'estilo' => XlsxBuilder::S_HEADER],
        ];

        foreach ($this->datos['amenazas'] as $a) {
            if ((int)($a['aplica'] ?? 1) !== 1) continue;

            $nivel = $a['nivel_riesgo'] ?? 'Sin evaluar';
            $estiloNivel = match($nivel) {
                'ALTO'  => XlsxBuilder::S_ROJO,
                'MEDIO' => XlsxBuilder::S_AMBAR,
                'BAJO'  => XlsxBuilder::S_VERDE,
                default => XlsxBuilder::S_CENTER,
            };

            $filas[] = [
                ['valor' => $a['nombre'], 'estilo' => XlsxBuilder::S_BOLD],
                ['valor' => number_format((float)($a['promedio_personas'] ?? 0), 2), 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => number_format((float)($a['promedio_recursos'] ?? 0), 2), 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => number_format((float)($a['promedio_sistemas'] ?? 0), 2), 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => number_format((float)($a['total_personas'] ?? 0), 2), 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => number_format((float)($a['total_recursos'] ?? 0), 2), 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => number_format((float)($a['total_sistemas'] ?? 0), 2), 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => $nivel, 'estilo' => $estiloNivel],
            ];
        }

        // Fila vacía
        $filas[] = [];

        // Leyenda
        $filas[] = [
            ['valor' => 'LEYENDA:', 'estilo' => XlsxBuilder::S_SUBTITLE],
        ];
        $merges[] = 'A' . count($filas) . ':H' . count($filas);

        $filas[] = [
            ['valor' => 'ROJO = Alta vulnerabilidad (0 – 0.5)',    'estilo' => XlsxBuilder::S_ROJO],
            ['valor' => 'AMARILLO = Media (0.5 – 0.7)',            'estilo' => XlsxBuilder::S_AMBAR],
            ['valor' => 'VERDE = Baja (0.7 – 1.0)',                'estilo' => XlsxBuilder::S_VERDE],
        ];

        $x->agregarHoja('CONSOLIDADO', $filas, [
            'anchos' => ['A' => 28, 'B' => 12, 'C' => 12, 'D' => 12, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 18],
            'merges' => $merges,
            'freeze' => 'A6',
        ]);

        $x->guardar($ruta);
        return $ruta;
    }

    // ============================================================
    // 04 — MEDEVAC
    // ============================================================

    private function docMedevac(string $ruta): string
    {
        $x = new XlsxBuilder();
        $emp = $this->datos['empresa'];

        $filas = [];
        $filas[] = [['valor' => 'MEDEVAC — EVACUACIÓN MÉDICA', 'estilo' => XlsxBuilder::S_TITLE]];
        $merges = ['A1:O1'];

        $filas[] = [['valor' => $emp['razon_social'] . '  ·  NIT: ' . $emp['nit'], 'estilo' => XlsxBuilder::S_SUBTITLE]];
        $merges[] = 'A2:O2';

        $filas[] = [];

        $filas[] = [
            ['valor' => 'N°',                    'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Nombre completo',       'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Identificación',        'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Correo',                'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Cargo',                 'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'RH',                    'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Celular',               'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Dependencia',           'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Entrenamiento brigada', 'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'EPS',                   'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'AFP',                   'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Contacto emergencia',   'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Teléfono contacto',     'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Alergias',              'estilo' => XlsxBuilder::S_HEADER],
            ['valor' => 'Firma',                 'estilo' => XlsxBuilder::S_HEADER],
        ];

        $i = 1;
        foreach ($this->datos['personal'] as $p) {
            $filas[] = [
                ['valor' => $i, 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => $p['nombre_completo'] ?? '', 'estilo' => XlsxBuilder::S_BOLD],
                ['valor' => $p['cedula'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['email'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['cargo'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['rh'] ?? '', 'estilo' => XlsxBuilder::S_CENTER],
                ['valor' => $p['celular'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['dependencia'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['entrenado_brigada'] ?? '—', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['eps'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['afp'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['contacto_emergencia_nombre'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['contacto_emergencia_tel'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => $p['alergias'] ?? '', 'estilo' => XlsxBuilder::S_VALUE],
                ['valor' => !empty($p['firma_url']) ? '✓ Firmado' : 'Sin firma',
                 'estilo' => !empty($p['firma_url']) ? XlsxBuilder::S_VERDE : XlsxBuilder::S_CENTER],
            ];
            $i++;
        }

        $x->agregarHoja('MEDEVAC', $filas, [
            'anchos' => [
                'A' => 5, 'B' => 28, 'C' => 15, 'D' => 28, 'E' => 18, 'F' => 6, 'G' => 14,
                'H' => 18, 'I' => 22, 'J' => 18, 'K' => 18, 'L' => 22, 'M' => 16,
                'N' => 25, 'O' => 12,
            ],
            'merges' => $merges,
            'freeze' => 'A5',
            'filtros' => 'A4:O4',
        ]);

        $x->guardar($ruta);
        return $ruta;
    }

    // ============================================================
    // 05 — PPPRAE COMPLETO
    // ============================================================

    private function docPlanCompleto(string $ruta): string
    {
        $d = new DocxBuilder();
        $emp = $this->datos['empresa'];
        $an  = $this->datos['analisis'];

        // ====== PORTADA ======
        $d->portada([
            'empresa'   => $emp['razon_social'],
            'titulo'    => 'PLAN DE PREVENCIÓN, PREPARACIÓN Y RESPUESTA ANTE EMERGENCIAS',
            'subtitulo' => 'Decreto 1072 de 2015',
            'ciudad'    => ($emp['ciudad'] ?? '') . ' / ' . ($emp['departamento'] ?? ''),
            'fecha'     => date('d/m/Y'),
        ]);

        // ====== TABLA DE CONTENIDO ======
        $d->heading('Tabla de contenido', 1);
        $d->tabla(
            ['#', 'Sección'],
            [
                ['1', 'Introducción'],
                ['2', 'Justificación'],
                ['3', 'Objetivos'],
                ['4', 'Marco normativo'],
                ['5', 'Información general de la empresa'],
                ['6', 'Inventario de recursos'],
                ['7', 'Análisis de amenazas'],
                ['8', 'Análisis de vulnerabilidad'],
                ['9', 'Plan operativo'],
                ['10', 'Plan de evacuación'],
                ['11', 'Procedimientos operativos normalizados'],
                ['12', 'MEDEVAC'],
                ['13', 'Firmas y aprobación'],
            ],
            ['ancho' => [10, 90]]
        );

        $d->saltoPagina();

        // ====== 1. INTRODUCCIÓN ======
        $d->heading('1. Introducción', 1);
        $d->lead(
            'El Plan de Prevención, Preparación y Respuesta ante Emergencias (PPPRAE) es la ' .
            'herramienta que permite a las organizaciones anticiparse, responder y recuperarse ' .
            'de eventos adversos.'
        );
        $d->parrafo(
            'Este plan es de aplicación obligatoria para todos los trabajadores, contratistas, ' .
            'subcontratistas, visitantes y proveedores que se encuentren en las instalaciones de ' .
            $emp['razon_social'] . '. Su implementación permite:'
        );
        $d->lista([
            'Reducir la probabilidad de ocurrencia de eventos adversos.',
            'Minimizar las consecuencias sobre las personas, los bienes y el ambiente.',
            'Establecer los procedimientos de respuesta y recuperación.',
            'Cumplir con los requisitos legales vigentes en materia de SST.',
        ]);

        // ====== 2. JUSTIFICACIÓN ======
        $d->heading('2. Justificación', 1);
        $d->parrafo(
            'La normatividad colombiana vigente, en especial el Decreto 1072 de 2015 en su ' .
            'artículo 2.2.4.6.25, exige a los empleadores implementar y mantener las disposiciones ' .
            'necesarias en materia de prevención, preparación y respuesta ante emergencias.'
        );
        $d->caja(
            'Decreto 1072 de 2015 — Art. 2.2.4.6.25',
            'El empleador debe implementar y mantener las disposiciones necesarias en materia de ' .
            'prevención, preparación y respuesta ante emergencias, con cobertura a todos los centros ' .
            'y turnos de trabajo y todos los trabajadores, independiente de su forma de contratación ' .
            'o vinculación.',
            'info'
        );

        // ====== 3. OBJETIVOS ======
        $d->heading('3. Objetivos', 1);
        $d->heading('3.1 Objetivo general', 2);
        $d->parrafo(
            'Establecer las acciones y procedimientos necesarios para prevenir, preparar y responder ' .
            'ante situaciones de emergencia en las instalaciones de ' . $emp['razon_social'] . '.'
        );

        $d->heading('3.2 Objetivos específicos', 2);
        $d->lista([
            'Identificar y valorar las amenazas presentes en las instalaciones.',
            'Determinar la vulnerabilidad de las personas, los recursos y los sistemas.',
            'Organizar la brigada de emergencias y definir sus funciones.',
            'Capacitar al personal en autoprotección y respuesta ante emergencias.',
            'Establecer los procedimientos operativos normalizados (PON).',
            'Diseñar el plan de evacuación y los puntos de encuentro.',
            'Definir las acciones de recuperación posteriores a un evento.',
        ]);

        $d->saltoPagina();

        // ====== 4. MARCO NORMATIVO ======
        $d->heading('4. Marco normativo', 1);
        $d->tabla(
            ['Norma', 'Descripción'],
            [
                ['Decreto 1072 de 2015',   'Art. 2.2.4.6.25 — Prevención, preparación y respuesta ante emergencias.'],
                ['Resolución 0312 de 2019', 'Estándares mínimos del SG-SST.'],
                ['Resolución 256 de 2014', 'Brigada contra incendios: mínimo 20% de la población laboral.'],
                ['Resolución 705 de 2007', 'Botiquín de primeros auxilios y elementos mínimos.'],
                ['Ley 1523 de 2012',       'Política Nacional de Gestión del Riesgo de Desastres.'],
                ['NTC 4595',               'Planeamiento y diseño de instalaciones y ambientes escolares.'],
                ['NFPA 101',               'Código de seguridad humana (evacuación).'],
            ],
            ['ancho' => [30, 70]]
        );

        // ====== 5. INFORMACIÓN GENERAL ======
        $d->saltoPagina();
        $d->heading('5. Información general de la empresa', 1);
        $d->lead('Datos básicos e identificación de la organización.');

        $d->heading('5.1 Identificación', 2);
        $d->tabla(
            ['Campo', 'Información'],
            [
                ['Razón social',         $emp['razon_social'] ?? ''],
                ['NIT',                  $emp['nit'] ?? ''],
                ['Actividad económica',  $emp['actividad_economica'] ?? ''],
                ['Dirección',            $emp['direccion'] ?? ''],
                ['Ciudad / Departamento', ($emp['ciudad'] ?? '') . ' / ' . ($emp['departamento'] ?? '')],
                ['Teléfono',             $emp['telefono'] ?? ''],
                ['Correo electrónico',   $emp['email'] ?? ''],
                ['ARL',                  $emp['arl'] ?? ''],
                ['Nivel de riesgo',      $emp['nivel_riesgo'] ?? ''],
                ['Representante legal',  $emp['representante_legal'] ?? ''],
            ],
            ['ancho' => [35, 65]]
        );

        $d->heading('5.2 Características de las instalaciones', 2);
        $d->tabla(
            ['Característica', 'Valor'],
            [
                ['Área construida',   ($emp['area_m2'] ?? 0) . ' m²'],
                ['Número de pisos',   (string) ($emp['num_pisos'] ?? 1)],
                ['Número de trabajadores', (string) ($emp['num_trabajadores'] ?? 0)],
                ['Aforo máximo',      (string) ($emp['aforo_max'] ?? 0) . ' personas'],
                ['Horarios de trabajo', $emp['horarios'] ?? 'No especificado'],
            ],
            ['ancho' => [40, 60]]
        );

        if (!empty($emp['areas_alto_riesgo'])) {
            $d->heading('5.3 Áreas de alto riesgo', 2);
            $d->parrafo($emp['areas_alto_riesgo']);
        }

        // ====== 6. INVENTARIO ======
        $d->saltoPagina();
        $d->heading('6. Inventario de recursos', 1);
        $d->lead('Recursos disponibles para la prevención y atención de emergencias.');

        $d->heading('6.1 Extintores', 2);
        $filasExt = [];
        foreach ($this->datos['extintores'] as $e) {
            $filasExt[] = [
                $e['numero'] ?? '',
                $e['ubicacion'] ?? '',
                $e['tipo'] ?? '',
                $e['peso'] ?? '',
                $e['fecha_recarga'] ?? '',
                ((int)($e['cumple'] ?? 1) === 1) ? '✓' : '✗',
            ];
        }
        $d->tabla(
            ['N°', 'Ubicación', 'Tipo', 'Peso', 'Recarga', 'OK'],
            $filasExt ?: [['—', '—', '—', '—', '—', '—']],
            ['ancho' => [8, 25, 15, 12, 20, 10]]
        );

        $d->espacio();

        $d->heading('6.2 Botiquín', 2);
        $filasBot = [];
        foreach ($this->datos['botiquin'] as $b) {
            $filasBot[] = [
                $b['elemento'] ?? '',
                (string) ($b['cantidad'] ?? 0),
                $b['fecha_vencimiento'] ?? '',
                ((int)($b['apto_para_uso'] ?? 1) === 1) ? 'Sí' : 'No',
            ];
        }
        $d->tabla(
            ['Elemento', 'Cantidad', 'Vencimiento', 'Apto'],
            $filasBot ?: [['—', '—', '—', '—']],
            ['ancho' => [50, 15, 20, 15]]
        );

        $d->espacio();

        $d->heading('6.3 Otros recursos', 2);
        $filasOtros = [];
        foreach ($this->datos['otros'] as $o) {
            $filasOtros[] = [
                $o['categoria'] ?? '',
                (string) ($o['cantidad'] ?? 0),
                $o['descripcion'] ?? '—',
            ];
        }
        $d->tabla(
            ['Categoría', 'Cantidad', 'Observaciones'],
            $filasOtros ?: [['—', '—', '—']],
            ['ancho' => [35, 15, 50]]
        );

        $d->espacio();

        $d->heading('6.4 Recursos externos', 2);
        $d->tabla(
            ['Entidad', 'Teléfono'],
            [
                ['Cruz Roja',                  '(8) 6703838 — 132'],
                ['Bomberos',                   '6626505 — 119 — 111'],
                ['Policía Nacional',           '112 — 146'],
                ['Defensa Civil',              '144'],
                ['Oficina Gestión del Riesgo', '671 58 59'],
                ['Gaula',                      '6678099 — 165'],
                ['Secretaría de Salud',        '6610555'],
                ['SIJIN',                      '6678100'],
                ['Hospital Departamental',     '018000930606 — 137'],
            ],
            ['ancho' => [40, 60]]
        );

        // ====== 7. ANÁLISIS DE AMENAZAS ======
        $d->saltoPagina();
        $d->heading('7. Análisis de amenazas', 1);
        $d->lead('Identificación y clasificación de las amenazas según su origen y probabilidad.');

        // Resumen
        $d->heading('7.1 Resumen de amenazas identificadas', 2);
        $filasAm = [];
        foreach ($this->datos['amenazas'] as $a) {
            if ((int)($a['aplica'] ?? 1) !== 1) continue;
            $filasAm[] = [
                $a['nombre'] ?? '',
                ucfirst($a['tipo'] ?? ''),
                ucfirst($a['origen'] ?? ''),
                $a['nivel_riesgo'] ?? 'Sin evaluar',
            ];
        }
        $d->tabla(
            ['Amenaza', 'Categoría', 'Origen', 'Nivel'],
            $filasAm ?: [['—', '—', '—', '—']],
            ['ancho' => [40, 22, 18, 20]]
        );

        // Detalle por amenaza
        foreach ($this->datos['amenazas'] as $a) {
            if ((int)($a['aplica'] ?? 1) !== 1) continue;

            $d->saltoPagina();
            $d->heading('7.2 ' . $a['nombre'], 2);

            $d->tabla(
                ['Parámetro', 'Valor'],
                [
                    ['Tipo',              ucfirst($a['tipo'] ?? '')],
                    ['Origen',            ucfirst($a['origen'] ?? '')],
                    ['Nivel de riesgo',   $a['nivel_riesgo'] ?? 'Sin evaluar'],
                    ['Vulnerabilidad en personas', (string)($a['promedio_personas'] ?? 0)],
                    ['Vulnerabilidad en recursos', (string)($a['promedio_recursos'] ?? 0)],
                    ['Vulnerabilidad en sistemas', (string)($a['promedio_sistemas'] ?? 0)],
                ],
                ['ancho' => [40, 60]]
            );

            $d->espacio();

            $respuestas = Database::query(
                'SELECT categoria, pregunta, respuesta, calificacion, observacion
                 FROM respuestas WHERE amenaza_id = ?
                 ORDER BY formato, orden, id',
                [(int) $a['id']]
            );

            $catAct = '';
            $filasR = [];
            foreach ($respuestas as $r) {
                if ($r['categoria'] !== $catAct) {
                    $filasR[] = ['► ' . $r['categoria'], '', '', ''];
                    $catAct = $r['categoria'];
                }
                $filasR[] = [
                    $r['pregunta'],
                    $r['respuesta'] ?? '—',
                    $r['calificacion'] !== null ? number_format((float) $r['calificacion'], 2) : '—',
                    $r['observacion'] ?? '',
                ];
            }
            if ($filasR) {
                $d->tabla(
                    ['Pregunta', 'Respuesta', 'Calif.', 'Observaciones'],
                    $filasR,
                    ['ancho' => [50, 12, 10, 28]]
                );
            }
        }

        // ====== 8. ANÁLISIS DE VULNERABILIDAD ======
        $d->saltoPagina();
        $d->heading('8. Análisis de vulnerabilidad', 1);
        $d->lead('Consolidado de vulnerabilidad por amenaza y por componente evaluado.');

        $filasMatriz = [];
        foreach ($this->datos['amenazas'] as $a) {
            if ((int)($a['aplica'] ?? 1) !== 1) continue;
            $filasMatriz[] = [
                $a['nombre'],
                (string)($a['total_personas'] ?? 0),
                (string)($a['total_recursos'] ?? 0),
                (string)($a['total_sistemas'] ?? 0),
                $a['nivel_riesgo'] ?? 'Sin evaluar',
            ];
        }
        $d->tabla(
            ['Amenaza', 'Personas', 'Recursos', 'Sistemas', 'Nivel de riesgo'],
            $filasMatriz ?: [['—', '—', '—', '—', '—']],
            ['ancho' => [35, 15, 15, 15, 20]]
        );

        $d->espacio();
        $d->caja(
            'Interpretación de resultados',
            'ROJO: alta vulnerabilidad (0 a 0.5) — acción inmediata. ' .
            'AMARILLO: vulnerabilidad media (0.5 a 0.7) — requiere mejora. ' .
            'VERDE: vulnerabilidad baja (0.7 a 1.0) — mantener condiciones.',
            'info'
        );

        // ====== 9. PLAN OPERATIVO ======
        $d->saltoPagina();
        $d->heading('9. Plan operativo', 1);

        $d->heading('9.1 Conformación de la brigada', 2);
        $d->parrafo(
            'La brigada de emergencias de ' . $emp['razon_social'] . ' está conformada por ' .
            count($this->datos['personal']) . ' personas. Según la Resolución 256 de 2014, ' .
            'debe estar conformada como mínimo por el 20% de la población laboral.'
        );

        $personal = $this->datos['personal'];
        if (!empty($personal)) {
            $filasPersonal = [];
            foreach ($personal as $p) {
                $filasPersonal[] = [
                    $p['nombre_completo'] ?? '',
                    $p['cargo'] ?? '',
                    $p['entrenado_brigada'] ?? 'Sin entrenamiento',
                    $p['celular'] ?? '',
                ];
            }
            $d->tabla(
                ['Nombre', 'Cargo', 'Entrenamiento', 'Celular'],
                $filasPersonal,
                ['ancho' => [35, 20, 30, 15]]
            );
        } else {
            $d->caja(
                'Sin personal registrado',
                'Aún no se ha registrado personal para la brigada de emergencias. ' .
                'Se recomienda completar el paso 5 del wizard.',
                'warning'
            );
        }

        $d->heading('9.2 Funciones de la brigada', 2);

        $d->heading('Antes de la emergencia', 3);
        $d->lista([
            'Distribuir las responsabilidades entre los integrantes de la brigada.',
            'Coordinar actividades formativas para todo el personal.',
            'Mantener actualizado el inventario de recursos.',
            'Realizar inspecciones periódicas de seguridad.',
            'Programar y ejecutar simulacros.',
        ]);

        $d->heading('Durante la emergencia', 3);
        $d->lista([
            'Activar la alarma y seguir los procedimientos establecidos.',
            'Coordinar la evacuación y el refugio según corresponda.',
            'Prestar primeros auxilios a los lesionados.',
            'Realizar el conteo del personal en el punto de encuentro.',
            'Solicitar apoyo externo según la magnitud del evento.',
        ]);

        $d->heading('Después de la emergencia', 3);
        $d->lista([
            'Realizar la evaluación de daños y necesidades.',
            'Elaborar el informe de la emergencia.',
            'Implementar acciones correctivas y de mejora.',
            'Coordinar la recuperación de las operaciones.',
            'Retroalimentar el plan con las lecciones aprendidas.',
        ]);

        // ====== 10. PLAN DE EVACUACIÓN ======
        $d->saltoPagina();
        $d->heading('10. Plan de evacuación', 1);

        $d->heading('10.1 Sistema de alarma', 2);
        $d->parrafo(
            'El sistema de alarma en la empresa es a través de SILBATO, ubicado en el botiquín. ' .
            'La alarma se activa en las siguientes situaciones:'
        );
        $d->lista([
            'Cuando se detecte un incendio que no se pueda controlar.',
            'Cuando se observe presencia de humo en grandes proporciones.',
            'Cuando se sospeche o presenten daños en la estructura.',
            'Cuando se descubra la presencia de personal extraño.',
        ]);

        $d->heading('10.2 Tiempo de evacuación', 2);
        $n   = (int)($emp['num_trabajadores'] ?? 0) + (int)($emp['aforo_max'] ?? 0);
        $a   = 2; $k = 1.3; $dist = 30; $v = 0.4;
        $ts  = ($n / ($a * $k)) + ($dist / $v);

        $d->parrafo('Fórmula aplicada:');
        $d->parrafo('TS = N / (A × K) + D / V', true, true);

        $d->tabla(
            ['Variable', 'Valor'],
            [
                ['N — Número de personas',      (string) $n],
                ['A — Ancho de la salida (m)',  (string) $a],
                ['K — Constante experimental',  (string) $k],
                ['D — Distancia total (m)',     (string) $dist],
                ['V — Velocidad (m/s)',         (string) $v],
            ],
            ['ancho' => [70, 30]]
        );

        $d->espacio();
        $d->caja(
            'Tiempo estimado de evacuación',
            'TS = ' . round($ts, 2) . ' segundos (' . round($ts / 60, 2) . ' minutos). ' .
            'Este es el tiempo máximo que debe tomar evacuar completamente las instalaciones.',
            'success'
        );

        $d->heading('10.3 Punto de encuentro', 2);
        $d->parrafo(
            'El punto de encuentro es el sitio utilizado para la agrupación de todo el personal ' .
            'una vez se ha dado la orden de evacuación. Debe estar ubicado en un lugar seguro, ' .
            'despejado y a una distancia mínima que garantice la seguridad de los evacuados.'
        );

        // ====== 11. PONs ======
        $d->saltoPagina();
        $d->heading('11. Procedimientos operativos normalizados (PON)', 1);
        $d->lead(
            'Los PON son acciones específicas de respuesta que permiten a todo el personal ' .
            'actuar de forma coordinada, facilitando las comunicaciones y optimizando los recursos.'
        );

        foreach ($this->datos['amenazas'] as $a) {
            if ((int)($a['aplica'] ?? 1) !== 1) continue;

            $d->saltoPagina();
            $d->heading('11.' . $a['id'] . ' PON — ' . $a['nombre'], 2);

            $d->heading('Trabajadores — Antes', 3);
            $d->lista([
                'Asistir a las capacitaciones y simulacros programados.',
                'Reportar condiciones inseguras al área de SST.',
                'Conocer las rutas de evacuación y puntos de encuentro.',
                'Identificar los recursos de emergencia disponibles.',
            ]);

            $d->heading('Trabajadores — Durante', 3);
            $d->lista([
                'No entrar en pánico, mantener el orden.',
                'Seguir las instrucciones de los brigadistas.',
                'Evacuar por las rutas señalizadas, sin correr.',
                'No regresar por objetos personales.',
                'Reportar la presencia de heridos o personas atrapadas.',
            ]);

            $d->heading('Trabajadores — Después', 3);
            $d->lista([
                'Dirigirse al punto de encuentro establecido.',
                'Reportar el estado de salud propio y de los compañeros.',
                'Permanecer en el punto de encuentro hasta nueva indicación.',
                'No regresar a las instalaciones sin autorización del comité.',
            ]);

            $d->heading('Brigadistas — Antes', 3);
            $d->lista([
                'Realizar y participar en las capacitaciones programadas.',
                'Inspeccionar los equipos de emergencia periódicamente.',
                'Mantener actualizados los inventarios y planos.',
                'Coordinar simulacros con el área de SST.',
            ]);

            $d->heading('Brigadistas — Durante', 3);
            $d->lista([
                'Accionar la alarma y activar el plan de emergencias.',
                'Dirigir la evacuación y controlar el pánico.',
                'Prestar primeros auxilios según protocolo.',
                'Coordinar la solicitud de apoyo externo si se requiere.',
                'Verificar que no quede ninguna persona en las instalaciones.',
            ]);

            $d->heading('Brigadistas — Después', 3);
            $d->lista([
                'Realizar el conteo del personal evacuado.',
                'Elaborar el informe preliminar de la emergencia.',
                'Participar en la evaluación y las acciones de mejora.',
                'Coordinar la recuperación de las operaciones.',
            ]);
        }

        // ====== 12. MEDEVAC ======
        $d->saltoPagina();
        $d->heading('12. MEDEVAC — Evacuación médica', 1);
        $d->lead(
            'Información del personal para la atención médica en caso de emergencia. ' .
            'Esta sección debe mantenerse actualizada permanentemente.'
        );

        foreach ($this->datos['personal'] as $i => $p) {
            $d->heading('12.' . ($i + 1) . ' ' . ($p['nombre_completo'] ?? ''), 2);

            $d->tabla(
                ['Campo', 'Información'],
                [
                    ['Identificación',        $p['cedula'] ?? '—'],
                    ['Cargo',                 $p['cargo'] ?? '—'],
                    ['RH',                    $p['rh'] ?? '—'],
                    ['Celular',               $p['celular'] ?? '—'],
                    ['Correo',                $p['email'] ?? '—'],
                    ['EPS',                   $p['eps'] ?? '—'],
                    ['AFP',                   $p['afp'] ?? '—'],
                    ['Contacto de emergencia', $p['contacto_emergencia_nombre'] ?? '—'],
                    ['Teléfono del contacto', $p['contacto_emergencia_tel'] ?? '—'],
                    ['Alergias / medicamentos', $p['alergias'] ?? 'Ninguna'],
                    ['Entrenamiento en brigada', $p['entrenado_brigada'] ?? 'Ninguno'],
                ],
                ['ancho' => [35, 65]]
            );

            if (!empty($p['firma_url'])) {
                $rutaFirma = BASE_PATH . '/public/' . $p['firma_url'];
                if (file_exists($rutaFirma)) {
                    $d->espacio();
                    $d->parrafo('Firma:', true);
                    $d->imagen($rutaFirma, 200);
                }
            }
        }

        // ====== 13. FIRMAS ======
        $d->saltoPagina();
        $d->heading('13. Firmas y aprobación', 1);
        $d->lead(
            'El presente plan es aprobado por los responsables de su implementación y seguimiento.'
        );

        $d->espacio(48);
        $d->firmas([
            ['nombre' => $emp['representante_legal'] ?? '', 'cargo' => 'Representante legal'],
            ['nombre' => '', 'cargo' => 'Profesional SST'],
        ]);

        $d->espacio(48);
        $d->firmas([
            ['nombre' => '', 'cargo' => 'Líder de brigada'],
            ['nombre' => '', 'cargo' => 'Coordinador del SG-SST'],
        ]);

        $d->espacio(48);
        $d->caja(
            'Control de versiones',
            'Versión ' . ($an['version'] ?? '1') . ' — ' .
            'Fecha de elaboración: ' . ($an['fecha_elaboracion'] ?? date('Y-m-d')) . ' — ' .
            'Código: ' . ($an['codigo_documento'] ?? 'SST-F-1'),
            'info'
        );

        $d->guardar($ruta);
        return $ruta;
    }

    // ============================================================
    // Carga de datos
    // ============================================================

    private function cargarDatos(): array
    {
        $analisis = Database::first(
            'SELECT a.*, e.* FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE a.id = ?',
            [$this->analisisId]
        );

        if (!$analisis) throw new \RuntimeException("Análisis {$this->analisisId} no encontrado");

        return [
            'empresa'  => $analisis,
            'analisis' => $analisis,
            'amenazas' => Database::query(
                'SELECT * FROM amenazas WHERE analisis_id = ? ORDER BY id',
                [$this->analisisId]
            ),
            'extintores' => Database::query(
                'SELECT * FROM inv_extintores WHERE analisis_id = ?',
                [$this->analisisId]
            ),
            'botiquin' => Database::query(
                'SELECT * FROM inv_botiquin WHERE analisis_id = ?',
                [$this->analisisId]
            ),
            'otros' => Database::query(
                'SELECT * FROM inv_otros WHERE analisis_id = ?',
                [$this->analisisId]
            ),
            'personal' => Database::query(
                'SELECT * FROM personal WHERE empresa_id = ? AND activo = 1',
                [(int) $analisis['empresa_id']]
            ),
        ];
    }
}
