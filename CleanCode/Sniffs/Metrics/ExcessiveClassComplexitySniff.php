<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use MikeBronner\CleanCode\Support\CyclomaticComplexity;
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
 * Every counting decision was calibrated against a live PHPMD 2.15.0 / PDepend
 * run rather than from the documentation, because the documentation does not
 * state most of them and gets one of them wrong. The per-method count itself
 * lives in Support\CyclomaticComplexity, which spells the counted and uncounted
 * constructs out and which CleanCode.Metrics.CyclomaticComplexity (#88) reads
 * for the same measurement one declaration at a time. The fixtures under
 * tests/fixtures/ExcessiveClassComplexitySniff/ pin each rule for this rule's
 * own totals — KeywordBooleanOperators pins the `and`/`or` spellings,
 * UncountedConstructs pins the zero-scoring list, InlineFunctionBodies pins that
 * a closure belongs to the method holding it, AnonymousClassArguments pins that
 * only an anonymous class's *body* is skipped, and MemberDefaults pins that only
 * method bodies are measured.
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
 * - Only the *body* of a nested anonymous class is skipped. Its constructor
 *   arguments are ordinary expressions in the enclosing method — PHP evaluates
 *   them there and PDepend scores them there (AnonymousClassArguments in
 *   passing.php).
 * - A closure or arrow function written inside a method is *not* its own
 *   artifact: its decision points belong to the enclosing method, which is what
 *   PDepend does by walking the method's whole subtree (InlineFunctionBodies in
 *   passing.php).
 * - An abstract method counts 1, the same as an empty concrete one.
 * - Only the body is measured. A ternary or boolean operator in a parameter
 *   default, a property default, or a constant default is outside every method
 *   body, and PDepend does not count it either (MemberDefaults in passing.php).
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
                $count += CyclomaticComplexity::forDeclaration($phpcsFile, $ptr);
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
}
