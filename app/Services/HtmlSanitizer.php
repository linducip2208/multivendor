<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

class HtmlSanitizer
{
    private const TAGS = '<p><br><ul><ol><li><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><hr>';

    public function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $safeHtml = strip_tags($html, self::TAGS);
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$safeHtml.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            $this->sanitizeAttributes($element);
        }

        $root = $document->getElementsByTagName('div')->item(0);
        if (! $root) {
            return $safeHtml;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $allowed = match ($element->tagName) {
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'width', 'height'],
            'td', 'th' => ['colspan', 'rowspan'],
            default => [],
        };
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        foreach (['href', 'src'] as $attribute) {
            if (! $element->hasAttribute($attribute)) {
                continue;
            }
            $value = trim($element->getAttribute($attribute));
            if (! preg_match('~^(https?://|mailto:|/|#)~i', $value)) {
                $element->removeAttribute($attribute);
            }
        }
        if ($element->tagName === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }
}
