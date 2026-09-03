<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class MultilineStringsSniff implements Sniff
{
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    private const MARKER = 'TEXT';

    private const HEREDOC_RESOLVED_ESCAPES = "\"";

    private const NOWDOC_RESOLVED_ESCAPES = '\\\'';

    private const MESSAGE_STRING =
        'Multi-line strings must use HEREDOC/NOWDOC syntax instead of a quoted string spanning multiple lines';

    private const MESSAGE_CONCATENATION =
        'Multi-line strings must use HEREDOC/NOWDOC syntax instead of concatenating quoted strings across lines';

    public function register(): array
    {
        return [
            T_CONSTANT_ENCAPSED_STRING,
            T_DOUBLE_QUOTED_STRING,
            T_STRING_CONCAT,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_STRING_CONCAT) {
            $this->processConcatenation($phpcsFile, $stackPtr);

            return;
        }

        $this->processStringLiteral($phpcsFile, $stackPtr);
    }

    private function processStringLiteral(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($this->opensLiteral($tokens, $stackPtr) === false) {
            return;
        }

        $fragments = $this->collectFragments($tokens, $stackPtr);

        $raw = '';

        foreach ($fragments as $fragment) {
            $raw .= $tokens[$fragment]['content'];
        }

        if (
            strpos($raw, "\n") === false
            && strpos($raw, "\r") === false
        ) {
            return;
        }

        $replacement = $this->buildDocString($phpcsFile, $raw);

        if ($replacement === null) {
            $phpcsFile->addError(self::MESSAGE_STRING, $stackPtr, 'QuotedString');

            return;
        }

        $fix = $phpcsFile->addFixableError(self::MESSAGE_STRING, $stackPtr, 'QuotedString');

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer
            ->beginChangeset();
        $phpcsFile->fixer
            ->replaceToken($stackPtr, $replacement);

        foreach ($fragments as $fragment) {
            if ($fragment !== $stackPtr) {
                $phpcsFile->fixer
                    ->replaceToken($fragment, '');
            }
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function processConcatenation(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // The operand immediately to the left of this '.' must be a string
        // literal for the chain to be a split string; bail cheaply otherwise.
        // (Checked before any statement walk so a '.' between non-string
        // operands costs O(1), not a backward scan — keeping the sniff linear
        // over long generated concatenations.)
        $left = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $left === false
            || $this->isStringLiteral($tokens, $left) === false
        ) {
            return;
        }

        // Walk back to the leftmost string fragment of this operand — where the
        // violation is reported when this '.' opens the chain.
        $firstString = $left;

        while ($this->isStringLiteral($tokens, ($firstString - 1))) {
            $firstString--;
        }

        // Dedup per concat sub-expression, not per statement. A chain holds
        // several '.' operators but must report once, on its first '.'. This is
        // the first '.' exactly when nothing to the left of its leftmost string
        // fragment is another '.'. Keying on findStartOfStatement() instead
        // would collapse two independent chains joined by a non-boundary
        // operator (ternary '?'/':' , '&&', '===', arithmetic, …) into one
        // scope and silently drop every chain after the first.
        $beforeChain = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($firstString - 1), null, true);

        if (
            $beforeChain !== false
            && $tokens[$beforeChain]['code'] === T_STRING_CONCAT
        ) {
            return;
        }

        $spansLines = ($tokens[$stackPtr]['line'] !== $tokens[$left]['line']);
        $ptr = $stackPtr;

        // Walk the chain rightward: every '.' must be followed by a string
        // literal. A non-string operand means this is not a pure split string.
        while (
            $ptr !== false
            && $tokens[$ptr]['code'] === T_STRING_CONCAT
        ) {
            $operand = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if (
                $operand === false
                || $this->isStringLiteral($tokens, $operand) === false
            ) {
                return;
            }

            if ($tokens[$operand]['line'] !== $tokens[$ptr]['line']) {
                $spansLines = true;
            }

            while ($this->isStringLiteral($tokens, ($operand + 1))) {
                $operand++;
            }

            $ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operand + 1), null, true);
        }

        if ($spansLines === false) {
            return;
        }

        $phpcsFile->addError(self::MESSAGE_CONCATENATION, $firstString, 'Concatenation');
    }

    private function collectFragments(array $tokens, int $start): array
    {
        $fragments = [$start];
        $ptr = $start + 1;

        while ($this->isStringLiteral($tokens, $ptr)) {
            $fragments[] = $ptr;
            $ptr++;
        }

        return $fragments;
    }

    private function opensLiteral(array $tokens, int $ptr): bool
    {
        return $this->isStringLiteral($tokens, ($ptr - 1)) === false
            && ($tokens[$ptr - 1]['code'] ?? null) !== T_ENCAPSED_AND_WHITESPACE;
    }

    private function isStringLiteral(array $tokens, int $ptr): bool
    {
        return isset($tokens[$ptr]) && in_array($tokens[$ptr]['code'], self::STRING_TOKENS, true);
    }

    private function buildDocString(File $phpcsFile, string $raw): ?string
    {
        $prefix = StringLiteral::prefix($raw);
        $inner = StringLiteral::inner($raw);

        if (StringLiteral::delimiter($raw) === "'") {
            $body = $this->docStringBody($inner, self::NOWDOC_RESOLVED_ESCAPES);
            $opener = $prefix . "<<<'" . self::MARKER . "'";
        } else {
            $body = $this->docStringBody($inner, self::HEREDOC_RESOLVED_ESCAPES);
            $opener = $prefix . '<<<' . self::MARKER;
        }

        $lines = preg_split('/\r\n|\n|\r/', $body);

        // A failed split is false and the foreach then throws a TypeError. The
        // marker-collision check below is the only thing standing between this
        // fixer and a heredoc whose own body closes it, so a body whose lines
        // could not be read is a body this fixer must not rewrite: null is the
        // same "leave the file alone" answer a real collision earns. `/\r\n|\n|\r/`
        // is a literal alternation with no quantifier and no `/u` modifier, so
        // preg_split() cannot fail.
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            if ($this->collidesWithMarker($line)) {
                return null;
            }
        }

        $eol = $phpcsFile->eolChar;

        return $opener . $eol . $body . $eol . self::MARKER;
    }

    private function collidesWithMarker(string $line): bool
    {
        $trimmed = ltrim($line);

        if (strpos($trimmed, self::MARKER) !== 0) {
            return false;
        }

        $after = $trimmed[strlen(self::MARKER)] ?? '';

        // A following label char (letter, digit, underscore) means PHP reads
        // the line as a longer identifier, not the closing marker.
        return $after === '' || preg_match('/[A-Za-z0-9_]/', $after) !== 1;
    }

    private function docStringBody(string $inner, string $resolved): string
    {
        $out = '';
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            if (
                $inner[$i] === '\\'
                && ($i + 1) < $length
            ) {
                $next = $inner[$i + 1];
                $out .= (strpos($resolved, $next) !== false) ? $next : '\\' . $next;
                $i++;

                continue;
            }

            $out .= $inner[$i];
        }

        return $out;
    }
}
