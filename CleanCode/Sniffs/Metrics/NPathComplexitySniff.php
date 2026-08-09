<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a function or method whose NPath complexity reaches the configured
 * minimum.
 *
 * Replicates PHPMD's CodeSize/NPathComplexity rule
 * (docs/phpmd/codesize-npathcomplexity.md). NPath counts the acyclic execution
 * paths through a callable. Unlike cyclomatic complexity, which adds 1 per
 * decision point, NPath *multiplies* the path counts of statements in sequence,
 * so two independent `if`s score 4 rather than 3.
 *
 * PHPMD reads the `npath` metric from PDepend, and this sniff recomputes it
 * from PHPCS tokens. Every formula below is transcribed from PDepend 2.x's
 * NPathComplexityAnalyzer and then confirmed against a live PHPMD 2.15.0 run,
 * because several of them are not what the published NPath specification says
 * and none of them are stated in PHPMD's documentation. The fixtures under
 * tests/fixtures/NPathComplexitySniff/ pin each one against the numbers that
 * run produced.
 *
 * The whole callable body is a *sequence*: its NPath is the product of the
 * NPath of each statement in it. A statement that is not one of the constructs
 * below contributes 1, which is why plain code does not inflate the score.
 *
 * Per construct, with `B(x)` meaning "the boolean complexity of expression x"
 * (see `expressionComplexity()`) and `N(r)` the NPath of a statement range:
 *
 * - `if`:      `B(cond) + N(then) + N(else-or-elseif chain)`, plus 1 when the
 *              chain has neither an `elseif` nor an `else`. An `elseif` uses the
 *              same formula, so a chain nests rather than sums flat.
 * - `while`:   `B(cond) + N(body) + 1`
 * - `do while`:`B(cond) + N(body) + 1`
 * - `for`:     `1 + B(init; cond; step) + N(body)`
 * - `foreach`: `B(expr) + 1 + N(body)`
 * - `switch`:  `B(expr) + the sum of N(range) over every `case` *and* `default`
 *              label. A `switch` with no labels therefore scores 0 and zeroes
 *              the whole product — PDepend's behaviour, pinned by
 *              labellessSwitch() in passing.php.
 * - `try`:     the sum of `N(range)` over the `try` block, every `catch` block,
 *              and the `finally` block. Nothing is added for the construct.
 * - `? :`:     `B(cond) + B(then) + B(else) + 2`, where the short form `?:`
 *              doubles `B(cond)` instead of reading a `then` branch.
 * - `return`:  `B(expr)`, or 1 when that is 0. This makes `return $a && $b &&
 *              $c;` score 2 while the identical expression assigned to a
 *              variable scores 1 — a PDepend quirk, not a mistake here, pinned
 *              by keywordXorAndReturnChain() in failing.php.
 *
 * Not counted at all: `match` and its arms, `??`, `??=`, `?->`, `!`, `goto`,
 * `throw`, `yield`, and `break`/`continue`. `xor` *is* counted, unlike in
 * cyclomatic complexity where PDepend ignores it — ExcessiveClassComplexitySniff
 * in this same directory excludes `xor` for that reason, and the two sniffs
 * disagreeing here is deliberate (keywordXorAndReturnChain() in failing.php
 * pins it).
 *
 * Scope decisions, all matching PHPMD:
 *
 * - Named functions and methods only. PHPMD's rule is FunctionAware and
 *   MethodAware, so it measures each named callable separately, including one
 *   declared inside another (nestedNamedFunction() in passing.php).
 * - A closure or arrow function is *not* its own artifact: its statements are
 *   part of the enclosing callable and multiply into its score, which is what
 *   PDepend does by walking the callable's whole subtree
 *   (closureBodiesBelongToTheEnclosingCallable() in failing.php).
 * - The body of an anonymous class is skipped, and its methods are not reported
 *   either — a live PHPMD run reports neither (anonymousClassBody() in
 *   passing.php).
 * - An *abstract* method is measured and scores 1, the same as an empty
 *   concrete one. A method declared in an *interface* is not measured at all:
 *   PHPMD's method rules walk classes and traits, not interfaces. Both are
 *   pinned in passing.php.
 *
 * The report is attached to the `function` keyword, because the measurement
 * describes the whole callable rather than any one line inside it. Detection
 * only: the fix is to break the callable up, which is a design change with no
 * mechanical rewrite, and PHPMD offers no fix either.
 */
class NPathComplexitySniff implements Sniff
{
    /**
     * The NPath value at which a callable is reported. Spelled as PHPMD spells
     * it, and defaulting to the value PHPMD's codesize.xml ships, so an existing
     * PHPMD configuration for this rule transfers verbatim.
     *
     * PHPMD reports at or above this value rather than strictly above it — its
     * rule returns early only when `$npath < $threshold` — so the default of 200
     * reports a callable measuring exactly 200. That is the behaviour replicated
     * here. atOneBelowTheMinimum() in passing.php measures 199 and stays silent;
     * switchLabelsMultiply() in failing.php measures exactly 200 and is
     * reported.
     *
     * Deliberately untyped. PHPCS assigns a `<property>` value to a sniff as
     * the raw string from the ruleset XML, so an `int` declaration here would
     * turn `<property name="minimum" value="300"/>` into a TypeError. It is cast
     * where it is read instead — the same shape Generic.Files.LineLength uses
     * for its own numeric thresholds.
     *
     * @var int|string
     */
    public $minimum = 200;

    /**
     * The operators PDepend's `sumComplexity()` scores 1 each.
     *
     * `T_LOGICAL_XOR` is present on purpose: NPath counts `xor`, cyclomatic
     * complexity does not.
     *
     * @var array<int, int|string>
     */
    private const BOOLEAN_TOKENS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
    ];

    /**
     * Tokens that end the expression a ternary's condition starts in, used to
     * find where that condition begins. Scanning back to one of these mirrors
     * PDepend reading the condition as the first child of the ternary's parent
     * node.
     *
     * @var array<int, int|string>
     */
    private const EXPRESSION_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_RETURN,
        T_ECHO,
        T_PRINT,
        T_CASE,
        T_OPEN_TAG,
    ];

    /**
     * Tokens that end a ternary's else-branch: the first separator reached at
     * the ternary's own nesting level.
     *
     * @var array<int, int|string>
     */
    private const BRANCH_TERMINATORS = [
        T_SEMICOLON,
        T_COMMA,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $minimum = (int) $this->minimum;
        $npath = $this->callableComplexity($phpcsFile, $stackPtr);

        if ($npath === null || $npath < $minimum) {
            return;
        }

        $phpcsFile->addError(
            'The %s %s() has an NPath complexity of %s, at or above the '
                . 'configured minimum of %s; break it into smaller pieces (see '
                . 'docs/phpmd/codesize-npathcomplexity.md)',
            $stackPtr,
            'MinimumExceeded',
            [
                $this->callableKind($phpcsFile, $stackPtr),
                (string) $phpcsFile->getDeclarationName($stackPtr),
                $npath,
                $minimum,
            ]
        );
    }

    /**
     * Whether this declaration is a method of a class-like or a free function,
     * so the message reads the way PHPMD's does.
     */
    private function callableKind(File $phpcsFile, int $functionPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $code) {
            if (in_array($code, [T_CLASS, T_ANON_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) === true) {
                return 'method';
            }
        }

        return 'function';
    }

    /**
     * The NPath of one named callable, or null when PHPMD measures none.
     *
     * Two kinds of declaration are skipped outright, because a live PHPMD run
     * reports neither however low the threshold is set:
     *
     * - A method of an interface. PHPMD's method rules walk the methods of
     *   classes and traits, not of interfaces.
     * - A method of an anonymous class, along with the anonymous class itself.
     *
     * An *abstract* method is not skipped. It has no body, so there is no
     * sequence to multiply and it scores the 1 an empty body scores — which is
     * what PDepend records and what a live PHPMD run reports for it. A body the
     * tokenizer never closed lands here too, and 1 is the right answer for the
     * same reason: PHP cannot compile such a file, so nothing measurable is
     * being suppressed. Neither can reach a threshold of 2 or more, so this
     * matters only to a configuration that sets `minimum` to 1.
     */
    private function callableComplexity(File $phpcsFile, int $functionPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'] ?? [];

        if (in_array(T_ANON_CLASS, $conditions, true) === true) {
            return null;
        }

        if (in_array(T_INTERFACE, $conditions, true) === true) {
            return null;
        }

        $opener = $tokens[$functionPtr]['scope_opener'] ?? null;
        $closer = $tokens[$functionPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return 1;
        }

        $ptr = ($opener + 1);

        return $this->blockComplexity($phpcsFile, $tokens, $ptr, $closer);
    }

    /**
     * The NPath of a statement sequence: the product of the NPath of every
     * statement in it.
     *
     * $ptr is advanced to $end, and every token between is consumed exactly
     * once by exactly one frame — each construct's evaluator moves the shared
     * cursor past the range it measured. That keeps the whole walk linear in
     * the size of the callable rather than re-scanning nested bodies once per
     * level of nesting.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function blockComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $npath = 1;

        while ($ptr < $end) {
            $npath *= $this->statementComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        return $npath;
    }

    /**
     * The NPath of whatever starts at $ptr, advancing $ptr past it.
     *
     * Anything that is not a construct NPath scores returns 1 and advances by a
     * single token, so the cursor always moves and the caller's loop cannot
     * spin.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function statementComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $code = $tokens[$ptr]['code'];

        // A named function declared inside another callable is its own PHPMD
        // artifact, reported separately by this sniff's own registration on it.
        // An anonymous class body belongs to the anonymous class. A closure or
        // arrow function is neither: it is walked as part of this callable.
        if ($code === T_FUNCTION || $this->opensAnonymousClassBody($tokens, $ptr) === true) {
            $ptr = (($tokens[$ptr]['scope_closer'] ?? $ptr) + 1);

            return 1;
        }

        if ($code === T_IF) {
            return $this->ifComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_DO) {
            return $this->doWhileComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_WHILE || $code === T_FOR || $code === T_FOREACH) {
            return $this->loopComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_SWITCH) {
            return $this->switchComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_TRY) {
            return $this->tryComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_RETURN) {
            return $this->returnComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_INLINE_THEN) {
            return $this->ternaryComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        $ptr++;

        return 1;
    }

    /**
     * Whether the brace at $ptr opens an anonymous class body.
     *
     * Keyed on the brace rather than on T_ANON_CLASS so the walk still passes
     * through the constructor arguments between `new class` and that brace:
     * those are ordinary expressions in the enclosing callable, and PDepend
     * scores them there.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function opensAnonymousClassBody(array $tokens, int $ptr): bool
    {
        if ($tokens[$ptr]['code'] !== T_OPEN_CURLY_BRACKET) {
            return false;
        }

        $owner = $tokens[$ptr]['scope_condition'] ?? null;

        return $owner !== null && $tokens[$owner]['code'] === T_ANON_CLASS;
    }

    /**
     * `B(cond) + N(then) + N(chain)`, plus 1 when the chain ends without an
     * `else`. Used for `if` and, unchanged, for `elseif`: PDepend gives both the
     * same formula, so `if/elseif/else` nests instead of summing flat.
     *
     * `hasElse()` in PDepend is true when an `elseif` follows as well as when an
     * `else` does, which is why an `elseif` chain is not also charged the +1 at
     * every level.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function ifComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $npath = $this->conditionComplexity($phpcsFile, $tokens, $ptr);
        $npath += $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);

        $chain = $this->nextChainLink($phpcsFile, $tokens, $ptr, $end);

        if ($chain === null) {
            return ($npath + 1);
        }

        $ptr = $chain;

        if ($tokens[$chain]['code'] === T_ELSEIF) {
            return ($npath + $this->ifComplexity($phpcsFile, $tokens, $ptr, $end));
        }

        // `else if` written with a space is one construct to PDepend, scored
        // exactly as `elseif`. PHPCS builds no scope for the `else` in that
        // shape, so measuring it as an ordinary `else` body would leave the
        // `if` to be counted again as a statement of its own and multiply the
        // two instead of adding them (ElseIfWithSpace in failing.php).
        $inner = $phpcsFile->findNext(Tokens::$emptyTokens, ($chain + 1), $end, true);

        if ($inner !== false && $tokens[$inner]['code'] === T_IF) {
            $ptr = $inner;

            return ($npath + $this->ifComplexity($phpcsFile, $tokens, $ptr, $end));
        }

        return ($npath + $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end));
    }

    /**
     * The `elseif` or `else` continuing the chain whose branch ended at $ptr, or
     * null when the chain is over. $ptr is left on the branch just measured.
     *
     * Under the alternative syntax PHPCS closes each branch *on* the token that
     * starts the next one, so that token is the chain link rather than something
     * after it.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function nextChainLink(File $phpcsFile, array $tokens, int $ptr, int $end): ?int
    {
        $code = $tokens[$ptr]['code'];

        if ($code === T_ELSEIF || $code === T_ELSE) {
            return $ptr;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $end, true);

        if ($next === false) {
            return null;
        }

        if ($tokens[$next]['code'] === T_ELSEIF || $tokens[$next]['code'] === T_ELSE) {
            return $next;
        }

        return null;
    }

    /**
     * `B(cond) + N(body) + 1` for `while` and `do … while`, `1 + B(init; cond;
     * step) + N(body)` for `for`, and `B(expr) + 1 + N(body)` for `foreach`.
     *
     * The three differ only in where the constant 1 comes from, so they share
     * one implementation.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function loopComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $condition = $this->conditionComplexity($phpcsFile, $tokens, $ptr);
        $body = $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);

        return ($condition + $body + 1);
    }

    /**
     * `B(cond) + N(body) + 1`, with the trailing `while (…);` consumed so its
     * `while` is never mistaken for a loop of its own.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function doWhileComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $body = $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);
        $while = $phpcsFile->findNext(T_WHILE, $ptr, $end);

        if ($while === false) {
            return ($body + 1);
        }

        $condition = $this->conditionComplexity($phpcsFile, $tokens, $while);
        $closer = $tokens[$while]['parenthesis_closer'] ?? $while;
        $ptr = ($closer + 1);

        return ($condition + $body + 1);
    }

    /**
     * `B(expr)` plus the NPath of every `case` *and* `default` range.
     *
     * Nothing is added for the construct itself and nothing is added for a
     * missing `default`, so a `switch` carrying no labels at all scores 0 and
     * zeroes the product for the whole callable. That is what PDepend does.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function switchComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $switchPtr = $ptr;
        $npath = $this->conditionComplexity($phpcsFile, $tokens, $switchPtr);
        $opener = $tokens[$switchPtr]['scope_opener'] ?? null;
        $closer = $tokens[$switchPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            $ptr++;

            return $npath;
        }

        $labels = $this->switchLabels($phpcsFile, $tokens, $switchPtr, $opener, $closer);
        $count = count($labels);

        foreach ($labels as $index => $label) {
            $bodyEnd = $labels[($index + 1)] ?? $closer;
            $bodyPtr = ($this->labelBodyStart($phpcsFile, $tokens, $label, $bodyEnd) + 1);
            $npath += $this->blockComplexity($phpcsFile, $tokens, $bodyPtr, $bodyEnd);
        }

        $ptr = ($closer + 1);

        return $npath;
    }

    /**
     * The `case` and `default` tokens belonging to this switch and not to one
     * nested inside it, in source order.
     *
     * `match` carries no `case` at all and its `default` tokenizes as
     * T_MATCH_DEFAULT, so a `match` written inside a case body cannot leak a
     * label into this list.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array<int, int>
     */
    private function switchLabels(File $phpcsFile, array $tokens, int $switchPtr, int $opener, int $closer): array
    {
        $labels = [];
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext([T_CASE, T_DEFAULT], ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions'] ?? []) === $switchPtr) {
                $labels[] = $ptr;
            }
        }

        return $labels;
    }

    /**
     * The `:` or `;` ending a `case`/`default` label, after which its range
     * begins. A label whose delimiter is missing falls back to the label token
     * itself, so the range simply starts one token later.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function labelBodyStart(File $phpcsFile, array $tokens, int $label, int $end): int
    {
        $colon = $phpcsFile->findNext([T_COLON, T_SEMICOLON], $label, $end);

        return $colon === false ? $label : $colon;
    }

    /**
     * The sum of the NPath of the `try` block, every `catch` block, and the
     * `finally` block. Nothing is added for the construct, so `try {} catch {}`
     * scores 2 rather than 3.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function tryComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $npath = $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $end, true);

            if ($next === false) {
                return $npath;
            }

            $code = $tokens[$next]['code'];

            if ($code !== T_CATCH && $code !== T_FINALLY) {
                return $npath;
            }

            $ptr = $next;
            $npath += $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);
        }
    }

    /**
     * `B(expr)`, or 1 when the expression holds nothing NPath scores.
     *
     * PDepend multiplies the *whole* return expression's boolean complexity into
     * the sequence, which double-counts the condition of a ternary being
     * returned: the condition is scored once as part of the return expression
     * and again inside the ternary's own formula. `return ($a && $b) ? 1 : 2;`
     * scores 4 where the same ternary assigned to a variable scores 3. Both are
     * pinned in the fixtures.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function returnComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $stop = $this->statementEnd($tokens, ($ptr + 1), $end);
        $exprPtr = ($ptr + 1);
        $complexity = $this->expressionComplexity($phpcsFile, $tokens, $exprPtr, $stop);
        $ptr = ($stop + 1);

        return $complexity === 0 ? 1 : $complexity;
    }

    /**
     * The `;` ending the statement that starts at $from, or $end when there is
     * none.
     *
     * Every nested parenthesis, bracket, and brace is jumped over rather than
     * walked, so a `;` inside a closure body or an anonymous class body written
     * in the middle of the statement cannot be mistaken for the statement's own
     * terminator. `return new class { … };` is the shape that needs it, and
     * AnonymousClassBody in the fixtures pins it.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function statementEnd(array $tokens, int $from, int $end): int
    {
        $ptr = ($from - 1);

        while (++$ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_SEMICOLON) {
                return $ptr;
            }

            $skip = $this->closerFor($tokens, $ptr);

            if ($skip !== null) {
                $ptr = $skip;
            }
        }

        return $end;
    }

    /**
     * The token closing the group that opens at $ptr, or null when $ptr opens
     * nothing. Covers parentheses, short arrays and square brackets, and any
     * brace PHPCS built a scope for.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function closerFor(array $tokens, int $ptr): ?int
    {
        $code = $tokens[$ptr]['code'];

        if ($code === T_OPEN_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_closer'] ?? null;
        }

        if ($code === T_OPEN_SHORT_ARRAY || $code === T_OPEN_SQUARE_BRACKET) {
            return $tokens[$ptr]['bracket_closer'] ?? null;
        }

        if ($code === T_OPEN_CURLY_BRACKET) {
            return $tokens[$ptr]['scope_closer'] ?? ($tokens[$ptr]['bracket_closer'] ?? null);
        }

        return null;
    }

    /**
     * `B(cond) + B(then) + B(else) + 2`.
     *
     * The short form `?:` has no `then` branch; PDepend doubles the condition's
     * complexity in its place, which for the common `$a ?: $b` still yields 2.
     *
     * The condition is everything from the start of the enclosing expression up
     * to the `?`, which is how PDepend reads it — the condition is the first
     * child of the ternary's *parent* node, not a child of the ternary.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function ternaryComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $thenPtr = $ptr;
        $start = $this->expressionStart($tokens, $thenPtr);
        $condPtr = $start;
        $condition = $this->expressionComplexity($phpcsFile, $tokens, $condPtr, $thenPtr);

        $elsePtr = $this->ternaryElse($phpcsFile, $tokens, $thenPtr, $end);

        if ($elsePtr === null) {
            $ptr = ($thenPtr + 1);

            return ($condition + 2);
        }

        $branchPtr = ($thenPtr + 1);
        $then = $this->expressionComplexity($phpcsFile, $tokens, $branchPtr, $elsePtr);
        $isShort = $phpcsFile->findNext(Tokens::$emptyTokens, ($thenPtr + 1), $end, true) === $elsePtr;

        $stop = $this->expressionEnd($phpcsFile, $tokens, $elsePtr, $end);
        $otherwisePtr = ($elsePtr + 1);
        $otherwise = $this->expressionComplexity($phpcsFile, $tokens, $otherwisePtr, $stop);
        $ptr = $stop;

        if ($isShort === true) {
            return (($condition * 2) + $otherwise + 2);
        }

        return ($condition + $then + $otherwise + 2);
    }

    /**
     * The `:` pairing with the `?` at $thenPtr, skipping any nested ternary and
     * any `:` that belongs to something else (a named argument, an alternative
     * syntax block, a match arm).
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function ternaryElse(File $phpcsFile, array $tokens, int $thenPtr, int $end): ?int
    {
        $depth = 0;
        $ptr = $thenPtr;

        while (++$ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (isset($tokens[$ptr]['parenthesis_closer']) === true && $code === T_OPEN_PARENTHESIS) {
                $ptr = $tokens[$ptr]['parenthesis_closer'];

                continue;
            }

            if (isset($tokens[$ptr]['bracket_closer']) === true) {
                $ptr = $tokens[$ptr]['bracket_closer'];

                continue;
            }

            if (isset($tokens[$ptr]['scope_closer']) === true && $code === T_MATCH) {
                $ptr = $tokens[$ptr]['scope_closer'];

                continue;
            }

            if ($code === T_INLINE_THEN) {
                $depth++;

                continue;
            }

            if ($code === T_INLINE_ELSE) {
                if ($depth === 0) {
                    return $ptr;
                }

                $depth--;

                continue;
            }

            if ($code === T_SEMICOLON || $code === T_OPEN_CURLY_BRACKET) {
                return null;
            }
        }

        return null;
    }

    /**
     * Where the expression holding the ternary at $thenPtr begins.
     *
     * Scans back to the nearest token that can only precede an expression — a
     * separator, an opening bracket, an assignment, or a keyword that takes one.
     * Assignment operators are matched as a class so `=`, `.=`, `??=` and the
     * rest all bound the condition the same way.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function expressionStart(array $tokens, int $thenPtr): int
    {
        $ptr = $thenPtr;

        while (--$ptr > 0) {
            $code = $tokens[$ptr]['code'];

            if (isset($tokens[$ptr]['parenthesis_opener']) === true && $code === T_CLOSE_PARENTHESIS) {
                $ptr = $tokens[$ptr]['parenthesis_opener'];

                continue;
            }

            if (isset($tokens[$ptr]['bracket_opener']) === true) {
                $ptr = $tokens[$ptr]['bracket_opener'];

                continue;
            }

            if (in_array($code, self::EXPRESSION_BOUNDARIES, true) === true) {
                return ($ptr + 1);
            }

            if (isset(Tokens::$assignmentTokens[$code]) === true) {
                return ($ptr + 1);
            }
        }

        return ($thenPtr - 1);
    }

    /**
     * Where the else-branch starting after $elsePtr ends: the first separator at
     * the ternary's own nesting level.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function expressionEnd(File $phpcsFile, array $tokens, int $elsePtr, int $end): int
    {
        $ptr = $elsePtr;

        while (++$ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (isset($tokens[$ptr]['parenthesis_closer']) === true && $code === T_OPEN_PARENTHESIS) {
                $ptr = $tokens[$ptr]['parenthesis_closer'];

                continue;
            }

            if (isset($tokens[$ptr]['bracket_closer']) === true) {
                $ptr = $tokens[$ptr]['bracket_closer'];

                continue;
            }

            if (in_array($code, self::BRANCH_TERMINATORS, true) === true) {
                return $ptr;
            }
        }

        return $end;
    }

    /**
     * The boolean complexity PDepend's `sumComplexity()` computes for the
     * parenthesised condition owned by the construct at $ptr.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function conditionComplexity(File $phpcsFile, array $tokens, int $ptr): int
    {
        $opener = $tokens[$ptr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return 0;
        }

        $cursor = ($opener + 1);

        return $this->expressionComplexity($phpcsFile, $tokens, $cursor, $closer);
    }

    /**
     * PDepend's `sumComplexity()`: 1 for every boolean or logical operator in
     * the expression, plus the full NPath of any ternary in it.
     *
     * Boolean operators are counted flat rather than per nested expression node.
     * A live run scores `($a && $b) || $c` as 2 and `($a && ($b || $c))` as 2,
     * so grouping parentheses do not change the count and flat counting matches.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function expressionComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $sum = 0;

        while ($ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (in_array($code, self::BOOLEAN_TOKENS, true) === true) {
                $sum++;
                $ptr++;

                continue;
            }

            if ($code === T_INLINE_THEN) {
                $sum += $this->ternaryComplexity($phpcsFile, $tokens, $ptr, $end);

                continue;
            }

            $ptr++;
        }

        return $sum;
    }

    /**
     * The NPath of the body owned by the construct at $ptr, with $ptr advanced
     * past that body.
     *
     * A construct PHPCS built no scope for — an inline body it could not
     * delimit — has nothing to walk, so it scores the 1 an empty statement
     * sequence scores and the cursor moves on by one token.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function scopeComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $opener = $tokens[$ptr]['scope_opener'] ?? null;
        $closer = $tokens[$ptr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            $ptr++;

            return 1;
        }

        $bodyPtr = ($opener + 1);
        $bodyEnd = min($closer, $end);
        $npath = $this->blockComplexity($phpcsFile, $tokens, $bodyPtr, $bodyEnd);
        $ptr = $closer;

        return $npath;
    }
}
