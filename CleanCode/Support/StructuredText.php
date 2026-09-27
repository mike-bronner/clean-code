<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

class StructuredText
{
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

    private const MARKDOWN_LINE = '/(?:^|\R)\s*(?:#{1,6}\s|[-*+]\s+\S|\|\s|>\s|```)/';

    private const XML_MARKERS = [
        '\?xml\b',
        '!DOCTYPE\b',
        '!\[CDATA\[',
    ];

    private const INI_SECTION = '/(?:^|\R)\s*\[[\w.\- ]+\]\s*(?:\R|$)/';

    private const SETTING_LINE = '/(?:^|\R)[ \t]*[\w.\-]+[ \t]*[:=][ \t]*\S.*/';

    private const SQL_CLAUSES = '/\b(FROM|INTO|SET|VALUES|WHERE|TABLE|JOIN|INDEX|VIEW|DATABASE|COLUMN)\b/i';

    public function isStructured(string $text): bool
    {
        return $this->isMarkup($text) === true
            || $this->isSql($text) === true
            || $this->isSerialised($text) === true
            || $this->isConfig($text) === true
            || $this->isMarkdown($text) === true;
    }

    public function isMarkup(string $text): bool
    {
        return (new Markup())->containsHtmlElement($text) === true
            || preg_match($this->xmlPattern(), $text) === 1;
    }

    private function xmlPattern(): string
    {
        return '/<(?:' . implode('|', self::XML_MARKERS) . ')'
            . '|<\/[a-z_][\w.-]*(?::[\w.-]+)?\s*>/i';
    }

    public function isSql(string $text): bool
    {
        $opener = strtoupper((string) strtok(ltrim($text), " \t\n("));

        return in_array($opener, self::SQL_OPENERS, true) === true
            && preg_match(self::SQL_CLAUSES, $text) === 1;
    }

    public function isSerialised(string $text): bool
    {
        $trimmed = ltrim($text);

        if (
            str_starts_with($trimmed, '---') === true
            && preg_match(self::SETTING_LINE, $trimmed) === 1
        ) {
            return true;
        }

        return preg_match('/^[\[{]/', $trimmed) === 1
            && preg_match("/\"[^\"]*\"\\s*:/", $trimmed) === 1;
    }

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
