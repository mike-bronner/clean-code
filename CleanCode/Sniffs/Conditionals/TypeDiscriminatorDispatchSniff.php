<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the token-visible slice of the Open-Closed principle from
 * "Pattern: SOLID" (#5), as scoped by #324.
 *
 * Reports one **warning** per qualifying construct — at the `switch` keyword, or
 * at the leading `if` of a chain. A `switch`, or an `if`/`elseif` chain, that
 * dispatches on the same type-discriminator read across three or more literal
 * branches is closed for extension and open for modification: adding a variant
 * of that type forces an edit to this construct, which is the principle
 * inverted.
 *
 * A construct qualifies when *all* of the following hold:
 *
 * 1. The subject is a **discriminator read** — a variable plus exactly one
 *    property or index hop (`$shape->type`, `$shape?->type`, `$row['type']`).
 *    A plain variable is not one, and neither is a read two hops deep.
 * 2. For an `if` chain, every clause compares that read against a scalar
 *    literal with `===` or `==`, in either operand order, and every clause reads
 *    the *same* discriminator — compared token for token, base variable
 *    included, so `$shape->type` and `$model->type` never share a chain. For a
 *    `switch`, the subject is read once, and every `case` label is itself a
 *    scalar literal.
 * 3. The branch count reaches $minimumBranches. Each `case` label counts on its
 *    own, so stacked labels sharing one fallthrough body count once each;
 *    `default` counts as one wherever it sits; a trailing `else` counts as one.
 *
 * Every continuation shape PHP offers is walked, because PHP_CodeSniffer
 * attaches scope to a different token in each (verified against the tokenizer,
 * not assumed):
 *
 * | Shape                        | Where the clause's scope lives                 |
 * |------------------------------|------------------------------------------------|
 * | `} elseif (…) {`             | `T_ELSEIF`, closer is the `}`                  |
 * | `} else if (…) {`            | the trailing `T_IF`; the `T_ELSE` has no scope |
 * | `if (…) return …;`           | no scope at all — body ends at the `;`         |
 * | `if (…): … elseif (…): …`    | opener is the `:`, closer is the *next clause* |
 * | `switch (…): … endswitch;`   | opener is the `:`, closer is the `endswitch`   |
 *
 * A chain ends where its continuations do. Only `elseif` and `else` continue
 * one, so a bare `if` written after a clause's body opens a construct of its
 * own however alike the two read, and the branches of the two are never added
 * together. A chain ends at a nested `if`, too: a clause whose brace-less body
 * writes one hands every `elseif` and `else` after it to that nested `if`,
 * because PHP binds a dangling continuation to the nearest `if` still open.
 *
 * Deliberately **not** flagged, and why:
 *
 * - A plain-variable subject (`switch ($type)`, `if ($type === 'circle')`). A
 *   bare local carries no evidence it holds a *type*, and the `if` form of it is
 *   already owned by CleanCode.Conditionals.MappingArrayCandidate.
 * - `switch (true) { case <expr>: }`. The switch subject is the literal `true`,
 *   not a discriminator field, even though each `case` re-reads one.
 * - A discriminator read more than one hop deep (`$row['meta']['type']`,
 *   `$a->b->type`). Skipped whole, never partially matched.
 * - Any non-literal or compound branch condition — `instanceof`, ranges, `!==`,
 *   calls, `&&`/`||` — and any `case` label that is not a scalar literal, which
 *   includes a class constant and a bare constant. One such label disqualifies
 *   the whole switch.
 * - `match`. It is the construct CleanCode.Conditionals.MappingArrayCandidate
 *   and CleanCode.Conditionals.AvoidConditionals both recommend as the
 *   *replacement*, so flagging it would have the ruleset argue with itself. This
 *   sniff never registers on T_MATCH.
 *
 * Overlap with CleanCode.Conditionals.AvoidConditionals is expected and
 * deliberate: that sniff counts a branch, this one names a pattern.
 *
 * Detection only — the remedy is a type hierarchy or a map plus every call site
 * rewritten, which is a design change rather than a mechanical one, so there is
 * nothing to auto-fix. See docs/standards/pattern-solid.md.
 */
class TypeDiscriminatorDispatchSniff implements Sniff
{
    /**
     * How many branches a construct needs before it is reported, counting each
     * `case` label, a `default`, and a trailing `else` as one branch each.
     *
     * Left untyped on purpose: PHPCS hands ruleset `<property>` values over as
     * strings, which a typed `int` property would reject with a TypeError. The
     * value is cast where it is read instead.
     *
     * @var int
     */
    public $minimumBranches = 3;

    /**
     * The message every report carries: the principle by name, the branch
     * count, and the discriminator as it is written in the source.
     */
    private const MESSAGE = 'Open-Closed principle: %d branches of this %s dispatch on the type discriminator'
        . ' "%s", so a new variant of that type means editing this construct. Prefer polymorphism, or a'
        . ' mapping array where the branches only produce a value.';

    /**
     * The two comparisons a dispatch chain is written with.
     *
     * Family: PHP_CodeSniffer's own Tokens::$equalityTokens, whose six members
     * are accounted for here. T_IS_EQUAL and T_IS_IDENTICAL are the two that ask
     * "is this the X variant?". T_IS_NOT_EQUAL and T_IS_NOT_IDENTICAL are the
     * negation, which selects everything *but* one variant and so does not
     * enumerate a type; T_IS_SMALLER_OR_EQUAL and T_IS_GREATER_OR_EQUAL are
     * ordering comparisons, which a type discriminator has no ordering for.
     *
     * @var array<int, int|string>
     */
    private const EQUALITY_OPERATORS = [
        T_IS_IDENTICAL,
        T_IS_EQUAL,
    ];

    /**
     * PHP's four scalar types written as literals — int, float, string, and both
     * spellings of bool, which is why the list is five tokens long. `null` is
     * absent because it is not a scalar and names no variant; an object or array
     * literal cannot be a `case` label's whole value here either.
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
     * The literals a sign may legally precede. PHP has no negative-number token:
     * `-1` is a T_MINUS followed by a T_LNUMBER, so a signed literal is only ever
     * recognised as this pair.
     *
     * @var array<int, int|string>
     */
    private const NUMERIC_LITERALS = [
        T_LNUMBER,
        T_DNUMBER,
    ];

    /**
     * The two tokens that can sign a numeric literal.
     *
     * Family: PHP's two additive operators, `+` and `-`, which are also its two
     * sign operators. Both are admitted only immediately before a numeric
     * literal that is the operand's whole remainder, so neither is ever read as
     * arithmetic — see isScalarLiteral().
     *
     * @var array<int, int|string>
     */
    private const SIGN_TOKENS = [
        T_MINUS,
        T_PLUS,
    ];

    /**
     * The operators a one-hop property read is written with.
     *
     * Family: the three member-access operators PHP defines — `->`, `?->` and
     * `::`. The two instance operators are here because both read a property off
     * the variable on their left, and a nullsafe read discriminates exactly as a
     * plain one does. T_DOUBLE_COLON is excluded: it reads a static property or
     * a class constant, which belongs to the class rather than to the value
     * being dispatched on, so it is not a per-instance type discriminator.
     *
     * @var array<int, int|string>
     */
    private const PROPERTY_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
    ];

    /**
     * The keywords that *continue* an `if` chain — the only tokens a finished
     * clause may hand the walk on to.
     *
     * Family: the five keywords PHP's `if` grammar defines — `if`, `elseif`,
     * `else`, the alternative syntax's `endif`, and the two-word `else if`. Two
     * of them continue a chain. T_IF is deliberately absent: a bare `if` sitting
     * after a clause's body is a *new* statement that merely happens to be
     * adjacent, and admitting it here merges two unrelated constructs into one
     * chain that was never written. The `if` of a two-word `else if` is reached
     * through its own T_ELSE instead — see collectSubjects(). T_ENDIF closes the
     * chain rather than continuing it.
     *
     * @var array<int, int|string>
     */
    private const CONTINUATION_KEYWORDS = [
        T_ELSEIF,
        T_ELSE,
    ];

    /**
     * The tokens that end a brace-less body without ending a statement, so the
     * chain fails closed rather than reading on past the construct the body
     * lives in.
     *
     * Family: every way PHP closes a construct a body can sit inside — the
     * three closing pairs plus the short-array closer, PHP's six
     * alternative-syntax `end…` keywords (`endif`, `endwhile`, `endfor`,
     * `endforeach`, `endswitch`, `enddeclare`, which is the whole list its
     * grammar defines), and the close tag, which ends the PHP block itself and
     * carries no pointers of any kind.
     *
     * Named rather than derived from the scope map, because the map is exactly
     * what a file the tokenizer could not parse is missing: an `endif` whose
     * `if` never closed carries no scope_closer to recognise it by, and that
     * unparsed file is the only route to any of these. No body PHP accepts
     * reaches one — its own groups are stepped over whole and its statement
     * ends at a semicolon first.
     *
     * @var array<int, int|string>
     */
    private const BODY_TERMINATORS = [
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_TAG,
        T_ENDIF,
        T_ENDWHILE,
        T_ENDFOR,
        T_ENDFOREACH,
        T_ENDSWITCH,
        T_ENDDECLARE,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_SWITCH, T_IF];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_SWITCH) {
            $this->processSwitch($phpcsFile, $stackPtr);

            return;
        }

        $this->processIfChain($phpcsFile, $stackPtr);
    }

    /**
     * Reports a `switch` whose subject is a discriminator read and whose every
     * arm is a scalar-literal `case` or the `default`.
     */
    private function processSwitch(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Both pointers below are withheld by the tokenizer on a file it cannot
        // parse. PHPCS leaves parenthesis_closer present-but-null rather than
        // absent, so the first check states the requirement rather than being
        // what enforces it; the scope check is what the arm walk depends on.
        if (isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer']) === false) {
            return;
        }

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $subject = $this->discriminator($tokens, $this->significantTokens(
            $phpcsFile,
            $tokens[$stackPtr]['parenthesis_opener'] + 1,
            $tokens[$stackPtr]['parenthesis_closer'] - 1
        ));

        if ($subject === null) {
            return;
        }

        $branches = $this->switchBranches($phpcsFile, $stackPtr);

        if ($branches === null || $branches < (int) $this->minimumBranches) {
            return;
        }

        $phpcsFile->addWarning(
            self::MESSAGE,
            $stackPtr,
            'SwitchDispatch',
            [$branches, 'switch', $subject]
        );
    }

    /**
     * How many branches a `switch` has, or null when any arm disqualifies it.
     *
     * Only the arms of *this* switch are counted. A nested switch's arms carry
     * that switch as their innermost condition, so the ownership check is what
     * keeps their labels — and their disqualifications — out of this verdict.
     */
    private function switchBranches(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $branches = 0;

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code !== T_CASE && $code !== T_DEFAULT) {
                continue;
            }

            if (array_key_last($tokens[$pointer]['conditions']) !== $stackPtr) {
                continue;
            }

            // An arm whose scope the tokenizer could not resolve is an arm this
            // switch cannot be read past, so the whole switch fails closed
            // rather than being counted short. It holds for `default` as much as
            // for `case`: `default` carries no label to read, but an arm PHP
            // cannot parse is no evidence of a branch either, and counting it
            // would report a file PHP rejects. The route there is an arm whose
            // colon is missing from a switch that still closes — truncating the
            // file instead costs the switch its own scope, and the check above
            // turns it away before any arm is read.
            if (isset($tokens[$pointer]['scope_opener']) === false) {
                return null;
            }

            if ($code === T_DEFAULT) {
                $branches++;

                continue;
            }

            $label = $this->significantTokens($phpcsFile, $pointer + 1, $tokens[$pointer]['scope_opener'] - 1);

            if ($this->isScalarLiteral($tokens, $label) === false) {
                return null;
            }

            $branches++;
        }

        return $branches;
    }

    /**
     * Reports an `if` chain whose every clause compares one discriminator read
     * against a scalar literal.
     */
    private function processIfChain(File $phpcsFile, int $stackPtr): void
    {
        if ($this->isChainHead($phpcsFile, $stackPtr) === false) {
            return;
        }

        $subjects = $this->collectSubjects($phpcsFile, $stackPtr);

        if ($subjects === null || count($subjects) < (int) $this->minimumBranches) {
            return;
        }

        $subject = $this->sharedSubject($subjects);

        if ($subject === null) {
            return;
        }

        $phpcsFile->addWarning(
            self::MESSAGE,
            $stackPtr,
            'IfChain',
            [count($subjects), 'if/elseif chain', $subject]
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
     * One entry per branch of the chain — the discriminator each clause reads,
     * or null for a trailing `else` — and null for the whole chain as soon as
     * any clause fails the shape rules.
     *
     * @return array<int, string|null>|null
     */
    private function collectSubjects(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $subjects = [];
        $pointer = $stackPtr;

        while ($pointer !== null) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ELSE) {
                $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

                // An `else` with nothing after it at all — a file truncated
                // mid-clause is the reachable way there — has no body to be a
                // branch of, so the whole chain fails closed rather than
                // counting a branch that is not written yet.
                if ($next === false) {
                    return null;
                }

                // A spaced `else if`: the trailing `if` carries the condition
                // and the scope, so hand the clause to it.
                if ($tokens[$next]['code'] === T_IF) {
                    $pointer = $next;

                    continue;
                }

                // A trailing `else` is the chain's default branch and its last:
                // nothing can follow it, so no continuation is looked for.
                $subjects[] = null;

                break;
            }

            // Every other pointer the walk holds is a clause of this chain by
            // construction: the head is the T_IF process() was handed, and
            // nextClause() only ever hands back a continuation keyword.
            $subject = $this->conditionDiscriminator($phpcsFile, $pointer);

            if ($subject === null) {
                return null;
            }

            $subjects[] = $subject;
            $pointer = $this->nextClause($phpcsFile, $pointer);
        }

        return $subjects;
    }

    /**
     * Where the clause after this one begins, or null when the chain ends here.
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
     * What each form finds is the token that *follows* the body, which is not
     * yet a reason to believe it continues the chain — so every form hands its
     * find to continuation() for that verdict.
     *
     * The brace-less form has no scope to read at all, so its body is walked
     * rather than looked up. See bracelessNextClause().
     */
    private function nextClause(File $phpcsFile, int $clausePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['scope_opener'], $tokens[$clausePtr]['scope_closer']) === true) {
            $closer = $tokens[$clausePtr]['scope_closer'];

            if ($tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return $this->continuation($tokens, $closer);
            }

            return $this->continuation(
                $tokens,
                $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true)
            );
        }

        return $this->bracelessNextClause($phpcsFile, $clausePtr);
    }

    /**
     * The same answer for the one body form PHP_CodeSniffer gives no scope: the
     * body is walked from the token after the condition until something ends
     * it.
     *
     * The walk asks only the question the chain asks, so it never borrows a
     * *statement* boundary to answer a *clause* one. findEndOfStatement() is
     * the obvious shortcut and it is wrong in both directions — it overshoots a
     * body that ends at an `elseif`, because no continuation keyword ends a
     * statement, and it undershoots every statement PHP writes as more than one
     * scope, returning the `try` block's own closer for a `try`/`catch` and the
     * `do` block's for a `do`/`while`. Reading the body directly removes both
     * failure modes rather than validating a span against them one shape at a
     * time.
     *
     * Four things end the walk, and each is the answer:
     *
     * - a nested `if`, at whatever depth it is written. PHP binds a dangling
     *   `elseif` or `else` to the nearest `if` still open — that nested one,
     *   never the clause it is the body of — so no continuation after it is
     *   this clause's, and the chain ends here.
     * - an `elseif` or `else`. Neither can be part of the body, so the body
     *   ended before it and this is the clause the chain continues at.
     * - a semicolon, which really does end the body. Whatever follows it is a
     *   continuation only if continuation() says so. One statement writes a
     *   semicolon that ends less than it appears to: a `do` with a brace-less
     *   body ends at the `while (…);` after that body, not at the body's own
     *   semicolon. So a brace-less `do` is counted on the way in and the first
     *   semicolon after it is spent closing that body instead of the clause's.
     *   Naming `do` is not a special case but the whole of a closed set: it is
     *   the only statement in PHP whose body ends before the statement does.
     *   Every other multi-part statement — `try`/`catch`/`finally` above all —
     *   braces each of its parts, so the step-over below already carries the
     *   walk across them. A *braced* `do` is one of those, and is deliberately
     *   not counted: the step-over has already crossed its body, so its
     *   `while (…);` closes a statement rather than a body, and spending that
     *   semicolon would read the walk on past the clause. No fixture can pin
     *   that guard on its own: PHP admits no continuation after a braced
     *   `do`/`while`, so the over-read finds nothing to return either way. It
     *   is written for the reason above rather than for an observable.
     * - a closer this walk never opened, or an alternative-syntax `end…`: the
     *   construct *around* the body ended first, which only a file PHP cannot
     *   parse can do. The chain fails closed rather than reading on into
     *   whatever follows that construct. See BODY_TERMINATORS.
     *
     * Anything that opens a scope, a parenthesis, or a bracket is stepped over
     * whole. That is what keeps a closure's, an anonymous class's, or a braced
     * loop's contents out of the four tests above — a boundary written in there
     * is closed before the body ends, so it can neither take a continuation nor
     * be one — and it is also what carries the walk across `try`/`catch`/
     * `finally` and `do`/`while` without either being named: each of their
     * scopes is stepped over in turn, and the walk simply arrives at whatever
     * follows the last one.
     *
     * The four tests come *before* the step-over, because an `if` owns the
     * scope it opens and alternative-syntax `elseif`/`else` own theirs:
     * stepping first would skip the very tokens being looked for.
     *
     * The walk visits each token of the body once and stops at the first
     * boundary, so a clause costs its own body rather than the rest of the
     * file. That matters for linearly nested brace-less `if`s, where every one
     * of them reaches process() as a chain head of its own: each ends its walk
     * on the nested `if` that opens its body, one token in. Re-deriving a
     * statement end per head instead is O(n) work paid n times, which a file of
     * a few thousand nested clauses turns into minutes of CPU.
     */
    private function bracelessNextClause(File $phpcsFile, int $clausePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['parenthesis_closer']) === false) {
            return null;
        }

        $pointer = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            $tokens[$clausePtr]['parenthesis_closer'] + 1,
            null,
            true
        );

        $openDoBodies = 0;

        while ($pointer !== false) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_IF) {
                return null;
            }

            if (in_array($code, self::CONTINUATION_KEYWORDS, true) === true) {
                return $pointer;
            }

            if ($code === T_DO && isset($tokens[$pointer]['scope_closer']) === false) {
                ++$openDoBodies;
            }

            if ($code === T_SEMICOLON) {
                if ($openDoBodies === 0) {
                    return $this->continuation(
                        $tokens,
                        $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true)
                    );
                }

                --$openDoBodies;
            }

            if (in_array($code, self::BODY_TERMINATORS, true) === true) {
                return null;
            }

            $pointer = $phpcsFile->findNext(
                Tokens::$emptyTokens,
                $this->groupCloser($tokens, $pointer) + 1,
                null,
                true
            );
        }

        return null;
    }

    /**
     * The last token of the group this one opens, or the token itself when it
     * opens none — so a caller stepping to the returned pointer plus one always
     * moves forward, whatever it was handed.
     *
     * A token owns the scope it names only when it is that scope's opener or
     * its condition; every other token carrying the pointers is inside the
     * scope already. That is PHP_CodeSniffer's own skip-nested-statements test,
     * from File::findEndOfStatement(), and the parenthesis and bracket pairs
     * are read the same way. A closer never resolved — the tokenizer leaves it
     * present-but-null on a file it cannot parse — is no group, so the walk
     * steps a single token instead of jumping to nowhere.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function groupCloser(array $tokens, int $pointer): int
    {
        $token = $tokens[$pointer];
        $ownsScope = isset($token['scope_opener'], $token['scope_closer']) === true
            && ($pointer === $token['scope_opener'] || $pointer === ($token['scope_condition'] ?? null));

        if ($ownsScope === true) {
            return max($pointer, $token['scope_closer']);
        }

        if (isset($token['parenthesis_closer']) === true && $pointer === ($token['parenthesis_opener'] ?? null)) {
            return max($pointer, $token['parenthesis_closer']);
        }

        if (isset($token['bracket_closer']) === true && $pointer === ($token['bracket_opener'] ?? null)) {
            return max($pointer, $token['bracket_closer']);
        }

        return $pointer;
    }

    /**
     * The token after a clause's body read as the chain's next clause, or null
     * when it is anything else — the end of the file, an unrelated statement, or
     * a fresh `if` that only sits next to this one.
     *
     * Textual adjacency is not continuation. Two `if` statements written back to
     * back are two constructs, each closed for extension on its own terms, and
     * counting their branches together would report a chain nobody wrote.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function continuation(array $tokens, int|false $pointer): ?int
    {
        if ($pointer === false) {
            return null;
        }

        return in_array($tokens[$pointer]['code'], self::CONTINUATION_KEYWORDS, true) === true
            ? $pointer
            : null;
    }

    /**
     * The discriminator a condition tests, when the condition is exactly
     * `<discriminator> === <literal>` or `<literal> === <discriminator>`; null
     * otherwise.
     *
     * The condition is split on its single equality operator and each side is
     * matched against one of two operand shapes. That is what excludes compound
     * conditions, calls, parenthesised conditions, non-equality operators, and
     * arithmetic on an operand in one stroke — while still admitting a signed
     * numeric literal, which PHP writes as two tokens rather than one.
     */
    private function conditionDiscriminator(File $phpcsFile, int $clausePtr): ?string
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

        $operators = [];

        foreach ($condition as $index => $pointer) {
            if (in_array($tokens[$pointer]['code'], self::EQUALITY_OPERATORS, true) === true) {
                $operators[] = $index;
            }
        }

        if (count($operators) !== 1) {
            return null;
        }

        $left = array_slice($condition, 0, $operators[0]);
        $right = array_slice($condition, $operators[0] + 1);
        $subject = $this->discriminator($tokens, $left);

        if ($subject !== null && $this->isScalarLiteral($tokens, $right) === true) {
            return $subject;
        }

        $subject = $this->discriminator($tokens, $right);

        return $subject !== null && $this->isScalarLiteral($tokens, $left) === true ? $subject : null;
    }

    /**
     * An operand read as a discriminator — a variable plus exactly one property
     * or index hop — spelled back as its own source text, or null when the
     * operand is any other shape.
     *
     * The text is what the branches are compared on, and it is built from every
     * token of the read including the base variable's own name, so
     * `$shape->type` and `$model->type` are two discriminators rather than one.
     *
     * A read two hops deep (`$row['meta']['type']`, `$a->b->type`) matches no
     * shape here and is skipped whole: matching its tail would let two reads
     * rooted in different values look identical.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param array<int, int>                  $pointers
     */
    private function discriminator(array $tokens, array $pointers): ?string
    {
        $codes = array_map(static fn (int $pointer): int|string => $tokens[$pointer]['code'], $pointers);

        $isPropertyRead = count($codes) === 3
            && $codes[0] === T_VARIABLE
            && in_array($codes[1], self::PROPERTY_OPERATORS, true) === true
            && $codes[2] === T_STRING;

        // Only a quoted key names a field. A positional index (`$row[0]`) says
        // nothing about a type, and a constant or variable key cannot be
        // compared across branches by its own text alone.
        $isIndexRead = count($codes) === 4
            && $codes[0] === T_VARIABLE
            && $codes[1] === T_OPEN_SQUARE_BRACKET
            && $codes[2] === T_CONSTANT_ENCAPSED_STRING
            && $codes[3] === T_CLOSE_SQUARE_BRACKET;

        if ($isPropertyRead === false && $isIndexRead === false) {
            return null;
        }

        return implode('', array_map(
            static fn (int $pointer): string => $tokens[$pointer]['content'],
            $pointers
        ));
    }

    /**
     * Whether an operand is a scalar literal: one literal token, or a sign
     * immediately followed by a numeric literal. The two-token form is the only
     * way PHP spells a negative number, so without it `case -1:` would look like
     * a non-literal label and disqualify a switch that is exactly the shape this
     * sniff exists for.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param array<int, int>                  $pointers
     */
    private function isScalarLiteral(array $tokens, array $pointers): bool
    {
        if (count($pointers) === 1) {
            return in_array($tokens[$pointers[0]]['code'], self::SCALAR_LITERALS, true);
        }

        return count($pointers) === 2
            && in_array($tokens[$pointers[0]]['code'], self::SIGN_TOKENS, true) === true
            && in_array($tokens[$pointers[1]]['code'], self::NUMERIC_LITERALS, true) === true;
    }

    /**
     * The discriminator every condition in the chain reads, or null when they
     * differ. A trailing `else` reads none and is skipped.
     *
     * @param array<int, string|null> $subjects
     */
    private function sharedSubject(array $subjects): ?string
    {
        $named = array_unique(array_filter($subjects, static fn (?string $subject): bool => $subject !== null));

        return count($named) === 1 ? (string) reset($named) : null;
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
