<?php
declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/** Conversor OOXML del subconjunto HTML sanitizado usado por el editor, sin macros ni dependencias externas. */
final class DocxDocument
{
    public static function available(): bool
    {
        return class_exists(DOMDocument::class);
    }

    public static function fromHtml(string $html): string
    {
        if (!self::available()) {
            throw new RuntimeException('DOM no está disponible para crear DOCX.');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="export-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('export-root');
        if (!$loaded || $root === null) {
            throw new RuntimeException('No fue posible interpretar el documento para exportarlo.');
        }
        $body = self::blockChildren($root);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>';
        $zip = new ZipBuilder();
        $zip->add('[Content_Types].xml', self::contentTypes());
        $zip->add('_rels/.rels', self::rootRelationships());
        $zip->add('word/document.xml', $xml);
        $zip->add('word/styles.xml', self::styles());
        $zip->add('word/_rels/document.xml.rels', self::documentRelationships());
        return $zip->build();
    }

    private static function blockChildren(DOMElement $parent): string
    {
        $xml = '';
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $xml .= self::block($child);
            } elseif ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue ?? '') !== '') {
                $xml .= self::paragraph(self::inline($child));
            }
        }
        return $xml;
    }

    private static function block(DOMElement $element): string
    {
        $tag = strtolower($element->tagName);
        if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'blockquote'], true)) {
            $style = match ($tag) {
                'h1' => 'Heading1', 'h2' => 'Heading2', 'h3' => 'Heading3', 'h4' => 'Heading4', default => null,
            };
            return self::paragraph(self::inline($element), $style);
        }
        if ($tag === 'hr') {
            return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="B8C2CC"/></w:pBdr></w:pPr></w:p>';
        }
        if (in_array($tag, ['ul', 'ol'], true)) {
            $xml = '';
            $number = 0;
            foreach ($element->childNodes as $child) {
                if (!$child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                    continue;
                }
                $number++;
                $prefix = $tag === 'ol' ? $number . '. ' : '• ';
                $xml .= self::paragraph(self::run($prefix) . self::inline($child));
            }
            return $xml;
        }
        if ($tag === 'table') {
            return self::table($element);
        }
        if (in_array($tag, ['span', 'strong', 'b', 'em', 'i'], true)) {
            return self::paragraph(self::inline($element));
        }
        return self::blockChildren($element);
    }

    private static function table(DOMElement $table): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:color="AAB4C0"/><w:left w:val="single" w:sz="4" w:color="AAB4C0"/>'
            . '<w:bottom w:val="single" w:sz="4" w:color="AAB4C0"/><w:right w:val="single" w:sz="4" w:color="AAB4C0"/>'
            . '<w:insideH w:val="single" w:sz="4" w:color="AAB4C0"/><w:insideV w:val="single" w:sz="4" w:color="AAB4C0"/>'
            . '</w:tblBorders><w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr>';
        foreach ($table->childNodes as $section) {
            if (!$section instanceof DOMElement) {
                continue;
            }
            if (strtolower($section->tagName) === 'tr') {
                $xml .= self::tableRow($section);
            } elseif (in_array(strtolower($section->tagName), ['thead', 'tbody', 'tfoot'], true)) {
                foreach ($section->childNodes as $row) {
                    if ($row instanceof DOMElement && strtolower($row->tagName) === 'tr') {
                        $xml .= self::tableRow($row);
                    }
                }
            }
        }
        return $xml . '</w:tbl><w:p/>';
    }

    private static function tableRow(DOMElement $row): string
    {
        $xml = '<w:tr>';
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof DOMElement || !in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                continue;
            }
            $properties = '';
            if (strtolower($cell->tagName) === 'th') {
                $properties .= '<w:shd w:val="clear" w:color="auto" w:fill="EAF0F6"/>';
            }
            $colspan = max(1, (int) $cell->getAttribute('colspan'));
            if ($colspan > 1) {
                $properties .= '<w:gridSpan w:val="' . $colspan . '"/>';
            }
            $rowspan = max(1, (int) $cell->getAttribute('rowspan'));
            if ($rowspan > 1) {
                $properties .= '<w:vMerge w:val="restart"/>';
            }
            $content = self::blockChildren($cell);
            if ($content === '') {
                $content = '<w:p/>';
            }
            $xml .= '<w:tc><w:tcPr>' . $properties . '</w:tcPr>' . $content . '</w:tc>';
        }
        return $xml . '</w:tr>';
    }

    private static function paragraph(string $runs, ?string $style = null): string
    {
        $properties = $style !== null ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '';
        return '<w:p>' . $properties . $runs . '</w:p>';
    }

    private static function inline(DOMNode $node, bool $bold = false, bool $italic = false): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return self::run((string) $node->nodeValue, $bold, $italic);
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if ($tag === 'br') {
            return '<w:r><w:br/></w:r>';
        }
        $bold = $bold || in_array($tag, ['strong', 'b', 'th'], true);
        $italic = $italic || in_array($tag, ['em', 'i'], true);
        $xml = '';
        foreach ($node->childNodes as $child) {
            $xml .= self::inline($child, $bold, $italic);
        }
        return $xml;
    }

    private static function run(string $text, bool $bold = false, bool $italic = false): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
        if ($text === '') {
            return '';
        }
        $properties = ($bold ? '<w:b/>' : '') . ($italic ? '<w:i/>' : '');
        return '<w:r>' . ($properties !== '' ? '<w:rPr>' . $properties . '</w:rPr>' : '')
            . '<w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private static function documentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="20"/></w:rPr></w:rPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:pPr><w:spacing w:after="100" w:line="240" w:lineRule="auto"/></w:pPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="140"/></w:pPr><w:rPr><w:b/><w:sz w:val="32"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="220" w:after="100"/></w:pPr><w:rPr><w:b/><w:sz w:val="25"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/></w:pPr><w:rPr><w:b/><w:sz w:val="22"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading4"><w:name w:val="heading 4"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:i/></w:rPr></w:style>'
            . '</w:styles>';
    }
}
