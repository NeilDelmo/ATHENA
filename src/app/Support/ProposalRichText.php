<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class ProposalRichText
{
    /**
     * @return list<array{type: 'paragraph'|'ordered'|'unordered', runs: list<array{text: string, bold: bool, italic: bool, underline: bool, break: bool}>}>
     */
    public function blocks(string $value): array
    {
        $document = $this->document($this->sanitize($value));
        $wrapper = $document->getElementById('proposal-rich-text');

        if (! $wrapper instanceof DOMElement) {
            return [];
        }

        $blocks = [];

        foreach ($wrapper->childNodes as $node) {
            if ($node instanceof DOMText && trim($node->textContent) === '') {
                continue;
            }

            if ($node instanceof DOMElement && in_array($node->tagName, ['ol', 'ul'], true)) {
                foreach ($node->childNodes as $item) {
                    if (! $item instanceof DOMElement || $item->tagName !== 'li') {
                        continue;
                    }

                    $blocks[] = [
                        'type' => $node->tagName === 'ol' ? 'ordered' : 'unordered',
                        'runs' => $this->runs($item),
                    ];
                }

                continue;
            }

            $blocks[] = [
                'type' => 'paragraph',
                'runs' => $this->runs($node),
            ];
        }

        return collect($blocks)
            ->filter(fn (array $block): bool => collect($block['runs'])->contains(
                fn (array $run): bool => $run['break'] || trim($run['text']) !== '',
            ))
            ->values()
            ->all();
    }

    public function sanitize(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if (strip_tags($value) === $value) {
            $value = collect(preg_split('/\n{2,}/u', $value) ?: [])
                ->map(fn (string $paragraph): string => '<p>'.nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>')
                ->filter(fn (string $paragraph): bool => $paragraph !== '<p></p>')
                ->implode('');
        }

        $document = $this->document($value);
        $wrapper = $document->getElementById('proposal-rich-text');

        if (! $wrapper instanceof DOMElement) {
            return '';
        }

        return collect(iterator_to_array($wrapper->childNodes))
            ->map(fn (DOMNode $node): string => $this->sanitizeNode($node))
            ->filter()
            ->implode('');
    }

    private function document(string $value): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="proposal-rich-text">'.$value.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function sanitizeNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->wholeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement || in_array($node->tagName, ['script', 'style'], true)) {
            return '';
        }

        $content = collect(iterator_to_array($node->childNodes))
            ->map(fn (DOMNode $child): string => $this->sanitizeNode($child))
            ->implode('');
        $tag = match ($node->tagName) {
            'b', 'strong' => 'strong',
            'i', 'em' => 'em',
            'u' => 'u',
            'br' => 'br',
            'p', 'div' => 'p',
            'ol' => 'ol',
            'ul' => 'ul',
            'li' => 'li',
            'span' => 'span',
            default => null,
        };

        if ($tag === null) {
            return $content;
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'span') {
            $citationSourceId = trim($node->getAttribute('data-proposal-citation'));

            return ctype_digit($citationSourceId)
                ? '<span data-proposal-citation="'.$citationSourceId.'">'.$content.'</span>'
                : $content;
        }

        return '<'.$tag.'>'.$content.'</'.$tag.'>';
    }

    /**
     * @return list<array{text: string, bold: bool, italic: bool, underline: bool, break: bool}>
     */
    private function runs(
        DOMNode $node,
        bool $bold = false,
        bool $italic = false,
        bool $underline = false,
    ): array {
        if ($node instanceof DOMText) {
            return [[
                'text' => $node->wholeText,
                'bold' => $bold,
                'italic' => $italic,
                'underline' => $underline,
                'break' => false,
            ]];
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        if ($node->tagName === 'br') {
            return [[
                'text' => '',
                'bold' => $bold,
                'italic' => $italic,
                'underline' => $underline,
                'break' => true,
            ]];
        }

        $bold = $bold || $node->tagName === 'strong';
        $italic = $italic || $node->tagName === 'em';
        $underline = $underline || $node->tagName === 'u';

        return collect(iterator_to_array($node->childNodes))
            ->flatMap(fn (DOMNode $child): array => $this->runs($child, $bold, $italic, $underline))
            ->all();
    }
}
