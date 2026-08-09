<?php

/**
 * Compliant code for Generic.PHP.NoSilencedErrors — the sniff that replaces
 * PHPMD's CleanCode/ErrorControlOperator rule.
 *
 * Nothing here suppresses an error, so the sniff must stay silent on every
 * line. The file is deliberately full of `@` characters that are *not* the
 * error-control operator, because that is the only way the fixture asserts
 * anything: a compliant file with no `@` in it at all would pass however
 * broken the sniff became.
 *
 * @author  Nobody <nobody@example.com>
 * @package MikeBronner\CleanCode\Tests
 */

declare(strict_types=1);

// An email address in a comment is prose: nobody@example.com

#[Attribute]
class SilenceFreeFixture
{
    /**
     * @param array<string, string> $data
     * @throws RuntimeException
     */
    #[Deprecated]
    public function readKey(array $data, string $key): string
    {
        // ?? is the honest replacement for @$data[$key] — it answers the
        // "missing key" question without hiding anything else.
        return $data[$key] ?? '';
    }

    public function readFile(string $path): string
    {
        // A guard plus a thrown exception, rather than @file_get_contents().
        if (is_readable($path) === false) {
            throw new RuntimeException("cannot read {$path}");
        }

        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    public function isEmailish(string $candidate): bool
    {
        // The '@' here is string data, not an operator, in every quoting style.
        $single = 'user@example.com';
        $double = "user@{$candidate}";
        $heredoc = <<<TEXT
            Contact user@example.com for access.
            TEXT;
        $nowdoc = <<<'TEXT'
            Contact user@example.com for access.
            TEXT;

        return str_contains($candidate, '@')
            && $single !== $double
            && $heredoc !== $nowdoc;
    }
}
