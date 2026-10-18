<?php
namespace App;

use ZipArchive;

class XlsxBuilder
{
    private array $hojas = [];

    // Estilos (índices en cellXfs)
    const S_DEFAULT  = 0;
    const S_HEADER   = 1;   // Azul oscuro + blanco negrita + center
    const S_TITLE    = 2;   // Azul oscuro, 18pt negrita
    const S_SUBTITLE = 3;   // Azul, 14pt negrita
    const S_LABEL    = 4;   // Gris fondo, negrita
    const S_VALUE    = 5;   // Blanco normal
    const S_VERDE    = 6;
    const S_AMBAR    = 7;
    const S_ROJO     = 8;
    const S_AZUL_CLARO = 9;
    const S_BOLD     = 10;
    const S_CENTER   = 11;
    const S_WRAP     = 12;  // Con wrap text

    public function agregarHoja(string $nombre, array $filas, array $opciones = []): self
    {
        $this->hojas[] = [
            'nombre'    => substr($nombre, 0, 31),
            'filas'     => $filas,      // array de ['valor', 'estilo_idx', 'colspan']
            'anchos'    => $opciones['anchos']  ?? [],
            'merges'    => $opciones['merges']  ?? [],
            'freeze'    => $opciones['freeze']  ?? null, // 'A2' congela fila 1
            'filtros'   => $opciones['filtros'] ?? null, // rango para autofiltro
        ];
        return $this;
    }

    public function guardar(string $rutaDestino): string
    {
        $zip = new ZipArchive();
        $zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->xmlContentTypes());
        $zip->addFromString('_rels/.rels',         $this->xmlRels());
        $zip->addFromString('xl/workbook.xml',     $this->xmlWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xmlWorkbookRels());
        $zip->addFromString('xl/styles.xml',       $this->xmlStyles());

        foreach ($this->hojas as $i => $hoja) {
            $n = $i + 1;
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $this->xmlSheet($hoja));
        }

        $zip->close();
        return $rutaDestino;
    }

    // ============================================================
    // XML estructural
    // ============================================================

    private function xmlContentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach ($this->hojas as $i => $h) {
            $n = $i + 1;
            $xml .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        return $xml . '</Types>';
    }

    private function xmlRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function xmlWorkbook(): string
    {
        $sheets = '';
        foreach ($this->hojas as $i => $h) {
            $n = $i + 1;
            $nombre = htmlspecialchars($h['nombre'], ENT_XML1, 'UTF-8');
            $sheets .= "<sheet name=\"{$nombre}\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>' . $sheets . '</sheets>
</workbook>';
    }

    private function xmlWorkbookRels(): string
    {
        $rels = '';
        foreach ($this->hojas as $i => $h) {
            $n = $i + 1;
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
        }
        $rels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    // ============================================================
    // Estilos
    // ============================================================

    private function xmlStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">

  <fonts count="8">
    <font><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    <font><b/><sz val="18"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>
    <font><b/><sz val="14"/><color rgb="FF2563EB"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>
    <font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FF065F46"/><name val="Calibri"/></font>
  </fonts>

  <fills count="8">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
  </fills>

  <borders count="3">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
  </borders>

  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>

  <cellXfs count="13">
    <!-- 0 default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>

    <!-- 1 header: azul oscuro, blanco, bold, center, borde -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"
        applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>

    <!-- 2 title: 18pt negrita azul oscuro -->
    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"
        applyFont="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center"/>
    </xf>

    <!-- 3 subtitle: 14pt negrita azul -->
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"
        applyFont="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center"/>
    </xf>

    <!-- 4 label: fondo azul claro, bold -->
    <xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0"
        applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>

    <!-- 5 value: borde normal -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"
        applyBorder="1" applyAlignment="1">
      <alignment vertical="center" wrapText="1"/>
    </xf>

    <!-- 6 verde -->
    <xf numFmtId="0" fontId="7" fillId="4" borderId="1" xfId="0"
        applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>

    <!-- 7 ámbar -->
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0"
        applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>

    <!-- 8 rojo -->
    <xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0"
        applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>

    <!-- 9 azul claro -->
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"
        applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>

    <!-- 10 bold -->
    <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0"
        applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>

    <!-- 11 center -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"
        applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>

    <!-- 12 wrap -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"
        applyBorder="1" applyAlignment="1">
      <alignment vertical="top" wrapText="1"/>
    </xf>
  </cellXfs>
</styleSheet>';
    }

    // ============================================================
    // Sheet
    // ============================================================

    private function xmlSheet(array $hoja): string
    {
        // Anchos de columna
        $colsXml = '';
        if (!empty($hoja['anchos'])) {
            $colsXml = '<cols>';
            foreach ($hoja['anchos'] as $col => $ancho) {
                $idx = $this->colIndex($col) + 1;
                $colsXml .= "<col min=\"{$idx}\" max=\"{$idx}\" width=\"{$ancho}\" customWidth=\"1\"/>";
            }
            $colsXml .= '</cols>';
        }

        // Freeze panes
        $freezeXml = '';
        if (!empty($hoja['freeze'])) {
            $parts = explode(':', $hoja['freeze']);
            $celda = $parts[0];
            $fila  = (int) preg_replace('/[A-Z]/', '', $celda) - 1;
            $col   = $this->colIndex(preg_replace('/[0-9]/', '', $celda));
            $topLeft = $this->colLetter($col) . ($fila + 1);
            $freezeXml = "<pane ySplit=\"{$fila}\" topLeftCell=\"{$topLeft}\" activePane=\"bottomLeft\" state=\"frozen\"/>"
                       . '<selection pane="bottomLeft"/>';
        }

        // Autofiltro
        $filtroXml = '';
        if (!empty($hoja['filtros'])) {
            $filtroXml = "<autoFilter ref=\"{$hoja['filtros']}\"/>";
        }

        // Merges
        $mergeXml = '';
        if (!empty($hoja['merges'])) {
            $mergeXml = '<mergeCells count="' . count($hoja['merges']) . '">';
            foreach ($hoja['merges'] as $m) {
                $mergeXml .= "<mergeCell ref=\"{$m}\"/>";
            }
            $mergeXml .= '</mergeCells>';
        }

        // Filas
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews><sheetView workbookViewId="0">' . $freezeXml . '</sheetView></sheetViews>
  ' . $colsXml . '
  <sheetData>';

        foreach ($hoja['filas'] as $numFila => $fila) {
            $filaNum = $numFila + 1;
            $xml .= "<row r=\"{$filaNum}\">";

            foreach ($fila as $numCol => $celda) {
                if (!is_array($celda)) {
                    $celda = ['valor' => $celda];
                }

                $valor  = $celda['valor']  ?? null;
                $estilo = $celda['estilo'] ?? self::S_DEFAULT;

                if ($valor === null || $valor === '') continue;

                $col = $this->colLetter($numCol);
                $ref = "{$col}{$filaNum}";

                if (is_int($valor) || is_float($valor)) {
                    $xml .= "<c r=\"{$ref}\" s=\"{$estilo}\"><v>{$valor}</v></c>";
                } else {
                    $texto = htmlspecialchars((string) $valor, ENT_XML1, 'UTF-8');
                    $xml .= "<c r=\"{$ref}\" s=\"{$estilo}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$texto}</t></is></c>";
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $xml .= $mergeXml;
        $xml .= $filtroXml;
        $xml .= '</worksheet>';
        return $xml;
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

    private function colIndex(string $col): int
    {
        $idx = 0; $len = strlen($col);
        for ($i = 0; $i < $len; $i++) $idx = $idx * 26 + (ord($col[$i]) - 64);
        return $idx - 1;
    }
}
