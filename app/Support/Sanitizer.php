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

        $allowed = ['div', 'section', 'header', 'footer', 'h1', 'h2', 'h3', 'h4', 'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'blockquote'];
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
            while ($child->attributes->length > 0) {
                $child->removeAttributeNode($child->attributes->item(0));
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
