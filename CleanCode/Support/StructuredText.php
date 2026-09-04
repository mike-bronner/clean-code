<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

// Whether a string's content is another language embedded in PHP — HTML, XML,
// SQL, JSON, YAML, an INI or config block, or markdown — rather than prose.
//
// The distinction earns a HEREDOC at any length. An editor highlights a HEREDOC
// body as the language its marker names and a quoted string as one flat run of
// characters, so the marker is what makes embedded code readable, and length has
// nothing to do with it.
//
// Every signal is deliberately narrow and anchored. A false positive tells a
// developer to restructure a sentence, so the cost of guessing is real, and the
// sniff that uses this reports rather than fixes.
class StructuredText
{
    // Anchored at the start, so a sentence merely containing the word "select"
    // or "update" is not read as a query.
    private const SQL_OPENERS = [
        'ALTER',
        'CREATE',
        'DELETE',
        'DROP',
        'INSERT',
        'MERGE',
        'SELECT',
        'TRUNCATE',
        'UPDATE',
        'WITH',
    ];

    // A heading, list item, table row, blockquote, or fence, each anchored to a
    // line start: a hyphen or a pipe mid-sentence is punctuation.
    private const MARKDOWN_LINE = '/(?:^|\R)\s*(?:#{1,6}\s|[-*+]\s+\S|\|\s|>\s|```)/';

    // Split from their opening `<` on purpose, so no literal declaration
    // appears in this file: written whole, the pattern matches its own source
    // and the sniff reports itself.
    private const XML_MARKERS = [
        '\?xml\b',
        '!DOCTYPE\b',
        '!\[CDATA\[',
    ];

    // A bracketed section header on its own line, the shape shared by INI, TOML
    // and most config formats.
    private const INI_SECTION = '/(?:^|\R)\s*\[[\w.\- ]+\]\s*(?:\R|$)/';

    // Two or more `key = value` or `key: value` lines. One alone is a sentence
    // with a colon in it; a run of them is a config block.
    // `.` and not `[^\R]`: \R is a line-break *escape* and PCRE rejects it
    // inside a character class. Without the /s modifier `.` already stops at a
    // newline, which is what a per-line match needs.
    private const SETTING_LINE = '/(?:^|\R)[ \t]*[\w.\-]+[ \t]*[:=][ \t]*\S.*/';

    // A companion clause, required alongside the opener. Without it every bare
    // 'delete' or 'create' in a PHP lookup array reads as a query: measured at
    // 28 false positives across this package's own sources, against 2 real
    // findings, before this was added.
    private const SQL_CLAUSES = '/\b(FROM|INTO|SET|VALUES|WHERE|TABLE|JOIN|INDEX|VIEW|DATABASE|COLUMN)\b/i';

    public function isStructured(string $text): bool
    {
        return $this->isMarkup($text) === true
            || $this->isSql($text) === true
            || $this->isSerialised($text) === true
            || $this->isConfig($text) === true
            || $this->isMarkdown($text) === true;
    }

    // HTML by its element list, plus the XML markers that list cannot cover.
    public function isMarkup(string $text): bool
    {
        return (new Markup())->containsHtmlElement($text) === true
            || preg_match($this->xmlPattern(), $text) === 1;
    }

    // An XML or SGML declaration, or any closing tag. The closing tag is the
    // signal HTML's element list cannot give for an arbitrary XML vocabulary.
    private function xmlPattern(): string
    {
        return '/<(?:' . implode('|', self::XML_MARKERS) . ')'
            . '|<\/[a-z_][\w.-]*(?::[\w.-]+)?\s*>/i';
    }

    // An opening keyword *and* a clause that belongs with it. `SELECT 1` and
    // other clause-free queries are missed by design: catching them would mean
    // trusting the opener alone, which reads a one-word 'select' as SQL.
    public function isSql(string $text): bool
    {
        $opener = strtoupper((string) strtok(ltrim($text), " \t\n("));

        return in_array($opener, self::SQL_OPENERS, true) === true
            && preg_match(self::SQL_CLAUSES, $text) === 1;
    }

    // JSON, and the YAML document marker. A bare `{` is not enough: an object
    // needs a quoted key, and an array of scalars is indistinguishable from a
    // PHP array literal written into a string.
    public function isSerialised(string $text): bool
    {
        $trimmed = ltrim($text);

        // A `---` on its own is a horizontal rule, an argument separator, or
        // padding. A YAML document needs a mapping under the marker.
        if (
            str_starts_with($trimmed, '---') === true
            && preg_match(self::SETTING_LINE, $trimmed) === 1
        ) {
            return true;
        }

        return preg_match('/^[\[{]/', $trimmed) === 1
            && preg_match("/\"[^\"]*\"\\s*:/", $trimmed) === 1;
    }

    // A `[section]` header needs a setting under it. Alone it is a placeholder,
    // a token in a message, or a bracketed aside — `'[placeholder]'` read as an
    // INI file was a measured false positive.
    public function isConfig(string $text): bool
    {
        $settings = preg_match_all(self::SETTING_LINE, $text);

        if (preg_match(self::INI_SECTION, $text) === 1) {
            return $settings >= 1;
        }

        return $settings >= 2;
    }

    public function isMarkdown(string $text): bool
    {
        return preg_match(self::MARKDOWN_LINE, $text) === 1;
    }
}
