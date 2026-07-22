<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

/**
 * Shared HTML-markup detection used by the Strings standard sniffs.
 *
 * The clean-code Strings standard treats "HTML or other renderable code"
 * specially (HereDocs for markup blocks, double-quoted HTML attributes). Both
 * the RequireHeredocForMarkup and HtmlAttributeQuotes sniffs need to decide
 * whether a string literal actually contains HTML, so that logic lives here
 * once rather than being duplicated (and drifting) across sniffs.
 *
 * Detection is deliberately conservative on two axes. It matches an open/close
 * tag whose name is a known HTML element (`<div>`, `</span>`, `<br/>`) — keying
 * off a curated element list, instead of "any `<letter`", keeps `'a < b'`,
 * `'List<int>'`, or `'x<y'` from being mistaken for markup. And the element
 * name must be followed by tag-like structure — whitespace, `/`, or `>` — and
 * an eventual closing `>`, so shell redirection (`<input.txt >out`) or a C
 * include (`<time.h>`) never masquerades as an `<input>`/`<time>` element.
 */
final class Markup
{
    /**
     * Known HTML element names. A string counts as markup only when it opens
     * or closes one of these; generics and comparisons never match.
     *
     * @var array<int, string>
     */
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

    /**
     * Whether $text contains a recognizable HTML element tag.
     */
    public static function containsHtmlElement(string $text): bool
    {
        return preg_match(self::elementPattern(), $text) === 1;
    }

    /**
     * Case-insensitive pattern capturing a single opening or self-closing tag
     * of a known element as its whole `<tag …>` span. Closing tags are
     * excluded because they carry no attributes — callers use this to scope an
     * attribute rewrite to real tag spans, leaving prose between tags untouched.
     */
    public static function tagSpanPattern(): string
    {
        return '#<(?:' . implode('|', self::HTML_TAGS) . ')(?=[\s/>])[^<>]*>#i';
    }

    /**
     * Case-insensitive pattern matching an opening or closing tag for any of
     * the known element names. The `(?=[\s/>])` lookahead requires the name to
     * be followed by tag structure (whitespace, `/`, or `>`) rather than a bare
     * word boundary — so `<input.txt` (a shell path) does not match `<input>` —
     * and the trailing `[^<>]*>` requires the tag to actually close.
     */
    private static function elementPattern(): string
    {
        return '#</?(?:' . implode('|', self::HTML_TAGS) . ')(?=[\s/>])[^<>]*>#i';
    }
}
