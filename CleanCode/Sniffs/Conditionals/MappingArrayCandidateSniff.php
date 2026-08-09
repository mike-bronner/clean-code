<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the token-visible slice of the "Conditionals: Mapping Arrays"
 * standard (#23), as scoped by #163.
 *
 * Reports one **warning** per qualifying chain, at the leading `if`. The
 * standard's core — whether a mapping array actually reduces complexity for a
 * given case — is a judgement no token walk can make, so this sniff only points
 * at the mechanical shape and leaves the call to code review.
 *
 * A chain qualifies when *all* of the following hold:
 *
 * 1. Every `if`/`elseif` condition is exactly three significant tokens: a plain
 *    variable, `===` or `==`, and a scalar literal — in either operand order.
 * 2. The same variable is the subject of every condition, compared by token
 *    content, so `$a` and `$b` never share a chain.
 * 3. Every branch body — including a trailing `else` — is a single statement:
 *    either `return <expr>;` throughout, or `<target> = <expr>;` throughout with
 *    the identical target variable.
 * 4. Every branch expression is built only from value tokens (literals,
 *    variables, constants, array literals, property/index reads). Anything that
 *    can do work — a call, `new`, an increment, a nested assignment — disqualifies
 *    the chain.
 * 5. The branch count reaches $minimumBranches, counting a trailing `else` as
 *    the default entry.
 *
 * Every continuation shape PHP offers is walked, because PHPCS attaches scope to
 * a different token in each (verified against the tokenizer, not assumed):
 *
 * | Shape                        | Where the clause's scope lives                |
 * |------------------------------|-----------------------------------------------|
 * | `} elseif (…) {`             | `T_ELSEIF`, closer is the `}`                 |
 * | `} else if (…) {`            | the trailing `T_IF`; the `T_ELSE` has no scope |
 * | `if (…) return …;`           | no scope at all — body ends at the `;`        |
 * | `if (…): … elseif (…): …`    | opener is the `:`, closer is the *next clause* |
 *
 * Deliberately **not** flagged, and why:
 *
 * - Non-equality operators, compound conditions (`&&`, `||`), calls in a
 *   condition, and parenthesised conditions — all fail the three-token rule.
 *   Judging those from tokens alone is noise-prone.
 * - A subject that is not a plain variable (`$this->status`, `$row['type']`).
 *   The standard speaks about "different values of the same variable", and a
 *   property or index read can be a different value on each evaluation.
 * - `null` as the compared literal: it is not a scalar, and `$x == null` is a
 *   loose-emptiness test rather than a value lookup.
 * - Bodies that mix `return` with assignment, or assign to different targets.
 *   Neither collapses into a single lookup.
 * - Bodies whose expression can do work. An array literal evaluates every value
 *   eagerly, so hoisting a call into a map changes when — and how often — it
 *   runs.
 * - `switch` and `match`. This sniff registers on `T_IF` only, so neither is
 *   ever inspected: `switch` already centralises its subject, and `match` is the
 *   construct this standard recommends.
 *
 * Detection only — the safe rewrite depends on the surrounding scope (where the
 * map should live, what the missing-key fallback is), so there is nothing to
 * auto-fix. See docs/standards/conditionals-mapping-arrays.md.
 */
class MappingArrayCandidateSniff implements Sniff
{
    /**
     * How many branches a chain needs before it is reported, counting a trailing
     * `else` as the default entry.
     *
     * Left untyped on purpose: PHPCS hands ruleset `<property>` values over as
     * strings, which a typed `int` property would reject with a TypeError. The
     * value is cast where it is read instead.
     *
     * @var int
     */
    public $minimumBranches = 3;

    /**
     * The two comparisons the standard names. Anything else — `>`, `!==`,
     * `instanceof` — is a different question about the variable, not a lookup.
     *
     * @var array<int, int|string>
     */
    private const EQUALITY_OPERATORS = [
        T_IS_IDENTICAL,
        T_IS_EQUAL,
    ];

    /**
     * PHP's four scalar types written as literals — int, float, string, and
     * both spellings of bool, which is why the list is five tokens long. `null`
     * is absent because it is not a scalar, and an object or array literal
     * cannot appear here at all.
     *
     * @var array<int, int|string>
     */
    private const SCALAR_LITERALS = [
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_TRUE,
        T_FALSE,
    ];

    /**
     * The only tokens a branch expression may be built from — an allowlist, so
     * an unfamiliar construct fails closed and the chain goes unreported.
     *
     * `T_OPEN_PARENTHESIS` is absent, which is what excludes every call and
     * every `new`: `foo()`, `$this->map()`, and `new Foo` all need a paren the
     * moment they take arguments, and `T_NEW`/`T_FN`/`T_FUNCTION` are absent
     * besides. `T_STRING` is present for bare and class constants (`MY_CONST`,
     * `self::MAP`), which a call would otherwise be indistinguishable from.
     *
     * @var array<int, int|string>
     */
    private const VALUE_TOKENS = [
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_TRUE,
        T_FALSE,
        T_NULL,
        T_VARIABLE,
        T_STRING,
        T_SELF,
        T_STATIC,
        T_PARENT,
        T_DOUBLE_COLON,
        T_NS_SEPARATOR,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OPEN_SQUARE_BRACKET,
        T_CLOSE_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_CLOSE_SHORT_ARRAY,
        T_DOUBLE_ARROW,
        T_COMMA,
        T_MINUS,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_IF];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isChainHead($phpcsFile, $stackPtr) === false) {
            return;
        }

        $clauses = $this->collectClauses($phpcsFile, $stackPtr);

        if ($clauses === null || count($clauses) < (int) $this->minimumBranches) {
            return;
        }

        $subject = $this->sharedSubject($clauses);

        if ($subject === null || $this->bodiesAgree($clauses) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Mapping-array candidate: %d branches all compare "%s" against a scalar literal and do'
                . ' nothing but produce a value. Prefer a mapping array or match where one applies.',
            $stackPtr,
            'IfChain',
            [count($clauses), $subject]
        );
    }

    /**
     * Whether this `if` opens a chain rather than continuing one.
     *
     * The `if` of a spaced `else if` is a full T_IF token with its own scope, so
     * it reaches process() exactly like a leading one. Its chain is already
     * walked from the real head, and reporting it again would warn twice on one
     * chain.
     */
    private function isChainHead(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

        if ($previous === false) {
            return true;
        }

        return $phpcsFile->getTokens()[$previous]['code'] !== T_ELSE;
    }

    /**
     * Walks the whole chain from its leading `if`, returning one entry per
     * branch, or null as soon as any branch fails the shape rules.
     *
     * @return array<int, array{subject: string|null, kind: string, target: string|null}>|null
     */
    private function collectClauses(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $clauses = [];
        $pointer = $stackPtr;

        while ($pointer !== null) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ELSE) {
                $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

                // A spaced `else if`: the trailing `if` carries the condition
                // and the scope, so hand the clause to it.
                if ($next !== false && $tokens[$next]['code'] === T_IF) {
                    $pointer = $next;

                    continue;
                }
            }

            if (in_array($code, [T_IF, T_ELSEIF, T_ELSE], true) === false) {
                break;
            }

            $subject = null;

            if ($code !== T_ELSE) {
                $subject = $this->conditionSubject($phpcsFile, $pointer);

                if ($subject === null) {
                    return null;
                }
            }

            $extent = $this->clauseExtent($phpcsFile, $pointer);

            if ($extent === null) {
                return null;
            }

            $body = $this->bodyShape($phpcsFile, $extent['bodyStart'], $extent['bodyEnd']);

            if ($body === null) {
                return null;
            }

            $clauses[] = $body + ['subject' => $subject];

            if ($code === T_ELSE) {
                break;
            }

            $pointer = $extent['next'];
        }

        return $clauses;
    }

    /**
     * The variable a condition tests, when the condition is exactly
     * `<variable> === <literal>` or `<literal> === <variable>`; null otherwise.
     *
     * The three-token rule is what excludes compound conditions, calls,
     * parenthesised conditions, and non-equality operators in one stroke.
     */
    private function conditionSubject(File $phpcsFile, int $clausePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['parenthesis_opener'], $tokens[$clausePtr]['parenthesis_closer']) === false) {
            return null;
        }

        $condition = $this->significantTokens(
            $phpcsFile,
            $tokens[$clausePtr]['parenthesis_opener'] + 1,
            $tokens[$clausePtr]['parenthesis_closer'] - 1
        );

        if (
            count($condition) !== 3
            || in_array($tokens[$condition[1]]['code'], self::EQUALITY_OPERATORS, true) === false
        ) {
            return null;
        }

        [$left, , $right] = $condition;

        if (
            $tokens[$left]['code'] === T_VARIABLE
            && in_array($tokens[$right]['code'], self::SCALAR_LITERALS, true) === true
        ) {
            return $tokens[$left]['content'];
        }

        if (
            $tokens[$right]['code'] === T_VARIABLE
            && in_array($tokens[$left]['code'], self::SCALAR_LITERALS, true) === true
        ) {
            return $tokens[$right]['content'];
        }

        return null;
    }

    /**
     * Locates a clause's body and the token where the next clause would begin.
     *
     * PHPCS models the three body forms differently, so each is read on its own
     * terms rather than through one assumed scope shortcut:
     *
     * - braced — scope runs `{` to `}`, and the next clause follows the `}`;
     * - alternative syntax — scope runs `:` to the *next clause's own keyword*,
     *   which therefore doubles as the continuation pointer;
     * - brace-less — no scope at all, so the body is the single statement after
     *   the condition, ending at its semicolon.
     *
     * @return array{bodyStart: int, bodyEnd: int, next: int|null}|null
     */
    private function clauseExtent(File $phpcsFile, int $clausePtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['scope_opener'], $tokens[$clausePtr]['scope_closer']) === true) {
            $closer = $tokens[$clausePtr]['scope_closer'];
            $isBraced = $tokens[$closer]['code'] === T_CLOSE_CURLY_BRACKET;
            $next = $isBraced === true
                ? $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true)
                : $closer;

            return [
                'bodyStart' => $tokens[$clausePtr]['scope_opener'] + 1,
                'bodyEnd' => $closer - 1,
                'next' => $next === false ? null : $next,
            ];
        }

        $afterCondition = isset($tokens[$clausePtr]['parenthesis_closer']) === true
            ? $tokens[$clausePtr]['parenthesis_closer'] + 1
            : $clausePtr + 1;
        // findEndOfStatement() reads the token it is handed, so it has to start
        // on the statement's first real token, never the whitespace before it.
        $bodyStart = $phpcsFile->findNext(Tokens::$emptyTokens, $afterCondition, null, true);

        if ($bodyStart === false) {
            return null;
        }

        $bodyEnd = $phpcsFile->findEndOfStatement($bodyStart);

        if ($bodyEnd <= $bodyStart) {
            return null;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $bodyEnd + 1, null, true);

        return [
            'bodyStart' => $bodyStart,
            'bodyEnd' => $bodyEnd,
            'next' => $next === false ? null : $next,
        ];
    }

    /**
     * Classifies a branch body as a single value-producing statement, or null
     * when it is anything else — empty, multi-statement, or side-effectful.
     *
     * @return array{kind: string, target: string|null}|null
     */
    private function bodyShape(File $phpcsFile, int $bodyStart, int $bodyEnd): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $body = $this->significantTokens($phpcsFile, $bodyStart, $bodyEnd);
        $last = array_pop($body);

        // A body that does not end at a semicolon is not one statement: an
        // inner `if`, a loop, or a nested block all end on a brace instead.
        if ($last === null || $tokens[$last]['code'] !== T_SEMICOLON) {
            return null;
        }

        // Any *further* semicolon means a second statement — the exclusion for
        // multi-statement and side-effectful bodies.
        foreach ($body as $pointer) {
            if ($tokens[$pointer]['code'] === T_SEMICOLON) {
                return null;
            }
        }

        if ($body !== [] && $tokens[$body[0]]['code'] === T_RETURN) {
            return $this->isValueExpression($phpcsFile, array_slice($body, 1)) === true
                ? ['kind' => 'return', 'target' => null]
                : null;
        }

        if (
            count($body) >= 2
            && $tokens[$body[0]]['code'] === T_VARIABLE
            && $tokens[$body[1]]['code'] === T_EQUAL
        ) {
            return $this->isValueExpression($phpcsFile, array_slice($body, 2)) === true
                ? ['kind' => 'assign', 'target' => $tokens[$body[0]]['content']]
                : null;
        }

        return null;
    }

    /**
     * Whether an expression is non-empty and built only from value tokens.
     *
     * @param array<int, int> $pointers
     */
    private function isValueExpression(File $phpcsFile, array $pointers): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($pointers === []) {
            return false;
        }

        foreach ($pointers as $pointer) {
            if (in_array($tokens[$pointer]['code'], self::VALUE_TOKENS, true) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * The variable every condition in the chain tests, or null when they differ.
     *
     * @param array<int, array{subject: string|null, kind: string, target: string|null}> $clauses
     */
    private function sharedSubject(array $clauses): ?string
    {
        $subjects = array_unique(array_filter(
            array_column($clauses, 'subject'),
            static fn (?string $subject): bool => $subject !== null
        ));

        return count($subjects) === 1 ? (string) reset($subjects) : null;
    }

    /**
     * Whether every branch produces its value the same way: all `return`, or
     * all assignment to one identical target. A mixed chain has no single
     * lookup to collapse into.
     *
     * @param array<int, array{subject: string|null, kind: string, target: string|null}> $clauses
     */
    private function bodiesAgree(array $clauses): bool
    {
        if (count(array_unique(array_column($clauses, 'kind'))) !== 1) {
            return false;
        }

        // Every `return` branch carries a null target, so one shared value here
        // means "all returns" just as much as it means "one assignment target".
        return count(array_unique(array_column($clauses, 'target'))) === 1;
    }

    /**
     * The pointers in a range, with whitespace and comments dropped.
     *
     * @return array<int, int>
     */
    private function significantTokens(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if (
                isset($tokens[$pointer]) === true
                && isset(Tokens::$emptyTokens[$tokens[$pointer]['code']]) === false
            ) {
                $pointers[] = $pointer;
            }
        }

        return $pointers;
    }
}
