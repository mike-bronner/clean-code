<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the Strings standard's "use interpolation in favor of
 * concatenation" rule (#25).
 *
 * The sniff flags a `.` concatenation that joins a string literal with a
 * variable — text that reads more cleanly as a single interpolated string.
 * Only concatenations whose every operand can appear in an interpolated string
 * (string literals and variable expressions) are considered; a concatenation
 * that mixes in a function call, constant, or arithmetic (`$x . foo()`,
 * `__DIR__ . '/x'`) cannot become one interpolated string and is left alone.
 *
 * Fixable — the direct, two-operand case: one plain string literal plus one
 * plain `$variable` (`'Hello ' . $name` → `"Hello {$name}"`). The fixer merges
 * them into a single double-quoted interpolated string, brace-wrapping the
 * variable so it never runs into adjacent literal text, and escaping a literal
 * that ends in a bare `$` so `"Total $" . $x` cannot fuse into the deprecated
 * `${...}` dollar-curly syntax.
 *
 * Detection-only — everything else that is still interpolatable but not a
 * mechanical rewrite: multi-expression chains (`'a' . $b . 'c'`), complex
 * variable operands (`'x' . $obj->prop`, `'x' . $arr['k']`), and any operand
 * that is itself an already-interpolated double-quoted string (`"Hi {$a}" .
 * $b`) — which the standard wants written with `{...}` but whose safe rewrite
 * is a judgement call left to the developer.
 */
class RequireStringInterpolationSniff implements Sniff
{
    /**
     * Member-access operators that extend a variable operand
     * (`$obj->prop`, `$obj::CONST`).
     */
    private const MEMBER_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    /**
     * Operand token codes that count as a string literal. A double-quoted
     * string that already interpolates (`"Hi {$a}"`) is a literal too — it can
     * live inside an interpolated result — so a chain built from one is flagged
     * for consistency, though only the plain single-token literal is auto-fixed.
     */
    private const STRING_LITERALS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_STRING_CONCAT];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $leftEnd = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($leftEnd === false) {
            return;
        }

        $chainStart = $this->operandStart($phpcsFile, $leftEnd);
        $beforeChain = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($chainStart - 1), null, true);

        // Process a concatenation chain only at its first `.`; a `.` whose
        // left operand is itself preceded by another `.` is a continuation.
        if ($beforeChain !== false && $tokens[$beforeChain]['code'] === T_STRING_CONCAT) {
            return;
        }

        $operands = $this->collectChainOperands($phpcsFile, $chainStart);

        if ($this->hasNonInterpolatableOperand($tokens, $operands) === true) {
            return;
        }

        $literals = $this->countStringLiterals($tokens, $operands);
        $variables = count($operands) - $literals;

        if ($literals === 0 || $variables === 0) {
            return;
        }

        $replacement = $this->simpleInterpolation($tokens, $operands);

        if ($replacement === null) {
            $phpcsFile->addError(
                'Use string interpolation instead of concatenation; this expression is too complex'
                    . ' to auto-fix — convert it to an interpolated string with {...} manually',
                $stackPtr,
                'ComplexConcatenation'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Use string interpolation instead of concatenating a string literal with a variable',
            $stackPtr,
            'Concatenation'
        );

        if ($fix === false) {
            return;
        }

        $first = $operands[0]['start'];
        $last = $operands[count($operands) - 1]['end'];

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($first, $replacement);

        for ($i = ($first + 1); $i <= $last; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Collects every operand of the concatenation chain that begins at
     * $chainStart, as a list of ['start' => int, 'end' => int] token spans.
     *
     * @return array<int, array{start: int, end: int}>
     */
    private function collectChainOperands(File $phpcsFile, int $chainStart): array
    {
        $tokens = $phpcsFile->getTokens();
        $operands = [];
        $cursor = $chainStart;

        while (true) {
            $end = $this->operandEnd($phpcsFile, $cursor);
            $operands[] = ['start' => $cursor, 'end' => $end];

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false || $tokens[$next]['code'] !== T_STRING_CONCAT) {
                break;
            }

            $following = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if ($following === false) {
                break;
            }

            $cursor = $following;
        }

        return $operands;
    }

    /**
     * Whether any operand is neither a string literal nor a variable
     * expression — a function call, constant, or magic constant that cannot
     * live inside an interpolated string.
     *
     * @param array<int, array{start: int, end: int}> $operands
     * @param array<int, array<string, mixed>> $tokens
     */
    private function hasNonInterpolatableOperand(array $tokens, array $operands): bool
    {
        foreach ($operands as $operand) {
            $code = $tokens[$operand['start']]['code'];

            if (in_array($code, self::STRING_LITERALS, true) === false && $code !== T_VARIABLE) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many operands are string literals (plain or already-interpolated
     * double-quoted). The remaining operands are variables, so callers derive
     * the variable count as `count($operands) - this`.
     *
     * @param array<int, array{start: int, end: int}> $operands
     * @param array<int, array<string, mixed>> $tokens
     */
    private function countStringLiterals(array $tokens, array $operands): int
    {
        $count = 0;

        foreach ($operands as $operand) {
            if (in_array($tokens[$operand['start']]['code'], self::STRING_LITERALS, true) === true) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Builds the replacement interpolated string for the fixable case — a
     * two-operand chain of exactly one string literal and one plain variable —
     * or null when the chain is anything else, when the variable is a complex
     * expression, or when the literal cannot be safely re-encoded.
     *
     * @param array<int, array{start: int, end: int}> $operands
     * @param array<int, array<string, mixed>> $tokens
     */
    private function simpleInterpolation(array $tokens, array $operands): ?string
    {
        if (count($operands) !== 2) {
            return null;
        }

        [$first, $second] = $operands;

        $literal = $this->singleTokenOfCode($tokens, $first, $second, T_CONSTANT_ENCAPSED_STRING);
        $variable = $this->singleTokenOfCode($tokens, $first, $second, T_VARIABLE);

        if ($literal === null || $variable === null) {
            return null;
        }

        $inner = $this->literalInnerAsDoubleQuoted($tokens[$literal]['content']);

        if ($inner === null) {
            return null;
        }

        $interpolated = '{' . $tokens[$variable]['content'] . '}';

        return $literal < $variable
            ? '"' . $inner . $interpolated . '"'
            : '"' . $interpolated . $inner . '"';
    }

    /**
     * Returns the pointer of whichever operand is a single token of $code and
     * whose sibling is the other kind, or null when neither operand qualifies
     * (e.g. a complex `$obj->prop` variable operand spanning many tokens).
     *
     * @param array{start: int, end: int} $first
     * @param array{start: int, end: int} $second
     * @param array<int, array<string, mixed>> $tokens
     * @param int|string $code
     */
    private function singleTokenOfCode(array $tokens, array $first, array $second, $code): ?int
    {
        foreach ([$first, $second] as $operand) {
            if ($operand['start'] === $operand['end'] && $tokens[$operand['start']]['code'] === $code) {
                return $operand['start'];
            }
        }

        return null;
    }

    /**
     * Re-encodes a string-literal token's inner text (its content minus the
     * surrounding quotes) so it is safe to embed in a double-quoted string,
     * or null when the literal is single-quoted and carries backslash escapes
     * whose meaning conversion could change.
     */
    private function literalInnerAsDoubleQuoted(string $content): ?string
    {
        $delimiter = $content[0];
        $inner = substr($content, 1, -1);

        if ($delimiter === '"') {
            return $this->escapeTrailingDollar($inner);
        }

        if (strpos($inner, '\\') !== false) {
            return null;
        }

        return str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $inner);
    }

    /**
     * Escapes a trailing bare `$` in already double-quoted inner text. When the
     * literal is the left operand its content abuts the injected `{$var}`; an
     * unescaped trailing `$` would then read as `${...}` (deprecated dollar-curly
     * variable-variable syntax) and change runtime behaviour. A `$` already
     * escaped (`\$`, an odd run of preceding backslashes) is left untouched.
     */
    private function escapeTrailingDollar(string $inner): string
    {
        if (substr($inner, -1) !== '$') {
            return $inner;
        }

        $beforeDollar = substr($inner, 0, -1);
        $backslashes = strlen($beforeDollar) - strlen(rtrim($beforeDollar, '\\'));

        if ($backslashes % 2 === 1) {
            return $inner;
        }

        return $beforeDollar . '\\$';
    }

    /**
     * Walks forward from an operand's first token to its last, consuming
     * member accesses (`->`, `?->`, `::`), array indexes, and call
     * parentheses so `$obj->method()['x']` counts as a single operand.
     */
    private function operandEnd(File $phpcsFile, int $start): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $start;

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false) {
                break;
            }

            $code = $tokens[$next]['code'];

            if (in_array($code, self::MEMBER_OPERATORS, true) === true) {
                $member = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

                if ($member === false) {
                    break;
                }

                $end = $member;

                continue;
            }

            if ($code === T_OPEN_SQUARE_BRACKET && isset($tokens[$next]['bracket_closer']) === true) {
                $end = $tokens[$next]['bracket_closer'];

                continue;
            }

            if ($code === T_OPEN_PARENTHESIS && isset($tokens[$next]['parenthesis_closer']) === true) {
                $end = $tokens[$next]['parenthesis_closer'];

                continue;
            }

            break;
        }

        return $end;
    }

    /**
     * Walks backward from an operand's last token to its first, mirroring
     * operandEnd — jumping bracket/parenthesis pairs and member accesses so
     * the whole variable expression is treated as one operand.
     */
    private function operandStart(File $phpcsFile, int $end): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $end;

        while (true) {
            $code = $tokens[$start]['code'];

            if ($code === T_CLOSE_SQUARE_BRACKET && isset($tokens[$start]['bracket_opener']) === true) {
                $start = $tokens[$start]['bracket_opener'];
            } elseif ($code === T_CLOSE_PARENTHESIS && isset($tokens[$start]['parenthesis_opener']) === true) {
                $start = $tokens[$start]['parenthesis_opener'];
            } else {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

                if ($prev !== false && in_array($tokens[$prev]['code'], self::MEMBER_OPERATORS, true) === true) {
                    $base = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($prev - 1), null, true);

                    if ($base === false) {
                        break;
                    }

                    $start = $base;

                    continue;
                }

                break;
            }

            $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

            if ($prev === false) {
                break;
            }

            $start = $prev;
        }

        return $start;
    }
}
