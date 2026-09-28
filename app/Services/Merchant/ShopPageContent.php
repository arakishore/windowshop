<?php

namespace App\Services\Merchant;

use DOMDocument;
use DOMElement;
use DOMNode;

class ShopPageContent
{
    private const TAGS = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'blockquote', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'meta', 'link'];

    public function render(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        if (! preg_match('/<\/?[a-z][^>]*>/i', $body)) {
            return nl2br(e($body));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8"?><div id="shop-page-content">'.$body.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementById('shop-page-content');
        if (! $root) {
            return e($body);
        }

        $this->cleanChildren($root);

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                if ($node->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($node);
                }
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP, true)) {
                $parent->removeChild($node);
                continue;
            }

            $this->cleanChildren($node);
            if (! in_array($tag, self::TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';
            while ($node->attributes->length) {
                $node->removeAttributeNode($node->attributes->item(0));
            }
            if ($tag === 'a' && $href !== '' && preg_match('~^(https?://|mailto:|tel:|/(?!/))~i', $href)) {
                $node->setAttribute('href', $href);
                $node->setAttribute('rel', 'nofollow noopener noreferrer');
            }
        }
    }
}
