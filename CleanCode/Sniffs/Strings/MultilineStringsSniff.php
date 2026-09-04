<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use MikeBronner\CleanCode\Support\StructuredText;
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

    private const SINGLE_QUOTE_RESOLVED_ESCAPES = '\\\'';

    private const MESSAGE_STRING
        = 'Multi-line strings must use HEREDOC syntax instead of a quoted string spanning multiple lines';

    private const MESSAGE_CONCATENATION
        = 'This text is %s lines long, more than the %s allowed; a block that long reads'
        . ' as the block it is in a HEREDOC';

    // Left untyped, and public, because PHP_CodeSniffer assigns a ruleset
    // <property> as a string: a native int here throws TypeError in a consumer's
    // run. See CONTRIBUTING.md.
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint -- ruleset-assigned, see CONTRIBUTING.md
    public $maximumLines = 3;

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

            while ($this->isStringLiteral($tokens, ($operand + 1))) {
                $operand++;
            }

            $ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operand + 1), null, true);
        }

        $this->reportChain($phpcsFile, $firstString);
    }

    // Lines of *text*, never lines of source.
    //
    // A sentence wrapped across four source lines is one line of text, and a
    // HEREDOC cannot express it: the body would have to sit on one line, which
    // breaks the 120-character limit, or wrap, which puts real newlines into the
    // value. Measuring the source made this rule demand something unreachable
    // and report what could not be fixed. Measuring the text means every report
    // names a string a HEREDOC can hold, with the newlines it already has.
    //
    // Source layout is somebody else's concern: the 100-character limit governs
    // how long a line may be, and the leading-operator rule governs where it
    // breaks. Between them they produce the wrapping this rule used to flag.
    private function reportChain(File $phpcsFile, int $firstString): void
    {
        $tokens = $phpcsFile->getTokens();
        $value = (new StringLiteral())->concatenated($tokens, $firstString);

        // Embedded languages belong to CleanCode.Strings.RequireHeredocForStructuredText,
        // which owns them at any length. Reporting one here as well would put two
        // sniffs on one concern and double every finding.
        if ((new StructuredText())->isStructured($value) === true) {
            return;
        }

        $lines = preg_match_all('/\R/', $value) + 1;

        if ($lines <= $this->maximumLines) {
            return;
        }

        $phpcsFile->addError(
            self::MESSAGE_CONCATENATION,
            $firstString,
            'Concatenation',
            [$lines, $this->maximumLines]
        );
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

    // Always a HEREDOC, whatever the source was quoted with. The two forms
    // differ only in the quotes around the opening identifier, and carrying both
    // means a reader checks the delimiter before trusting what the body says —
    // see CleanCode.Strings.DisallowNowdoc, which holds the same line.
    //
    // What differs is the body. A single-quoted source resolves only `\\` and
    // `\'`, so those come back to the characters they stand for and the whole
    // thing is then escaped for a HEREDOC, where a backslash and a `$` each mean
    // something. A double-quoted source already uses the escapes a HEREDOC
    // resolves; only its quote escape is unnecessary there.
    private function docStringParts(string $raw, string $prefix, string $inner): array
    {
        $literal = new StringLiteral();
        $opener = "{$prefix}<<<" . self::MARKER;

        if ($literal->delimiter($raw) === "'") {
            $resolved = $this->docStringBody($inner, self::SINGLE_QUOTE_RESOLVED_ESCAPES);

            return [$literal->asHeredocBody($resolved), $opener];
        }

        return [$this->docStringBody($inner, self::HEREDOC_RESOLVED_ESCAPES), $opener];
    }

    private function buildDocString(File $phpcsFile, string $raw): ?string
    {
        $prefix = (new StringLiteral())->prefix($raw);
        $inner = (new StringLiteral())->inner($raw);

        [$body, $opener] = $this->docStringParts($raw, $prefix, $inner);

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
                $out .= (strpos($resolved, $next) !== false) ? $next : "\\{$next}";
                $i++;

                continue;
            }

            $out .= $inner[$i];
        }

        return $out;
    }
}
