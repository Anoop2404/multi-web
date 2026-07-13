<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Allow-list HTML sanitizer for CMS rich text and embeds (FRD-20 XSS hardening).
 * No external package required — uses DOMDocument.
 */
class HtmlSanitizer
{
    /** @var list<string> */
    private const RICH_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'a', 'h2', 'h3', 'h4', 'blockquote', 'span', 'div', 'table', 'thead',
        'tbody', 'tr', 'th', 'td',
    ];

    /** @var list<string> */
    private const RICH_ATTRS = ['href', 'title', 'target', 'rel', 'class'];

    /** @var list<string> */
    public const HTML_CONFIG_KEYS = [
        'content', 'org_chart', 'history', 'body', 'html', 'content_html',
        'description_html', 'bio', 'message',
    ];

    /** @var list<string> */
    public const EMBED_CONFIG_KEYS = [
        'map_embed', 'tour_embed', 'video_embed', 'embed', 'iframe',
    ];

    public static function rich(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::sanitize($html, self::RICH_TAGS, self::RICH_ATTRS, false);
    }

    public static function embed(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::sanitize($html, ['iframe', 'div', 'p'], ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'title', 'class', 'style'], true);
    }

    /** Sanitize a section config array in place (returns new array). */
    public static function sanitizeConfig(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = self::sanitizeConfig($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if (in_array($key, self::EMBED_CONFIG_KEYS, true)) {
                $config[$key] = self::embed($value);
            } elseif (in_array($key, self::HTML_CONFIG_KEYS, true) || str_ends_with($key, '_html')) {
                $config[$key] = self::rich($value);
            }
        }

        return $config;
    }

    /**
     * @param  list<string>  $allowedTags
     * @param  list<string>  $allowedAttrs
     */
    private static function sanitize(string $html, array $allowedTags, array $allowedAttrs, bool $embedMode): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__root');
        if (! $root) {
            return '';
        }

        self::walk($root, $allowedTags, $allowedAttrs, $embedMode);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    /**
     * @param  list<string>  $allowedTags
     * @param  list<string>  $allowedAttrs
     */
    private static function walk(DOMNode $node, array $allowedTags, array $allowedAttrs, bool $embedMode): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        /** @var list<DOMNode> $children */
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE || ! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            // Never unwrap script/style — drop the whole node (including text).
            if (in_array($tag, ['script', 'style', 'object', 'embed', 'link', 'meta'], true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, $allowedTags, true)) {
                // unwrap: keep children
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            // Strip disallowed attributes
            $remove = [];
            foreach ($child->attributes ?? [] as $attr) {
                $name = strtolower($attr->name);
                if (! in_array($name, $allowedAttrs, true)) {
                    $remove[] = $attr->name;

                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $val = trim($attr->value);
                    if (preg_match('#^\s*javascript:#i', $val) || preg_match('#^\s*data:#i', $val)) {
                        $remove[] = $attr->name;
                    }
                }
                if ($name === 'src' && $embedMode && ! self::isAllowedEmbedSrc($attr->value)) {
                    $remove[] = $attr->name;
                }
            }
            foreach ($remove as $name) {
                $child->removeAttribute($name);
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
                if ($child->hasAttribute('target') && $child->getAttribute('target') === '_blank') {
                    // keep
                }
            }

            if ($tag === 'iframe' && ! $child->hasAttribute('src')) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child, $allowedTags, $allowedAttrs, $embedMode);
        }
    }

    private static function isAllowedEmbedSrc(string $src): bool
    {
        $src = trim($src);
        if ($src === '' || ! str_starts_with($src, 'https://')) {
            return false;
        }

        $host = parse_url($src, PHP_URL_HOST);
        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);
        $allowed = [
            'www.google.com', 'maps.google.com', 'www.google.co.in',
            'www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com',
            'player.vimeo.com', 'www.facebook.com',
        ];

        foreach ($allowed as $ok) {
            if ($host === $ok || str_ends_with($host, '.'.$ok)) {
                return true;
            }
        }

        return false;
    }
}
