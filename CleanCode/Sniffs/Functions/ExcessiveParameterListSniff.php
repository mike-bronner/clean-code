<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a named function or method whose declared parameter list reaches a
 * configurable threshold.
 *
 * Replicates PHPMD's CodeSize ExcessiveParameterList rule
 * (docs/phpmd/codesize-excessiveparameterlist.md, #95). A long parameter list
 * says the callee is taking a bag of loose values that belong together; the fix
 * is to group the related parameters into an object.
 *
 * PHPMD implements the rule in `PHPMD\Rule\Design\LongParameterList`, whose
 * whole body is:
 *
 *     $threshold = $this->getIntProperty('minimum');
 *     $count = $node->getParameterCount();
 *     if ($count < $threshold) {
 *         return;
 *     }
 *     $this->addViolation(...);
 *
 * Two things follow from that, and both are load-bearing here:
 *
 * - **The threshold is inclusive.** The guard returns on `$count < $threshold`,
 *   so a declaration with *exactly* `minimum` parameters is reported. With the
 *   shipped default of 10, ten parameters violate; nine do not. phpmd.org's
 *   prose ("Consider reducing the number of parameters to less than 10") reads
 *   like a strict `>` and #95's acceptance criteria paraphrase it as
 *   "≤10 → no violation", but the code and a live PHPMD 2.15.0 run over
 *   tests/fixtures/ExcessiveParameterListSniff/boundaries.php both report at
 *   ten. This sniff follows the tool, not the prose — matching the prose would
 *   miss a report PHPMD makes, which is the one direction that would put
 *   `phpmd` back in the pipeline.
 * - **Only named functions and methods are visited.** The rule implements
 *   `FunctionAware` and `MethodAware` and nothing else, so PHPMD never reports
 *   a closure or an arrow function however many parameters it declares.
 *   Registering on T_FUNCTION alone reproduces that for free: T_CLOSURE and
 *   T_FN are separate tokens, so no exclusion logic is needed to match.
 *
 * `getMethodParameters()` supplies the count, which lines up with PDepend's
 * `getParameterCount()` on every shape #95 cares about — a promoted
 * constructor property is still a parameter, a variadic counts once, and a
 * declaration with no body (abstract, interface) is counted from its signature
 * like any other. Each of those is pinned by a fixture and was confirmed
 * against a live PHPMD run.
 *
 * Scope decisions:
 *
 * - A method of an **anonymous** class is reported here and is not reported by
 *   PHPMD — PDepend does not surface those methods to a MethodAware rule at
 *   all. That is a gap in PHPMD rather than a decision, reproducing it would
 *   mean writing code to *suppress* a true defect, and the extra report keeps
 *   this sniff a superset. tests/fixtures/ExcessiveParameterListSniff/
 *   divergences.php pins it.
 * - Detection only, matching PHPMD. Collapsing parameters into an object means
 *   introducing that object and rewriting every call site, which is a design
 *   change with no mechanical rewrite.
 *
 * The property is spelled `minimum`, exactly as PHPMD spells it, and carries
 * PHPMD's own shipped default, so a project's existing PHPMD configuration for
 * this rule transfers verbatim.
 */
class ExcessiveParameterListSniff implements Sniff
{
    /**
     * PHPMD's own shipped threshold, read from PHPMD 2.15.0's
     * rulesets/codesize.xml rather than from phpmd.org. It is named once and
     * used twice — as the property's default and as threshold()'s fallback for
     * an unusable configured value — so the two can never drift apart.
     */
    private const DEFAULT_MINIMUM = 10;

    /**
     * The parameter count at which a declaration is reported — inclusive, as
     * PHPMD's own comparison is. Defaults to DEFAULT_MINIMUM above.
     *
     * Deliberately typed `int|string|null` rather than `int`, and every member
     * of that union is load-bearing. PHPCS assigns a `<property>` value
     * straight from the ruleset XML without casting —
     * Ruleset::setSniffProperty() ends in `$sniffObject->$name = $value;` — so
     * what actually arrives is:
     *
     * - a *string*, for `<property name="minimum" value="5"/>`. A well-formed
     *   numeric string would survive a native `int` (Ruleset.php declares no
     *   strict_types, so PHP coerces on assignment), but a typo such as
     *   `value="ten"` would not: it aborts the whole phpcs run with an
     *   uncaught TypeError before a single file is scanned.
     * - *null*, for `<property name="minimum" value=""/>`. PHPCS rewrites an
     *   empty value to null a few lines above that assignment, so an empty
     *   property kills a run the same way — and it does so even against an
     *   `int|string` union, which is how this type arrived at three members.
     *
     * All three were measured against a real consumer ruleset, not assumed;
     * tests/Standards/ExcessiveParameterListTest.php drives each one. The
     * union accepts whatever the XML supplies and lets threshold() validate
     * it, so a configuration mistake degrades to the documented default
     * instead of taking the run down — the same reason PHPCS's own threshold
     * sniffs (Generic.Files.LineLength, Generic.Metrics.CyclomaticComplexity)
     * leave the equivalent properties untyped.
     */
    public int|string|null $minimum = self::DEFAULT_MINIMUM;

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
        $threshold = $this->threshold();

        // A declaration cut short mid-edit has no parenthesis pair, and
        // getMethodParameters() answers with an empty list rather than raising.
        $count = count($phpcsFile->getMethodParameters($stackPtr));

        if ($count < $threshold) {
            return;
        }

        $phpcsFile->addError(
            'The %s declares %s parameters, reaching the maximum of %s; group the related '
                . 'parameters into an object instead '
                . '(see docs/phpmd/codesize-excessiveparameterlist.md)',
            $stackPtr,
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $count, $threshold]
        );
    }

    /**
     * The configured threshold as an integer.
     *
     * A value that is not a positive whole number — an empty property, a typo
     * such as `value="ten"`, a negative or zero count — falls back to PHPMD's
     * default rather than being used as written. Zero or a negative threshold
     * would report *every* declaration in the codebase, including one taking
     * no parameters at all, so a configuration mistake degrades to the
     * documented behaviour instead of burying the report in noise.
     */
    private function threshold(): int
    {
        $configured = trim((string) $this->minimum);

        if (preg_match('/^\d+$/', $configured) !== 1 || (int) $configured < 1) {
            return self::DEFAULT_MINIMUM;
        }

        return (int) $configured;
    }

    /**
     * Names the declaration for the diagnostic: "method render()" when a
     * class-like scope holds it directly, "function render()" otherwise — the
     * same split PHPMD's message makes between "method" and "function".
     *
     * The *innermost* enclosing scope decides, not merely the presence of a
     * class somewhere above: a named function declared inside a method is a
     * function, and PHPMD calls it one too (its rule is FunctionAware for
     * exactly that shape). Walking outwards and stopping at the first scope of
     * either kind is what keeps the two apart; a plain `array_intersect` over
     * every condition would call such a function a method.
     */
    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $classLike = [T_ANON_CLASS, T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT];
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];
        $subject = 'function';

        foreach (array_reverse($conditions, true) as $code) {
            if (in_array($code, $classLike, true) === true) {
                $subject = 'method';

                break;
            }

            if (in_array($code, [T_CLOSURE, T_FN, T_FUNCTION], true) === true) {
                break;
            }
        }

        return $subject . ' ' . $phpcsFile->getDeclarationName($stackPtr) . '()';
    }
}
