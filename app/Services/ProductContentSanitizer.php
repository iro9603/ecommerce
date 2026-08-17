<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class ProductContentSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED_ELEMENTS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'b' => [],
        'blockquote' => [],
        'br' => [],
        'code' => [],
        'em' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'hr' => [],
        'i' => [],
        'li' => [],
        'ol' => [],
        'p' => [],
        'pre' => [],
        's' => [],
        'span' => [],
        'strong' => [],
        'table' => [],
        'tbody' => [],
        'td' => [],
        'th' => [],
        'thead' => [],
        'tr' => [],
        'u' => [],
        'ul' => [],
    ];

    /** @var list<string> */
    private const REMOVE_WITH_CONTENT = [
        'base', 'button', 'canvas', 'embed', 'form', 'frame', 'frameset', 'iframe', 'input',
        'link', 'math', 'meta', 'noscript', 'object', 'option', 'script', 'select', 'source',
        'style', 'svg', 'template', 'textarea', 'video', 'audio',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousSetting = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset=UTF-8></head><body>'
                    .'<div id=product-content-root>'.$html.'</div>'
                    .'</body></html>',
                LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $root = $document->getElementById('product-content-root');

            if (! $root instanceof DOMElement) {
                return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $this->sanitizeChildren($root);

            $sanitized = '';
            foreach (iterator_to_array($root->childNodes) as $child) {
                $sanitized .= $document->saveHTML($child);
            }

            return $sanitized;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);

            if (! array_key_exists($tag, self::ALLOWED_ELEMENTS)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }

                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, self::ALLOWED_ELEMENTS[$tag]);
        }
    }

    /** @param list<string> $allowedAttributes */
    private function sanitizeAttributes(DOMElement $element, array $allowedAttributes): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if (strtolower($element->tagName) !== 'a') {
            return;
        }

        if ($element->hasAttribute('href') && ! $this->isSafeUrl($element->getAttribute('href'))) {
            $element->removeAttribute('href');
        }

        if ($element->hasAttribute('target') && ! in_array($element->getAttribute('target'), ['_blank', '_self'], true)) {
            $element->removeAttribute('target');
        }

        if ($element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        } elseif ($element->hasAttribute('rel')) {
            $element->removeAttribute('rel');
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $decoded = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $compact = preg_replace('/[\x00-\x20\x7F]+/u', '', $decoded) ?? '';

        if ($compact === '' || str_starts_with($compact, '#') || str_starts_with($compact, '/')) {
            return ! str_starts_with($compact, '//');
        }

        $scheme = strtolower((string) parse_url($compact, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
