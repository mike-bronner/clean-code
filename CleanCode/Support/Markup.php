<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

final class Markup
{
    private const HTML_TAGS = [
        'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio',
        'b', 'blockquote', 'body', 'br', 'button',
        'canvas', 'caption', 'cite', 'code', 'col', 'colgroup',
        'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'dt',
        'em', 'embed',
        'fieldset', 'figcaption', 'figure', 'footer', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html',
        'i', 'iframe', 'img', 'input', 'ins',
        'kbd',
        'label', 'legend', 'li', 'link',
        'main', 'map', 'mark', 'menu', 'meta', 'meter',
        'nav',
        'object', 'ol', 'optgroup', 'option', 'output',
        'p', 'param', 'picture', 'pre', 'progress',
        'q',
        's', 'samp', 'script', 'section', 'select', 'small', 'source', 'span',
        'strong', 'style', 'sub', 'summary', 'sup',
        'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead',
        'time', 'title', 'tr', 'track',
        'u', 'ul',
        'var', 'video',
        'wbr',
    ];

    public static function containsHtmlElement(string $text): bool
    {
        return preg_match(self::elementPattern(), $text) === 1;
    }

    public static function tagSpanPattern(): string
    {
        return '#<(?:' . implode('|', self::HTML_TAGS) . ')(?=[\s/>])'
            . '(?:"[^"]*"|\'[^\']*\'|[^<>"\'])*>#i';
    }

    private static function elementPattern(): string
    {
        return '#</?(?:' . implode('|', self::HTML_TAGS) . ')(?=[\s/>])[^<>]*>#i';
    }
}
