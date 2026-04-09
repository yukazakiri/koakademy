<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

final class HtmlToPortableText
{
    public function convert(string $html): array
    {
        if (in_array(mb_trim($html), ['', '0'], true)) {
            return [];
        }

        $dom = new DOMDocument();
        // Use error suppression for HTML5 tags not recognized by standard DOMDocument
        libxml_use_internal_errors(true);
        // Force UTF-8
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $blocks = [];
        foreach ($dom->childNodes as $node) {
            $block = $this->nodeToBlock($node);
            if ($block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function nodeToBlock(DOMNode $node): ?array
    {
        if ($node instanceof DOMElement) {
            $tagName = mb_strtolower($node->tagName);

            // Handle Images
            if ($tagName === 'img') {
                $src = $node->getAttribute('src');
                $alt = $node->getAttribute('alt');
                $title = $node->getAttribute('title'); // We used title for tooltip/caption sometimes

                $assetRef = $this->extractAssetRefFromUrl($src);

                if ($assetRef) {
                    return [
                        '_type' => 'image',
                        '_key' => uniqid(),
                        'asset' => [
                            '_type' => 'reference',
                            '_ref' => $assetRef,
                        ],
                        'alt' => $alt ?: '',
                        'caption' => $title ?: '', // Map title to caption for simplicity
                    ];
                }

                return null;
            }

            // Handle Blocks (p, h1-h6, blockquote)
            $style = 'normal';
            if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $style = $tagName;
            } elseif ($tagName === 'blockquote') {
                $style = 'blockquote';
            }

            // Check for valid block containers
            if (in_array($tagName, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol', 'li'])) {
                // For lists, we might need more complex handling, but let's treat them as blocks for now
                // or just Flatten text content.
                // Simple implementation: Just treat as a text block.

                return [
                    '_type' => 'block',
                    '_key' => uniqid(),
                    'style' => $style,
                    'markDefs' => [],
                    'children' => $this->extractChildren($node),
                ];
            }
        }

        return null;
    }

    private function extractChildren(DOMNode $node): array
    {
        $children = [];

        if (! $node->hasChildNodes()) {
            // If empty tag, maybe just return empty text?
            // But if it's text content itself...
            if ($node->nodeType === XML_TEXT_NODE) {
                return [[
                    '_type' => 'span',
                    '_key' => uniqid(),
                    'marks' => [],
                    'text' => $node->textContent,
                ]];
            }

            return [];
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->textContent;
                // Skip empty text nodes unless significant?
                // Tiptap usually puts &nbsp; or <br> for empty lines.

                $children[] = [
                    '_type' => 'span',
                    '_key' => uniqid(),
                    'marks' => [],
                    'text' => $text,
                ];
            } elseif ($child instanceof DOMElement) {
                // Handle inline marks: strong, em, code, a
                $marks = [];
                $tagName = mb_strtolower($child->tagName);

                if ($tagName === 'strong' || $tagName === 'b') {
                    $marks[] = 'strong';
                }
                if ($tagName === 'em' || $tagName === 'i') {
                    $marks[] = 'em';
                }
                if ($tagName === 'code') {
                    $marks[] = 'code';
                }
                // Underline, strike-through etc. can be added if Sanity schema supports them

                // Recursively get text. This simplifies nested marks (e.g. <b><i>text</i></b>)
                // For a robust solution, we'd need to handle markDefs for links.

                $text = $child->textContent;

                $span = [
                    '_type' => 'span',
                    '_key' => uniqid(),
                    'marks' => $marks,
                    'text' => $text,
                ];

                if ($tagName === 'a') {
                    // Links need a markDef
                    $href = $child->getAttribute('href');
                    $defKey = uniqid('link-');
                    // We can't easily add markDefs to the parent block here without passing context.
                    // Simplified: We'll just treat it as text for now to avoid complexity,
                    // OR we need to return markDefs alongside children.

                    // Let's defer link support or just keep text.
                    // For now, keep text.
                }

                $children[] = $span;
            }
        }

        // Ensure there's at least one child
        if ($children === []) {
            $children[] = [
                '_type' => 'span',
                '_key' => uniqid(),
                'marks' => [],
                'text' => '',
            ];
        }

        return $children;
    }

    private function extractAssetRefFromUrl(string $url): ?string
    {
        // Example: https://cdn.sanity.io/images/project/dataset/hash-widthxheight.extension
        // Ref: image-hash-widthxheight-extension

        // Parse the filename
        $parts = explode('/', $url);
        $filename = end($parts); // hash-widthxheight.extension

        // Split extension
        $dotPos = mb_strrpos($filename, '.');
        if ($dotPos === false) {
            return null;
        }

        $base = mb_substr($filename, 0, $dotPos); // hash-widthxheight
        $ext = mb_substr($filename, $dotPos + 1); // extension

        // Sanity ref format uses dash before extension
        return "image-{$base}-{$ext}";
    }
}
