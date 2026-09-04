<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class RequireStringInterpolationSniff implements Sniff
{
    private const MEMBER_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const CHAIN_HEADS = [
        T_STRING,
        T_VARIABLE,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_CURLY_BRACKET,
    ];

    private const STRING_LITERALS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    public function register(): array
    {
        return [T_STRING_CONCAT];
    }

    public function process(File $phpcsFile, $stackPtr): void
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
        if (
            $beforeChain !== false
            && $tokens[$beforeChain]['code'] === T_STRING_CONCAT
        ) {
            return;
        }

        $operands = $this->collectChainOperands($phpcsFile, $chainStart);

        if ($this->hasNonInterpolatableOperand($tokens, $operands) === true) {
            return;
        }

        $literals = $this->countStringLiterals($tokens, $operands);
        $variables = count($operands) - $literals;

        if (
            $literals === 0
            || $variables === 0
        ) {
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

        $phpcsFile->fixer
            ->beginChangeset();
        $phpcsFile->fixer
            ->replaceToken($first, $replacement);

        for ($i = ($first + 1); $i <= $last; $i++) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function collectChainOperands(File $phpcsFile, int $chainStart): array
    {
        $tokens = $phpcsFile->getTokens();
        $operands = [];
        $cursor = $chainStart;

        while (true) {
            $end = $this->operandEnd($phpcsFile, $cursor);
            $operands[] = ['start' => $cursor, 'end' => $end];

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if (
                $next === false
                || $tokens[$next]['code'] !== T_STRING_CONCAT
            ) {
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

    private function hasNonInterpolatableOperand(array $tokens, array $operands): bool
    {
        foreach ($operands as $operand) {
            $pointer = $this->operandPointer($tokens, $operand);
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::STRING_LITERALS, true) === true) {
                if ((new StringLiteral())->isComplete($tokens[$pointer]['content']) === false) {
                    return true;
                }

                continue;
            }

            if ($code !== T_VARIABLE) {
                return true;
            }
        }

        return false;
    }

    private function operandCode(array $tokens, array $operand)
    {
        return $tokens[$this->operandPointer($tokens, $operand)]['code'];
    }

    private function operandPointer(array $tokens, array $operand): int
    {
        if ($tokens[$operand['start']]['code'] !== T_OPEN_PARENTHESIS) {
            return $operand['start'];
        }

        $closer = $tokens[$operand['start']]['parenthesis_closer'] ?? $operand['end'];

        $head = $this->skipGrouping($tokens, $operand['start'], $closer, T_OPEN_PARENTHESIS, 1);
        $tail = $this->skipGrouping($tokens, $closer, $operand['start'], T_CLOSE_PARENTHESIS, -1);

        return $head === $tail ? $head : $operand['start'];
    }

    private function skipGrouping(array $tokens, int $from, int $limit, int|string $grouping, int $step): int
    {
        $pointer = $from;

        while ($pointer !== $limit) {
            $code = $tokens[$pointer]['code'];

            if (
                $code !== $grouping
                && isset(Tokens::$emptyTokens[$code]) === false
            ) {
                break;
            }

            $pointer += $step;
        }

        return $pointer;
    }

    private function countStringLiterals(array $tokens, array $operands): int
    {
        $count = 0;

        foreach ($operands as $operand) {
            if (in_array($this->operandCode($tokens, $operand), self::STRING_LITERALS, true) === true) {
                $count++;
            }
        }

        return $count;
    }

    private function simpleInterpolation(array $tokens, array $operands): ?string
    {
        if (count($operands) !== 2) {
            return null;
        }

        [$first, $second] = $operands;

        $literal = $this->singleTokenOfCode($tokens, $first, $second, T_CONSTANT_ENCAPSED_STRING);
        $variable = $this->singleTokenOfCode($tokens, $first, $second, T_VARIABLE);

        if (
            $literal === null
            || $variable === null
        ) {
            return null;
        }

        $content = $tokens[$literal]['content'];

        // A binary-string prefix makes this rewrite unfixable, because the
        // result always interpolates and no spelling of the prefix survives
        // that. Dropping the prefix would rest on it being a no-op, and
        // carrying it over emits `B"…{$var}…"`, which PHP_CodeSniffer's own
        // tokenizer cannot read: it types the `B"` opener T_NONE and folds the
        // rest of the statement — and the source after it — into one bogus
        // string token, so every later sniff reads live code as string body.
        // (The prefix is safe on the *non*-interpolating outputs its sibling
        // fixers build, which is why only this one refuses.) The violation is
        // still reported, as detection-only.
        if ((new StringLiteral())->prefix($content) !== '') {
            return null;
        }

        $inner = $this->literalInnerAsDoubleQuoted($content);

        if ($inner === null) {
            return null;
        }

        $interpolated = '{' . $tokens[$variable]['content'] . '}';

        return $literal < $variable
            ? "\"" . $inner . $interpolated . "\""
            : "\"" . $interpolated . $inner . "\"";
    }

    private function singleTokenOfCode(array $tokens, array $first, array $second, int|string $code): ?int
    {
        foreach ([$first, $second] as $operand) {
            if (
                $operand['start'] === $operand['end']
                && $tokens[$operand['start']]['code'] === $code
            ) {
                return $operand['start'];
            }
        }

        return null;
    }

    private function literalInnerAsDoubleQuoted(string $content): ?string
    {
        $delimiter = (new StringLiteral())->delimiter($content);
        $inner = (new StringLiteral())->inner($content);

        if ($delimiter === "\"") {
            return $this->escapeTrailingDollar($inner);
        }

        if (strpos($inner, '\\') !== false) {
            return null;
        }

        return str_replace(['\\', "\"", '$'], ['\\\\', '\\"', '\\$'], $inner);
    }

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

    private function operandEnd(File $phpcsFile, int $start): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $start;

        // An operand that *begins* with a grouping parenthesis — `($b) . 'y'` —
        // spans to that parenthesis's closer before any call/index chain is
        // walked. Without this the operand would end on the `(` itself and the
        // chain would be misread.
        if (
            $tokens[$end]['code'] === T_OPEN_PARENTHESIS
            && isset($tokens[$end]['parenthesis_closer']) === true
        ) {
            $end = $tokens[$end]['parenthesis_closer'];
        }

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

            if (
                $code === T_OPEN_SQUARE_BRACKET
                && isset($tokens[$next]['bracket_closer']) === true
            ) {
                $end = $tokens[$next]['bracket_closer'];

                continue;
            }

            if (
                $code === T_OPEN_PARENTHESIS
                && isset($tokens[$next]['parenthesis_closer']) === true
            ) {
                $end = $tokens[$next]['parenthesis_closer'];

                continue;
            }

            break;
        }

        return $end;
    }

    // The opener of a bracket or parenthesis pair the walk stands on the closer
    // of. A closer whose opener PHPCS did not record answers null, the same as a
    // token that is not a closer at all.
    private function groupOpenerAt(array $tokens, int $ptr, int|string $code): ?int
    {
        if ($code === T_CLOSE_SQUARE_BRACKET) {
            return $tokens[$ptr]['bracket_opener'] ?? null;
        }

        if ($code === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? null;
        }

        return null;
    }

    private function operandStart(File $phpcsFile, int $end): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $end;

        while (true) {
            $code = $tokens[$start]['code'];

            $opener = $this->groupOpenerAt($tokens, $start, $code);

            if ($opener === null) {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

                if (
                    $prev === false
                    || in_array($tokens[$prev]['code'], self::MEMBER_OPERATORS, true) === false
                ) {
                    break;
                }

                $base = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($prev - 1), null, true);

                if ($base === false) {
                    break;
                }

                $start = $base;

                continue;
            }

            $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($opener - 1), null, true);

            // A bracket or parenthesis pair only extends the operand further
            // left when something callable or subscriptable sits immediately
            // before it — `foo(…)`, `$obj->run(…)`, `$arr[…]`. A *bare*
            // grouping parenthesis has no such head, so the operand starts at
            // the opener itself; stepping past it would swallow the assignment
            // operator before it and leave the whole chain unrecognisable.
            if (
                $prev === false
                || in_array($tokens[$prev]['code'], self::CHAIN_HEADS, true) === false
            ) {
                $start = $opener;

                break;
            }

            $start = $prev;
        }

        return $start;
    }
}
