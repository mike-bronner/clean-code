<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Code Style: Multiline Strings (HEREDOC)" standard.
 *
 * A string value that spans multiple source lines must be written with HEREDOC
 * (interpolating) or NOWDOC (literal) syntax rather than a quoted string that
 * runs across lines or a concatenation of quoted strings stitched together over
 * several lines. This matters most for inline SQL, where a multi-line HEREDOC
 * reads as the query it is.
 *
 * Two shapes are flagged:
 *
 * - **QuotedString** — a single quoted string literal whose source spans more
 *   than one physical line (a double- or single-quoted string with a real
 *   newline between its quotes). PHPCS splits such a string into consecutive
 *   string tokens at each newline; the violation is reported once, on the first
 *   fragment. This shape is auto-fixable: a double-quoted string becomes a
 *   HEREDOC (interpolation is preserved), a single-quoted string becomes a
 *   NOWDOC (kept literal), with the closing marker emitted at column 0 so the
 *   value is reproduced byte-for-byte.
 * - **Concatenation** — a run of quoted strings joined with `.` that spans
 *   multiple lines (the line-wrapped concatenation idiom, `"SELECT *"\n . " FROM
 *   t"`). Reported once, on the first string operand of the chain. Merging the
 *   pieces into a single HEREDOC is a semantic rewrite — interpolation, the
 *   whitespace between the pieces, and any non-string operands all have to be
 *   reasoned about — so this shape is detection-only and left to the developer.
 *
 * Never flagged: HEREDOC and NOWDOC bodies (they tokenize as `T_START_HEREDOC`
 * / `T_HEREDOC` / `T_END_HEREDOC`, never as string literals), single-line
 * quoted strings, single-line concatenation, and a single-line string whose
 * only newline is the escape sequence `\n` (that is one physical line).
 */
class MultilineStringsSniff implements Sniff
{
    /**
     * Token types PHPCS emits for a quoted string literal. A string that spans
     * source lines is split into several consecutive tokens of these types.
     */
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    /**
     * Closing marker used for the HEREDOC/NOWDOC the fixer emits. Placed at
     * column 0 so no indentation is stripped and the string value is preserved
     * exactly.
     */
    private const MARKER = 'TEXT';

    private const MESSAGE_STRING =
        'Multi-line strings must use HEREDOC/NOWDOC syntax instead of a quoted string spanning multiple lines';

    private const MESSAGE_CONCATENATION =
        'Multi-line strings must use HEREDOC/NOWDOC syntax instead of concatenating quoted strings across lines';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_CONSTANT_ENCAPSED_STRING,
            T_DOUBLE_QUOTED_STRING,
            T_STRING_CONCAT,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_STRING_CONCAT) {
            $this->processConcatenation($phpcsFile, $stackPtr);

            return;
        }

        $this->processStringLiteral($phpcsFile, $stackPtr);
    }

    /**
     * Flags a quoted string literal whose source spans multiple physical lines,
     * offering the HEREDOC/NOWDOC auto-fix when the conversion is feasible.
     */
    private function processStringLiteral(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Only the first fragment of a split string reports. A multi-line
        // string arrives as consecutive string tokens; a tail fragment is one
        // whose immediate predecessor is also a string token.
        if ($this->isStringLiteral($tokens, ($stackPtr - 1))) {
            return;
        }

        $fragments = $this->collectFragments($tokens, $stackPtr);

        $raw = '';

        foreach ($fragments as $fragment) {
            $raw .= $tokens[$fragment]['content'];
        }

        if (strpos($raw, "\n") === false && strpos($raw, "\r") === false) {
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

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($stackPtr, $replacement);

        foreach ($fragments as $fragment) {
            if ($fragment !== $stackPtr) {
                $phpcsFile->fixer->replaceToken($fragment, '');
            }
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Flags a multi-line concatenation whose operands are *all* quoted string
     * literals — a long string literal split across source lines with `.`,
     * which the standard wants written as one HEREDOC/NOWDOC. Detection-only,
     * reported once on the leftmost string of the chain.
     *
     * A concatenation that mixes in a variable, function call, or any other
     * non-string operand is a value-composing expression wrapped for line
     * length, not a split string literal, and is left alone.
     */
    private function processConcatenation(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // The operand immediately to the left of this '.' must be a string
        // literal for the chain to be a split string; bail cheaply otherwise.
        // (Checked before any statement walk so a '.' between non-string
        // operands costs O(1), not a backward scan — keeping the sniff linear
        // over long generated concatenations.)
        $left = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($left === false || $this->isStringLiteral($tokens, $left) === false) {
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

        if ($beforeChain !== false && $tokens[$beforeChain]['code'] === T_STRING_CONCAT) {
            return;
        }

        $spansLines = ($tokens[$stackPtr]['line'] !== $tokens[$left]['line']);
        $ptr = $stackPtr;

        // Walk the chain rightward: every '.' must be followed by a string
        // literal. A non-string operand means this is not a pure split string.
        while ($ptr !== false && $tokens[$ptr]['code'] === T_STRING_CONCAT) {
            $operand = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if ($operand === false || $this->isStringLiteral($tokens, $operand) === false) {
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

    /**
     * Collects the consecutive string tokens that make up one quoted string
     * literal, starting at its first fragment.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array<int, int>
     */
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

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isStringLiteral(array $tokens, int $ptr): bool
    {
        return isset($tokens[$ptr]) && in_array($tokens[$ptr]['code'], self::STRING_TOKENS, true);
    }

    /**
     * Builds the HEREDOC (double-quoted source) or NOWDOC (single-quoted
     * source) that reproduces the quoted string's value exactly, or null when
     * the conversion is not feasible (a body line would collide with the
     * closing marker). The marker sits at column 0, so PHP strips no
     * indentation and the value is preserved byte-for-byte.
     */
    private function buildDocString(File $phpcsFile, string $raw): ?string
    {
        $quote = $raw[0];
        $inner = substr($raw, 1, -1);

        if ($quote === "'") {
            $body = $this->nowdocBody($inner);
            $opener = "<<<'" . self::MARKER . "'";
        } else {
            $body = $this->heredocBody($inner);
            $opener = '<<<' . self::MARKER;
        }

        foreach (preg_split('/\r\n|\n|\r/', $body) as $line) {
            if (strpos(ltrim($line), self::MARKER) === 0) {
                return null;
            }
        }

        $eol = $phpcsFile->eolChar;

        return $opener . $eol . $body . $eol . self::MARKER;
    }

    /**
     * Rewrites the inner text of a double-quoted string as a HEREDOC body.
     * HEREDOC honours the same escape sequences as double quotes except `\"`,
     * which is not special there — so only `\"` is unescaped to `"`; every
     * other escape (and any interpolation) is preserved verbatim.
     */
    private function heredocBody(string $inner): string
    {
        $out = '';
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            if ($inner[$i] === '\\' && ($i + 1) < $length) {
                $next = $inner[$i + 1];
                $out .= ($next === '"') ? '"' : '\\' . $next;
                $i++;

                continue;
            }

            $out .= $inner[$i];
        }

        return $out;
    }

    /**
     * Rewrites the inner text of a single-quoted string as a NOWDOC body.
     * NOWDOC is fully literal, so the two single-quote escapes are resolved
     * (`\\` to `\`, `\'` to `'`) and every other character is kept as-is.
     */
    private function nowdocBody(string $inner): string
    {
        $out = '';
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            if ($inner[$i] === '\\' && ($i + 1) < $length) {
                $next = $inner[$i + 1];
                $out .= ($next === '\\' || $next === "'") ? $next : '\\' . $next;
                $i++;

                continue;
            }

            $out .= $inner[$i];
        }

        return $out;
    }
}
