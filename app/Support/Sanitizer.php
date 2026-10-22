<?php
declare(strict_types=1);

namespace App\Support;

final class Sanitizer
{
    public static function html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists(\DOMDocument::class)) {
            return '<p>' . nl2br(e(trim(strip_tags($html)))) . '</p>';
        }

        $allowed = ['article', 'div', 'section', 'header', 'footer', 'h1', 'h2', 'h3', 'h4', 'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'table', 'caption', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'blockquote'];
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="sanitizer-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('sanitizer-root');
        if ($root === null) {
            return '<p>' . nl2br(e(trim(strip_tags($html)))) . '</p>';
        }

        self::cleanNode($root, $allowed);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }
        return $output;
    }

    private static function cleanNode(\DOMElement $element, array $allowed): void
    {
        for ($index = $element->childNodes->length - 1; $index >= 0; $index--) {
            $child = $element->childNodes->item($index);
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input', 'button'], true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }
            self::cleanNode($child, $allowed);
            $documentClasses = ['sst-document', 'document-header', 'document-kicker', 'document-meta', 'document-body', 'document-footer', 'document-notice'];
            $safeClass = '';
            $safeSpans = [];
            while ($child->attributes->length > 0) {
                $attribute = $child->attributes->item(0);
                if ($attribute === null) {
                    break;
                }
                $attributeName = text_lower($attribute->nodeName);
                if (in_array($tag, ['td', 'th'], true) && in_array($attributeName, ['colspan', 'rowspan'], true)) {
                    $span = filter_var($attribute->nodeValue, FILTER_VALIDATE_INT);
                    $maximum = $attributeName === 'colspan' ? 50 : 500;
                    if ($span !== false && $span >= 1 && $span <= $maximum) {
                        $safeSpans[$attributeName] = $span;
                    }
                }
                if ($attributeName === 'class') {
                    $classes = preg_split('/\s+/', trim($attribute->nodeValue ?? '')) ?: [];
                    $classes = array_values(array_intersect($classes, $documentClasses));
                    $safeClass = implode(' ', $classes);
                }
                $child->removeAttributeNode($attribute);
            }
            if ($safeClass !== '') {
                $child->setAttribute('class', $safeClass);
            }
            foreach ($safeSpans as $name => $span) {
                $child->setAttribute($name, (string) $span);
            }
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild !== null) {
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);
            }
        }
    }
}
