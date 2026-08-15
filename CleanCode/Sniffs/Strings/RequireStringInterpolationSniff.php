<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
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
 * variable operands (`'x' . $obj->prop`, `'x' . $arr['k']`), operands wrapped
 * in grouping parentheses (`($b) . 'y'`), any operand that is itself an
 * already-interpolated double-quoted string (`"Hi {$a}" . $b`) — which the
 * standard wants written with `{...}` but whose safe rewrite is a judgement
 * call left to the developer — and a literal carrying a binary-string prefix
 * (`B'Total: ' . $sum`), whose interpolated form this tokenizer cannot read.
 *
 * Silent — a literal operand whose source spans several physical lines. It
 * tokenizes one token per line, so no single token holds the literal and
 * rewriting one of them would leave the string unterminated; that shape belongs
 * to CleanCode.Strings.MultilineStrings, which converts it to a HEREDOC.
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
     * Token codes that, sitting immediately before a `(` or `[`, make that
     * pair part of the operand to its left — a call or an index rather than a
     * bare grouping. `$arr['k']`, `foo()`, `$obj->run()`, `$fn()()`,
     * `${'x'}['k']` all end in one of these; `= ($b)` ends in none of them.
     */
    private const CHAIN_HEADS = [
        T_STRING,
        T_VARIABLE,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_CURLY_BRACKET,
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
     * A literal that is only one physical-line fragment of a multi-line string
     * counts as non-interpolatable too. Such a fragment is a string token like
     * any other, so nothing in its token code says the rest of the literal
     * lives in its neighbours; replacing it alone would cut a string in half.
     *
     * @param array<int, array{start: int, end: int}> $operands
     * @param array<int, array<string, mixed>> $tokens
     */
    private function hasNonInterpolatableOperand(array $tokens, array $operands): bool
    {
        foreach ($operands as $operand) {
            $pointer = $this->operandPointer($tokens, $operand);
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::STRING_LITERALS, true) === true) {
                if (StringLiteral::isComplete($tokens[$pointer]['content']) === false) {
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

    /**
     * The code of the token that decides an operand's kind.
     *
     * Normally that is simply the operand's first token. The exception is an
     * operand that *opens* with grouping parentheses, which are transparent to
     * the standard: `($b) . 'y'` says exactly what `$b . 'y'` says, and reads
     * as `"{$b}y"` either way.
     *
     * The parentheses are unwrapped **only when they contain a single token**.
     * That restriction is the whole point: `($count + 1)` also opens with a
     * `T_VARIABLE`, but `"total: {$count + 1}"` is not valid PHP, so unwrapping
     * on the first token alone would report a concatenation that has no
     * interpolated form. A compound parenthesized expression therefore keeps
     * its `T_OPEN_PARENTHESIS` code and is classified non-interpolatable, which
     * is what keeps the sniff silent on it.
     *
     * What is unwrapped is the *parenthesized span*, not the whole operand. A
     * member, index, or call chain can hang off the closing parenthesis
     * (`($obj)->prop`, `($arr)['key']`, `($service)->run()`), and such a chain
     * runs on past it — so reading to the operand's own end would compare the
     * wrapped token against the chain's last token, never match, and drop the
     * operand to non-interpolatable. That left the sniff silent on shapes whose
     * unparenthesized twins it reports.
     *
     * Unwrapping never makes an operand *fixable* — singleTokenOfCode() still
     * requires the operand's own span to be one token, and a parenthesized one
     * never is — so these land on ComplexConcatenation.
     *
     * @param array{start: int, end: int} $operand
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return int|string
     */
    private function operandCode(array $tokens, array $operand)
    {
        return $tokens[$this->operandPointer($tokens, $operand)]['code'];
    }

    /**
     * The token that decides an operand's kind — the operand's own first token,
     * or the single token a grouping parenthesis wraps. Callers that need the
     * token's *content* as well as its code go through this rather than
     * operandCode(), so both read the same token.
     *
     * The two walks are bounded by the opening parenthesis's own closer rather
     * than by the operand's end, so a chain hanging off that closer is stepped
     * over instead of being read as part of what the parentheses wrap. An
     * unmatched parenthesis has no closer to bound them with, so the operand's
     * end stands in and the operand stays classified by its `(`.
     *
     * @param array{start: int, end: int} $operand
     * @param array<int, array<string, mixed>> $tokens
     */
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

    /**
     * Walks from $from towards $limit in $step increments, stepping over
     * grouping parentheses of type $grouping and over whitespace/comments, and
     * returns the first token that is neither. Used from both ends of an
     * operand so `( $b )` and `(($a))` collapse to the token they wrap.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param int|string $grouping
     */
    private function skipGrouping(array $tokens, int $from, int $limit, $grouping, int $step): int
    {
        $pointer = $from;

        while ($pointer !== $limit) {
            $code = $tokens[$pointer]['code'];

            if ($code !== $grouping && isset(Tokens::$emptyTokens[$code]) === false) {
                break;
            }

            $pointer += $step;
        }

        return $pointer;
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
            if (in_array($this->operandCode($tokens, $operand), self::STRING_LITERALS, true) === true) {
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
        if (StringLiteral::prefix($content) !== '') {
            return null;
        }

        $inner = $this->literalInnerAsDoubleQuoted($content);

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
     * Re-encodes a string-literal token's inner text (its content minus any
     * binary-string prefix and the surrounding quotes) so it is safe to embed
     * in a double-quoted string, or null when the literal is single-quoted and
     * carries backslash escapes whose meaning conversion could change.
     *
     * The delimiter is still read past any prefix rather than off the token's
     * first character, so this stays correct on its own terms — but no
     * prefixed literal reaches it any more: simpleInterpolation() refuses
     * those outright, because its interpolated result is untokenizable however
     * the inner text is encoded.
     *
     * Only whole literals reach here — hasNonInterpolatableOperand() has
     * already rejected a fragment of a multi-line one.
     */
    private function literalInnerAsDoubleQuoted(string $content): ?string
    {
        $delimiter = StringLiteral::delimiter($content);
        $inner = StringLiteral::inner($content);

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
                $opener = $tokens[$start]['bracket_opener'];
            } elseif ($code === T_CLOSE_PARENTHESIS && isset($tokens[$start]['parenthesis_opener']) === true) {
                $opener = $tokens[$start]['parenthesis_opener'];
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

            $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($opener - 1), null, true);

            // A bracket or parenthesis pair only extends the operand further
            // left when something callable or subscriptable sits immediately
            // before it — `foo(…)`, `$obj->run(…)`, `$arr[…]`. A *bare*
            // grouping parenthesis has no such head, so the operand starts at
            // the opener itself; stepping past it would swallow the assignment
            // operator before it and leave the whole chain unrecognisable.
            if ($prev === false || in_array($tokens[$prev]['code'], self::CHAIN_HEADS, true) === false) {
                $start = $opener;

                break;
            }

            $start = $prev;
        }

        return $start;
    }
}
