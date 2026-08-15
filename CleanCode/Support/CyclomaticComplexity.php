<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;

/**
 * PDepend's `ccn2` metric, recomputed from PHPCS tokens.
 *
 * Two sniffs need this same number, and PHPMD reads both of their rules off the
 * same PDepend measurement, so the walk lives here rather than in either of
 * them: CleanCode.Metrics.CyclomaticComplexity reports one declaration's count
 * (PHPMD CodeSize/CyclomaticComplexity, #88), and
 * CleanCode.Metrics.ExcessiveClassComplexity sums it over a class's methods
 * (PHPMD CodeSize/ExcessiveClassComplexity, #87). Keeping one copy is what stops
 * the two rules from drifting apart on a construct only one of them has a
 * fixture for.
 *
 * Every counting decision below was calibrated against a live PHPMD 2.15.0 /
 * PDepend run rather than from the documentation, because the documentation does
 * not state most of them and gets one of them wrong. PDepend's
 * CyclomaticComplexityAnalyzer starts each callable at 1 and has exactly one
 * incrementing visitor per counted construct — visitIfStatement,
 * visitElseIfStatement, visitForStatement, visitForeachStatement,
 * visitWhileStatement, visitDoWhileStatement, visitSwitchLabel (skipped for
 * `default`), visitCatchStatement, visitConditionalExpression,
 * visitBooleanAndExpression, visitBooleanOrExpression, visitLogicalAndExpression
 * and visitLogicalOrExpression — and no visitor at all for anything else.
 *
 * Counting: 1, plus 1 for every decision point in the body.
 *
 * - Counted: `if`, `elseif` (`else if` too — it is `else` followed by `if`),
 *   `while` (the `while` of a `do … while` included, see below), `for`,
 *   `foreach`, `case`, `catch`, `&&`, `||`, `and`, `or`, and the `?` of a
 *   ternary (`?:` included).
 * - Not counted: `else`, `default`, `finally`, `xor`, `??`, `??=`, `?->`,
 *   `match` and its arms, `goto`. `xor` is the one PDepend has no visitor for
 *   while having one for its `and`/`or` siblings, so it is measured rather than
 *   assumed: PHPMD 2.15.0 scores a method whose only operator is `xor` at 1.
 *
 * `do` is deliberately absent from the counted list even though a `do … while`
 * loop is worth 1. Every `do … while` carries exactly one `while`, and every
 * other loop written with `while` carries exactly one too, so counting `while`
 * alone scores both shapes correctly and needs no deduplication pass. Counting
 * `do` as well would double-count the loop.
 *
 * Two nesting rules, both matching PDepend:
 *
 * - A closure or an arrow function is *not* its own artifact: its decision
 *   points belong to the declaration it is written in, which is what PDepend
 *   does by walking the callable's whole subtree.
 * - A named function or a class-like declared inside a declaration *is* its own
 *   artifact, so its body is skipped. Only the *body* of a nested anonymous
 *   class is skipped, though — its constructor arguments are ordinary
 *   expressions in the enclosing declaration, and PDepend scores them there.
 */
final class CyclomaticComplexity
{
    /**
     * The tokens that add 1 to a declaration's cyclomatic complexity.
     *
     * @var array<int, int|string>
     */
    private const DECISION_TOKENS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_CASE,
        T_CATCH,
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_INLINE_THEN,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_WHILE,
    ];

    /**
     * Counts one declaration's decision points, plus the base 1 every
     * declaration carries.
     *
     * A declaration with no body — an abstract method, an interface method, or
     * one the tokenizer never closed — has no scope_opener at all. PDepend
     * scores an abstract method 1, the same as a concrete method with an empty
     * body, so the early return is the matching behaviour rather than a
     * bail-out.
     *
     * @param int $declarationPtr a T_FUNCTION, T_CLOSURE, or T_FN pointer
     */
    public static function forDeclaration(File $phpcsFile, int $declarationPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$declarationPtr]['scope_opener'] ?? null;
        $closer = $tokens[$declarationPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return 1;
        }

        $complexity = 1;
        $ptr = $opener;

        while (++$ptr < $closer) {
            $code = $tokens[$ptr]['code'];

            if (self::opensSkippedBody($tokens, $ptr) === true) {
                // Resume after the skipped body. A declaration the tokenizer
                // never closed leaves $ptr where it is, and the loop's own
                // increment moves past it, so this cannot spin.
                $ptr = $tokens[$ptr]['scope_closer'] ?? $ptr;

                continue;
            }

            if (in_array($code, self::DECISION_TOKENS, true) === true) {
                $complexity++;
            }
        }

        return $complexity;
    }

    /**
     * Whether the token at $ptr starts a body belonging to a nested declaration
     * rather than to the declaration being measured. PDepend measures each such
     * declaration as its own artifact, so its body is skipped over.
     *
     * The two declarations a body can hold are skipped from different tokens,
     * because different things sit in front of their braces:
     *
     * - A nested named function is skipped from its `function` keyword. What
     *   precedes its brace is its own parameter list, and a decision point in a
     *   parameter default scores in neither tool — MemberDefaults in
     *   ExcessiveClassComplexitySniff/passing.php pins that for a plain method,
     *   and a live PDepend run scores a nested function's own parameter default
     *   the same 0.
     * - An anonymous class is skipped from its opening brace instead, so the
     *   walk still passes through the constructor arguments between `new class`
     *   and that brace. Those are ordinary expressions in the enclosing
     *   declaration: PHP evaluates them there and PDepend scores them there.
     *   Only the body — methods, property and constant defaults — belongs to
     *   the anonymous class, which is why skipping the methods alone would not
     *   be enough either.
     *
     * Keying the anonymous-class case on the brace rather than on T_ANON_CLASS
     * also nests for free: an anonymous class inside another one's argument
     * list is walked, not jumped over with the outer expression.
     *
     * T_CLOSURE and T_FN are absent on purpose: a closure and an arrow function
     * are part of the enclosing declaration for this metric. PHP rejects a
     * `class`, `interface`, `trait`, or `enum` declaration written inside a
     * function outright ("Class declarations may not be nested"), and one
     * written inside a nested named function is already behind the T_FUNCTION
     * skip, so nothing else can appear.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private static function opensSkippedBody(array $tokens, int $ptr): bool
    {
        if ($tokens[$ptr]['code'] === T_FUNCTION) {
            return true;
        }

        if ($tokens[$ptr]['code'] !== T_OPEN_CURLY_BRACKET) {
            return false;
        }

        // A bare block's brace owns nothing, and PHPCS records no scope closer
        // for it either. The null check is there to keep the lookup below from
        // raising an undefined-key warning on that shape, not to decide a
        // measurement: a brace with no closer has nothing to skip to, so the
        // walk carries on through the block whichever way this returns.
        $owner = $tokens[$ptr]['scope_condition'] ?? null;

        return $owner !== null && $tokens[$owner]['code'] === T_ANON_CLASS;
    }
}
