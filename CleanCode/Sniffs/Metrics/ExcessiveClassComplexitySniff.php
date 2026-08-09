<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a class whose Weighted Method Count reaches the configured maximum.
 *
 * Replicates PHPMD's CodeSize/ExcessiveClassComplexity rule
 * (docs/phpmd/codesize-excessiveclasscomplexity.md). WMC is the sum of the
 * cyclomatic complexity of every method a class declares; PHPMD reads it from
 * PDepend's `wmc` metric, and this sniff recomputes it from PHPCS tokens.
 *
 * Every counting decision below was calibrated against a live PHPMD 2.15.0 /
 * PDepend run rather than from the documentation, because the documentation
 * does not state most of them and gets one of them wrong. The fixtures under
 * tests/fixtures/ExcessiveClassComplexitySniff/ pin each one.
 *
 * Counting, per method: 1, plus 1 for every decision point in the body.
 *
 * - Counted: `if`, `elseif` (`else if` too — it is `else` followed by `if`),
 *   `while` (the `while` of a `do … while` included, see below), `for`,
 *   `foreach`, `case`, `catch`, `&&`, `||`, `and`, `or`, and the `?` of a
 *   ternary (`?:` included). The keyword spellings `and` and `or` are pinned by
 *   KeywordBooleanOperators in passing.php; every other counted boolean in the
 *   fixtures is written in the symbol form.
 * - Not counted: `else`, `default`, `finally`, `xor`, `??`, `??=`, `?->`,
 *   `match` and its arms, `goto`.
 *
 * `do` is deliberately absent from the counted list even though a `do … while`
 * loop is worth 1. Every `do … while` carries exactly one `while`, and every
 * other loop written with `while` carries exactly one too, so counting `while`
 * alone scores both shapes correctly and needs no deduplication pass. Counting
 * `do` as well would double-count the loop.
 *
 * Scope decisions, all matching PHPMD:
 *
 * - Named classes only. PHPMD's rule is `ClassAware`, so an interface, a trait,
 *   an enum, or an anonymous class is never reported however complex it is —
 *   and T_CLASS is the token for a named class, T_ANON_CLASS being separate.
 * - Only methods the class itself declares. Methods reached through `use
 *   SomeTrait;` count towards the trait, not towards the using class (PDepend
 *   scores the using class 0), and a named function or a class-like declared
 *   inside a method is its own artifact, so its body is skipped.
 * - A closure or arrow function written inside a method is *not* its own
 *   artifact: its decision points belong to the enclosing method, which is what
 *   PDepend does by walking the method's whole subtree (InlineFunctionBodies in
 *   passing.php).
 * - An abstract method counts 1, the same as an empty concrete one.
 * - Only the body is measured. A ternary or boolean operator in a parameter
 *   default or a property default is outside every method body, and PDepend
 *   does not count it either.
 *
 * The report is attached to the class declaration, because the measurement
 * describes the whole class rather than any one line inside it. Detection only:
 * the fix is to split the class up, which is a design change with no mechanical
 * rewrite, and PHPMD offers no fix either.
 */
class ExcessiveClassComplexitySniff implements Sniff
{
    /**
     * The WMC at which a class is reported. Spelled as PHPMD spells it, and
     * defaulting to the value PHPMD's codesize.xml ships, so an existing PHPMD
     * configuration for this rule transfers verbatim.
     *
     * Deliberately untyped. PHPCS assigns a `<property>` value to a sniff as
     * the raw string from the ruleset XML, so an `int` declaration here would
     * turn `<property name="maximum" value="40"/>` into a TypeError. It is cast
     * where it is read instead — the same shape Generic.Files.LineLength uses
     * for its own numeric thresholds.
     *
     * @var int|string
     */
    public $maximum = 50;

    /**
     * The tokens that add 1 to a method's cyclomatic complexity.
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
     * Declarations that own their complexity rather than lending it to the
     * method they are written in. PDepend measures each as its own artifact, so
     * the whole body is skipped over.
     *
     * T_CLOSURE and T_FN are absent on purpose: a closure and an arrow function
     * are part of the enclosing method for this metric. Adding either here
     * lowers InlineFunctionBodies in passing.php below the 3 both tools measure,
     * which is what stops that omission from being silently reversible.
     *
     * These two are the whole list because they are the only nested
     * declarations a method body can hold. PHP rejects a `class`, `interface`,
     * `trait`, or `enum` declaration written inside a method outright ("Class
     * declarations may not be nested"), and one written inside a *named
     * function* nested in a method is already behind the T_FUNCTION skip.
     *
     * T_ANON_CLASS earns its place separately from T_FUNCTION: an anonymous
     * class holds constant and property defaults as well as methods, and a
     * `public bool $flags = self::YES && …;` sits outside every method it
     * declares, so skipping its methods alone would leave it counted.
     *
     * @var array<int, int|string>
     */
    private const NESTED_DECLARATION_TOKENS = [
        T_ANON_CLASS,
        T_FUNCTION,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $maximum = (int) $this->maximum;
        $count = $this->weightedMethodCount($phpcsFile, $stackPtr);

        if ($count === null || $count < $maximum) {
            return;
        }

        $phpcsFile->addError(
            'Class %s has a weighted method count of %s, at or above the '
                . 'configured maximum of %s; split it into smaller classes (see '
                . 'docs/phpmd/codesize-excessiveclasscomplexity.md)',
            $stackPtr,
            'MaximumExceeded',
            [(string) $phpcsFile->getDeclarationName($stackPtr), $count, $maximum]
        );
    }

    /**
     * Sums the cyclomatic complexity of every method the class declares, or
     * null when the class has no body PHPCS can delimit.
     *
     * A class the tokenizer never closed carries no scope_closer, and PHPCS
     * builds no scope for it, so its methods are not recorded as belonging to
     * it either — there is nothing to measure rather than a body to guess at.
     * PHP cannot compile such a file, so PDepend never parses it and PHPMD
     * reports nothing on it; returning null keeps the sniff silent for the same
     * reason instead of publishing a count of 0 that means "unmeasurable".
     */
    private function weightedMethodCount(File $phpcsFile, int $classPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$classPtr]['scope_closer'] ?? null;

        if ($end === null) {
            return null;
        }

        $count = 0;
        $ptr = $classPtr;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $end)) !== false) {
            if ($this->isDeclaredDirectlyIn($tokens, $ptr, $classPtr) === true) {
                $count += $this->cyclomaticComplexity($phpcsFile, $ptr);
            }
        }

        return $count;
    }

    /**
     * Whether the function at $functionPtr is a method of the class at
     * $classPtr rather than something nested deeper.
     *
     * PHPCS records a token's enclosing scopes outermost-first, so the last key
     * is the innermost one. A method of an anonymous class written inside a
     * method, or a named function declared inside one, therefore closes over a
     * different scope and is filtered out here.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isDeclaredDirectlyIn(array $tokens, int $functionPtr, int $classPtr): bool
    {
        return array_key_last($tokens[$functionPtr]['conditions'] ?? []) === $classPtr;
    }

    /**
     * Counts one method's decision points, plus the base 1 every method carries.
     *
     * An abstract method has no scope_opener at all. PDepend scores it 1 — the
     * same as a concrete method with an empty body — so the early return is the
     * matching behaviour, not a bail-out.
     */
    private function cyclomaticComplexity(File $phpcsFile, int $functionPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$functionPtr]['scope_opener'] ?? null;
        $closer = $tokens[$functionPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return 1;
        }

        $complexity = 1;
        $ptr = $opener;

        while (++$ptr < $closer) {
            $code = $tokens[$ptr]['code'];

            if (in_array($code, self::NESTED_DECLARATION_TOKENS, true) === true) {
                // Resume after the nested declaration. A declaration the
                // tokenizer never closed leaves $ptr where it is, and the loop's
                // own increment moves past it, so this cannot spin.
                $ptr = $tokens[$ptr]['scope_closer'] ?? $ptr;

                continue;
            }

            if (in_array($code, self::DECISION_TOKENS, true) === true) {
                $complexity++;
            }
        }

        return $complexity;
    }
}
