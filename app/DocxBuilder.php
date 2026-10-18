<?php
namespace App;

use ZipArchive;

class DocxBuilder
{
    private array $bloques  = [];
    private array $imagenes = [];

    // Paleta
    const AZUL_OSCURO = '1E3A8A';
    const AZUL        = '2563EB';
    const AZUL_CLARO  = 'EFF6FF';
    const VERDE       = '10B981';
    const AMBAR       = 'F59E0B';
    const ROJO        = 'EF4444';
    const GRIS_TXT    = '1E293B';
    const GRIS_SUAVE  = '64748B';
    const GRIS_FONDO  = 'F8FAFC';

    public function __construct(private string $titulo = 'Documento') {}

    // ============================================================
    // API
    // ============================================================

    /** Portada con logo, título y metadatos */
    public function portada(array $datos): self
    {
        $this->bloques[] = ['tipo' => 'portada', 'datos' => $datos];
        return $this;
    }

    public function heading(string $texto, int $nivel = 1): self
    {
        $this->bloques[] = ['tipo' => 'heading', 'texto' => $texto, 'nivel' => $nivel];
        return $this;
    }

    /** Párrafo normal */
    public function parrafo(string $texto, bool $negrita = false, bool $centrado = false): self
    {
        $this->bloques[] = ['tipo' => 'parrafo', 'texto' => $texto, 'negrita' => $negrita, 'centrado' => $centrado];
        return $this;
    }

    /** Párrafo de introducción (letra más grande, gris) */
    public function lead(string $texto): self
    {
        $this->bloques[] = ['tipo' => 'lead', 'texto' => $texto];
        return $this;
    }

    /** Caja de color con título + contenido. tipo: info|success|warning|danger */
    public function caja(string $titulo, string $texto, string $tipo = 'info'): self
    {
        $this->bloques[] = ['tipo' => 'caja', 'titulo' => $titulo, 'texto' => $texto, 'color' => $tipo];
        return $this;
    }

    /** Lista con viñetas */
    public function lista(array $items): self
    {
        $this->bloques[] = ['tipo' => 'lista', 'items' => $items];
        return $this;
    }

    /** Badge inline (chip de color) */
    public function badge(string $texto, string $color = 'azul'): self
    {
        $this->bloques[] = ['tipo' => 'badge', 'texto' => $texto, 'color' => $color];
        return $this;
    }

    public function saltoPagina(): self
    {
        $this->bloques[] = ['tipo' => 'salto'];
        return $this;
    }

    /** Espacio vertical */
    public function espacio(int $pt = 12): self
    {
        $this->bloques[] = ['tipo' => 'espacio', 'pt' => $pt];
        return $this;
    }

    /** Línea horizontal decorativa */
    public function separador(): self
    {
        $this->bloques[] = ['tipo' => 'separador'];
        return $this;
    }

    /** Tabla moderna con encabezado azul y filas alternas */
    public function tabla(array $encabezados, array $filas, array $opciones = []): self
    {
        $this->bloques[] = [
            'tipo'        => 'tabla',
            'encabezados' => $encabezados,
            'filas'       => $filas,
            'opciones'    => $opciones, // ['colores_celda' => ['B2' => 'VERDE'], 'ancho' => [...]]
        ];
        return $this;
    }

    public function imagen(string $ruta, int $ancho = 200): self
    {
        if (!file_exists($ruta)) return $this;
        $idx = count($this->imagenes);
        $this->imagenes[] = ['ruta' => $ruta, 'ancho' => $ancho];
        $this->bloques[]  = ['tipo' => 'imagen', 'idx' => $idx];
        return $this;
    }

    /** Bloque de firmas al pie */
    public function firmas(array $firmantes): self
    {
        $this->bloques[] = ['tipo' => 'firmas', 'firmantes' => $firmantes];
        return $this;
    }

    // ============================================================
    // Generación
    // ============================================================

    public function guardar(string $rutaDestino): string
    {
        $zip = new ZipArchive();
        if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("No se pudo crear {$rutaDestino}");
        }

        $zip->addFromString('[Content_Types].xml', $this->xmlContentTypes());
        $zip->addFromString('_rels/.rels',         $this->xmlRels());
        $zip->addFromString('word/document.xml',   $this->xmlDocumento());
        $zip->addFromString('word/styles.xml',     $this->xmlStyles());
        $zip->addFromString('word/_rels/document.xml.rels', $this->xmlDocumentRels());

        foreach ($this->imagenes as $i => $img) {
            $ext = strtolower(pathinfo($img['ruta'], PATHINFO_EXTENSION));
            $zip->addFile($img['ruta'], "word/media/img{$i}.{$ext}");
        }

        $zip->close();
        return $rutaDestino;
    }

    // ============================================================
    // XML — Content Types, Rels
    // ============================================================

    private function xmlContentTypes(): string
    {
        $overrides = '';
        foreach ($this->imagenes as $i => $img) {
            $ext = strtolower(pathinfo($img['ruta'], PATHINFO_EXTENSION));
            $overrides .= '<Default Extension="' . $ext . '" ContentType="image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  ' . $overrides . '
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml"   ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>';
    }

    private function xmlRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    }

    private function xmlDocumentRels(): string
    {
        $rels = '';
        foreach ($this->imagenes as $i => $img) {
            $ext = strtolower(pathinfo($img['ruta'], PATHINFO_EXTENSION));
            $rels .= '<Relationship Id="rIdImg' . $i . '"
                       Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"
                       Target="media/img' . $i . '.' . $ext . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ' . $rels . '
</Relationships>';
    }

    // ============================================================
    // XML — Estilos
    // ============================================================

    private function xmlStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">

  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
        <w:sz w:val="22"/>
        <w:szCs w:val="22"/>
        <w:color w:val="' . self::GRIS_TXT . '"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:after="120" w:line="276" w:lineRule="auto"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>

  <!-- Normal -->
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
  </w:style>

  <!-- Título de portada -->
  <w:style w:type="paragraph" w:styleId="PortadaTitulo">
    <w:name w:val="Portada Titulo"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr>
      <w:jc w:val="center"/>
      <w:spacing w:before="480" w:after="240"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:sz w:val="72"/>
      <w:color w:val="' . self::AZUL_OSCURO . '"/>
      <w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light"/>
    </w:rPr>
  </w:style>

  <!-- Título grande para portada 2 -->
  <w:style w:type="paragraph" w:styleId="PortadaSubtitulo">
    <w:name w:val="Portada Subtitulo"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr><w:jc w:val="center"/><w:spacing w:after="480"/></w:pPr>
    <w:rPr>
      <w:sz w:val="28"/>
      <w:color w:val="' . self::GRIS_SUAVE . '"/>
      <w:i/>
    </w:rPr>
  </w:style>

  <!-- Heading 1 -->
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:pPr>
      <w:keepNext/>
      <w:pBdr>
        <w:bottom w:val="single" w:sz="24" w:space="8" w:color="' . self::AZUL . '"/>
      </w:pBdr>
      <w:spacing w:before="480" w:after="240"/>
      <w:outlineLvl w:val="0"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:sz w:val="36"/>
      <w:color w:val="' . self::AZUL_OSCURO . '"/>
      <w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light"/>
    </w:rPr>
  </w:style>

  <!-- Heading 2 -->
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:pPr>
      <w:keepNext/>
      <w:spacing w:before="360" w:after="120"/>
      <w:outlineLvl w:val="1"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:sz w:val="28"/>
      <w:color w:val="' . self::AZUL . '"/>
      <w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light"/>
    </w:rPr>
  </w:style>

  <!-- Heading 3 -->
  <w:style w:type="paragraph" w:styleId="Heading3">
    <w:name w:val="heading 3"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr>
      <w:keepNext/>
      <w:spacing w:before="240" w:after="80"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:sz w:val="24"/>
      <w:color w:val="' . self::GRIS_TXT . '"/>
    </w:rPr>
  </w:style>

  <!-- Lead (introducción) -->
  <w:style w:type="paragraph" w:styleId="Lead">
    <w:name w:val="Lead"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr><w:spacing w:after="240"/></w:pPr>
    <w:rPr>
      <w:sz w:val="26"/>
      <w:color w:val="' . self::GRIS_SUAVE . '"/>
      <w:i/>
    </w:rPr>
  </w:style>

  <!-- Caja (párrafo con shading) -->
  <w:style w:type="paragraph" w:styleId="Caja">
    <w:name w:val="Caja"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr>
      <w:shd w:val="clear" w:fill="' . self::AZUL_CLARO . '"/>
      <w:pBdr>
        <w:left w:val="single" w:sz="24" w:space="8" w:color="' . self::AZUL . '"/>
      </w:pBdr>
      <w:spacing w:before="120" w:after="120"/>
      <w:ind w:left="240" w:right="240"/>
    </w:pPr>
    <w:rPr><w:sz w:val="22"/></w:rPr>
  </w:style>

  <!-- Encabezado de tabla -->
  <w:style w:type="paragraph" w:styleId="TblHeader">
    <w:name w:val="Tbl Header"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr><w:jc w:val="center"/><w:spacing w:before="60" w:after="60"/></w:pPr>
    <w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="20"/></w:rPr>
  </w:style>

  <!-- Texto pequeño -->
  <w:style w:type="paragraph" w:styleId="Small">
    <w:name w:val="Small"/>
    <w:basedOn w:val="Normal"/>
    <w:rPr><w:sz w:val="18"/><w:color w:val="' . self::GRIS_SUAVE . '"/></w:rPr>
  </w:style>

</w:styles>';
    }

    // ============================================================
    // XML — Documento
    // ============================================================

    private function xmlDocumento(): string
    {
        $body = '';
        foreach ($this->bloques as $b) {
            $body .= match($b['tipo']) {
                'portada'    => $this->bloquePortada($b),
                'heading'    => $this->bloqueHeading($b),
                'parrafo'    => $this->bloqueParrafo($b),
                'lead'       => $this->bloqueLead($b),
                'caja'       => $this->bloqueCaja($b),
                'lista'      => $this->bloqueLista($b),
                'badge'      => $this->bloqueBadge($b),
                'salto'      => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
                'espacio'    => $this->bloqueEspacio($b),
                'separador'  => $this->bloqueSeparador($b),
                'tabla'      => $this->bloqueTabla($b),
                'imagen'     => $this->bloqueImagen($b),
                'firmas'     => $this->bloqueFirmas($b),
                default      => '',
            };
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
            xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
            xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
            xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    ' . $body . '
    <w:sectPr>
      <w:pgSz w:w="12240" w:h="15840"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>';
    }

    // ============================================================
    // Bloques
    // ============================================================

    private function bloquePortada(array $b): string
    {
        $d = $b['datos'];

        $empresa = $this->esc($d['empresa'] ?? '');
        $titulo  = $this->esc($d['titulo'] ?? 'PLAN DE PREVENCIÓN, PREPARACIÓN Y RESPUESTA ANTE EMERGENCIAS');
        $sub     = $this->esc($d['subtitulo'] ?? '');
        $ciudad  = $this->esc($d['ciudad'] ?? '');
        $fecha   = $this->esc($d['fecha'] ?? date('Y-m-d'));

        // Banda superior
        $bandaTop = '<w:p><w:pPr><w:shd w:val="clear" w:fill="' . self::AZUL_OSCURO . '"/>'
                  . '<w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr>'
                  . '<w:r><w:rPr><w:sz w:val="4"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r></w:p>';

        // Espacio
        $espacio = str_repeat('<w:p/>', 3);

        // Título
        $tituloP = '<w:p><w:pPr><w:pStyle w:val="PortadaTitulo"/></w:pPr>'
                 . '<w:r><w:t xml:space="preserve">' . $titulo . '</w:t></w:r></w:p>';

        // Subtítulo (empresa)
        $subP = '<w:p><w:pPr><w:pStyle w:val="PortadaSubtitulo"/></w:pPr>'
              . '<w:r><w:t xml:space="preserve">' . $empresa . '</w:t></w:r></w:p>';

        // Banda intermedia
        $banda = '<w:p><w:pPr><w:shd w:val="clear" w:fill="' . self::AZUL . '"/>'
               . '<w:spacing w:before="0" w:after="0"/></w:pPr></w:p>';

        // Pie con metadatos
        $pie = '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="1200"/></w:pPr>'
             . '<w:r><w:rPr><w:sz w:val="22"/><w:color w:val="' . self::GRIS_SUAVE . '"/></w:rPr>'
             . '<w:t xml:space="preserve">' . $ciudad . '</w:t></w:r></w:p>';

        $pie2 = '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
              . '<w:r><w:rPr><w:sz w:val="22"/><w:color w:val="' . self::GRIS_SUAVE . '"/></w:rPr>'
              . '<w:t xml:space="preserve">' . $fecha . '</w:t></w:r></w:p>';

        return $bandaTop . $espacio . $tituloP . $subP . $banda . $pie . $pie2
             . '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    private function bloqueHeading(array $b): string
    {
        $texto  = $this->esc($b['texto']);
        $nivel  = max(1, min(3, $b['nivel']));
        $estilo = "Heading{$nivel}";
        return "<w:p><w:pPr><w:pStyle w:val=\"{$estilo}\"/></w:pPr><w:r><w:t xml:space=\"preserve\">{$texto}</w:t></w:r></w:p>";
    }

    private function bloqueParrafo(array $b): string
    {
        $texto = $this->esc($b['texto']);
        $rPr   = !empty($b['negrita']) ? '<w:b/>' : '';
        $pPr   = !empty($b['centrado']) ? '<w:jc w:val="center"/>' : '';
        return "<w:p><w:pPr>{$pPr}</w:pPr><w:r><w:rPr>{$rPr}</w:rPr><w:t xml:space=\"preserve\">{$texto}</w:t></w:r></w:p>";
    }

    private function bloqueLead(array $b): string
    {
        $texto = $this->esc($b['texto']);
        return "<w:p><w:pPr><w:pStyle w:val=\"Lead\"/></w:pPr><w:r><w:t xml:space=\"preserve\">{$texto}</w:t></w:r></w:p>";
    }

    private function bloqueCaja(array $b): string
    {
        $colores = [
            'info'    => ['fondo' => 'EFF6FF', 'borde' => self::AZUL,   'titulo' => self::AZUL_OSCURO, 'icono' => 'ℹ'],
            'success' => ['fondo' => 'ECFDF5', 'borde' => self::VERDE,  'titulo' => '065F46',          'icono' => '✓'],
            'warning' => ['fondo' => 'FFFBEB', 'borde' => self::AMBAR,  'titulo' => '92400E',          'icono' => '!'],
            'danger'  => ['fondo' => 'FEF2F2', 'borde' => self::ROJO,   'titulo' => '991B1B',          'icono' => '×'],
        ];
        $c = $colores[$b['color']] ?? $colores['info'];

        $titulo = $this->esc($b['titulo']);
        $texto  = $this->esc($b['texto']);

        // Título de caja
        $tituloP = "<w:p><w:pPr><w:shd w:val=\"clear\" w:fill=\"{$c['fondo']}\"/>"
                 . "<w:pBdr><w:left w:val=\"single\" w:sz=\"24\" w:space=\"8\" w:color=\"{$c['borde']}\"/></w:pBdr>"
                 . "<w:spacing w:before=\"120\" w:after=\"0\"/><w:ind w:left=\"200\"/></w:pPr>"
                 . "<w:r><w:rPr><w:b/><w:color w:val=\"{$c['titulo']}\"/></w:rPr>"
                 . "<w:t xml:space=\"preserve\">{$c['icono']} {$titulo}</w:t></w:r></w:p>";

        // Texto de caja
        $textoP = "<w:p><w:pPr><w:shd w:val=\"clear\" w:fill=\"{$c['fondo']}\"/>"
                . "<w:pBdr><w:left w:val=\"single\" w:sz=\"24\" w:space=\"8\" w:color=\"{$c['borde']}\"/></w:pBdr>"
                . "<w:spacing w:before=\"0\" w:after=\"240\"/><w:ind w:left=\"200\"/></w:pPr>"
                . "<w:r><w:t xml:space=\"preserve\">{$texto}</w:t></w:r></w:p>";

        return $tituloP . $textoP;
    }

    private function bloqueLista(array $b): string
    {
        $xml = '';
        foreach ($b['items'] as $item) {
            $txt = $this->esc($item);
            $xml .= "<w:p><w:pPr><w:ind w:left=\"360\" w:hanging=\"240\"/><w:spacing w:after=\"60\"/></w:pPr>"
                  . "<w:r><w:rPr><w:color w:val=\"" . self::AZUL . "\"/><w:b/></w:rPr>"
                  . "<w:t xml:space=\"preserve\">• </w:t></w:r>"
                  . "<w:r><w:t xml:space=\"preserve\">{$txt}</w:t></w:r></w:p>";
        }
        return $xml;
    }

    private function bloqueBadge(array $b): string
    {
        $colores = [
            'azul'     => ['fondo' => 'DBEAFE', 'texto' => self::AZUL_OSCURO],
            'verde'    => ['fondo' => 'D1FAE5', 'texto' => '065F46'],
            'ambar'    => ['fondo' => 'FEF3C7', 'texto' => '92400E'],
            'rojo'     => ['fondo' => 'FEE2E2', 'texto' => '991B1B'],
            'gris'     => ['fondo' => 'F1F5F9', 'texto' => self::GRIS_TXT],
        ];
        $c = $colores[$b['color']] ?? $colores['azul'];

        $texto = $this->esc(strtoupper($b['texto']));
        return "<w:p><w:r><w:rPr>"
             . "<w:shd w:val=\"clear\" w:fill=\"{$c['fondo']}\"/>"
             . "<w:color w:val=\"{$c['texto']}\"/>"
             . "<w:b/><w:sz w:val=\"18\"/>"
             . "</w:rPr>"
             . "<w:t xml:space=\"preserve\"> {$texto} </w:t></w:r></w:p>";
    }

    private function bloqueEspacio(array $b): string
    {
        $pt = (int) $b['pt'];
        return "<w:p><w:pPr><w:spacing w:before=\"{$pt}\" w:after=\"0\"/></w:pPr></w:p>";
    }

    private function bloqueSeparador(array $b): string
    {
        return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="' . self::GRIS_SUAVE . '"/></w:pBdr></w:pPr></w:p>';
    }

    // ============================================================
    // Tabla moderna
    // ============================================================

    private function bloqueTabla(array $b): string
    {
        $encs     = $b['encabezados'];
        $filas    = $b['filas'];
        $opciones = $b['opciones'] ?? [];
        $coloresCelda = $opciones['colores_celda'] ?? [];

        // Anchos si vienen
        $anchos = $opciones['ancho'] ?? [];
        $totalAncho = 9360; // ancho útil
        $anchoCols = [];
        if ($anchos && count($anchos) === count($encs)) {
            $suma = array_sum($anchos);
            foreach ($anchos as $a) $anchoCols[] = (int) ($totalAncho * ($a / $suma));
        }

        // Estructura de tabla
        $xml  = '<w:tbl><w:tblPr>'
              . '<w:tblStyle w:val="TableGrid"/>'
              . '<w:tblW w:w="9360" w:type="dxa"/>'
              . '<w:tblBorders>'
              . '<w:top w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
              . '<w:left w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
              . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
              . '<w:right w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
              . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
              . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
              . '</w:tblBorders>'
              . '<w:tblCellMar>'
              . '<w:top w:w="80" w:type="dxa"/><w:left w:w="120" w:type="dxa"/>'
              . '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="120" w:type="dxa"/>'
              . '</w:tblCellMar>'
              . '</w:tblPr>';

        // Encabezado
        $xml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
        foreach ($encs as $i => $h) {
            $texto = $this->esc((string) $h);
            $ancho = $anchoCols[$i] ?? null;
            $wPr   = $ancho ? "<w:tcW w:w=\"{$ancho}\" w:type=\"dxa\"/>" : '';
            $xml  .= '<w:tc><w:tcPr>'
                  . $wPr
                  . '<w:shd w:val="clear" w:fill="' . self::AZUL_OSCURO . '"/>'
                  . '<w:vAlign w:val="center"/>'
                  . '</w:tcPr>'
                  . '<w:p><w:pPr><w:pStyle w:val="TblHeader"/></w:pPr>'
                  . "<w:r><w:t xml:space=\"preserve\">{$texto}</w:t></w:r></w:p></w:tc>";
        }
        $xml .= '</w:tr>';

        // Filas
        foreach ($filas as $numFila => $fila) {
            $esPar = ($numFila % 2 === 0);
            $fondo = $esPar ? 'FFFFFF' : self::GRIS_FONDO;

            $xml .= '<w:tr>';
            foreach ($fila as $i => $celda) {
                $texto = $this->esc((string) $celda);
                $ancho = $anchoCols[$i] ?? null;
                $wPr   = $ancho ? "<w:tcW w:w=\"{$ancho}\" w:type=\"dxa\"/>" : '';

                // Color personalizado de celda (ej: VERDE, AMBAR, ROJO)
                $ref = $this->colLetter($i) . ($numFila + 2);
                if (isset($coloresCelda[$ref])) {
                    $mapa = [
                        'VERDE'    => 'D1FAE5',
                        'AMARILLO' => 'FEF3C7',
                        'ROJO'     => 'FEE2E2',
                        'AZUL'     => 'DBEAFE',
                        'GRIS'     => 'F1F5F9',
                    ];
                    $fondo = $mapa[$coloresCelda[$ref]] ?? $fondo;
                }

                $xml .= '<w:tc><w:tcPr>'
                      . $wPr
                      . '<w:shd w:val="clear" w:fill="' . $fondo . '"/>'
                      . '<w:vAlign w:val="center"/>'
                      . '</w:tcPr>'
                      . "<w:p><w:r><w:t xml:space=\"preserve\">{$texto}</w:t></w:r></w:p></w:tc>";
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl><w:p/>';
    }

    // ============================================================
    // Imagen y firmas
    // ============================================================

    private function bloqueImagen(array $b): string
    {
        $idx = $b['idx'];
        $img = $this->imagenes[$idx];
        $cx  = $img['ancho'] * 9525;
        $cy  = (int) ($cx / 3);

        return "<w:p><w:pPr><w:jc w:val=\"center\"/></w:pPr><w:r><w:drawing>
          <wp:inline distT=\"0\" distB=\"0\" distL=\"0\" distR=\"0\">
            <wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/>
            <wp:docPr id=\"" . ($idx + 1) . "\" name=\"Imagen{$idx}\"/>
            <a:graphic>
              <a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/picture\">
                <pic:pic>
                  <pic:nvPicPr>
                    <pic:cNvPr id=\"" . ($idx + 1) . "\" name=\"Imagen{$idx}\"/>
                    <pic:cNvPicPr/>
                  </pic:nvPicPr>
                  <pic:blipFill>
                    <a:blip r:embed=\"rIdImg{$idx}\"/>
                    <a:stretch><a:fillRect/></a:stretch>
                  </pic:blipFill>
                  <pic:spPr>
                    <a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$cx}\" cy=\"{$cy}\"/></a:xfrm>
                    <a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom>
                  </pic:spPr>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing></w:r></w:p>";
    }

    private function bloqueFirmas(array $b): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="9360" w:type="dxa"/><w:tblBorders>'
             . '<w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/>'
             . '<w:right w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/>'
             . '</w:tblBorders></w:tblPr><w:tr>';

        foreach ($b['firmantes'] as $f) {
            $nombre = $this->esc($f['nombre'] ?? '');
            $cargo  = $this->esc($f['cargo'] ?? '');

            $xml .= '<w:tc><w:tcPr><w:tcW w:w="4680" w:type="dxa"/></w:tcPr>'
                  . '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="720"/></w:pPr></w:p>'
                  . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
                  . '<w:r><w:t>_______________________________</w:t></w:r></w:p>'
                  . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
                  . '<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">' . $nombre . '</w:t></w:r></w:p>'
                  . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
                  . '<w:r><w:rPr><w:sz w:val="18"/><w:color w:val="' . self::GRIS_SUAVE . '"/></w:rPr>'
                  . '<w:t xml:space="preserve">' . $cargo . '</w:t></w:r></w:p></w:tc>';
        }

        return $xml . '</w:tr></w:tbl>';
    }

    private function colLetter(int $n): string
    {
        $letras = '';
        while ($n >= 0) {
            $letras = chr(65 + ($n % 26)) . $letras;
            $n = intdiv($n, 26) - 1;
        }
        return $letras;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
