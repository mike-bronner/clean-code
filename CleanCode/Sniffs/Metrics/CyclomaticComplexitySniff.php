<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use MikeBronner\CleanCode\Support\CyclomaticComplexity;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a named function or method whose cyclomatic complexity reaches a
 * configurable report level.
 *
 * Replicates PHPMD's CodeSize/CyclomaticComplexity rule
 * (docs/phpmd/codesize-cyclomaticcomplexity.md, #88). A high count says the
 * declaration holds too many independent paths to hold in one's head, and needs
 * that many tests to cover; the fix is to break it up.
 *
 * PHPMD implements the rule in `PHPMD\Rule\CyclomaticComplexity`, whose whole
 * body is:
 *
 *     $threshold = $this->getIntProperty('reportLevel');
 *     $ccn = $node->getMetric('ccn2');
 *     if ($ccn < $threshold) {
 *         return;
 *     }
 *     $this->addViolation(...);
 *
 * Three things follow from that, and all three are load-bearing here:
 *
 * - **The report level is inclusive.** The guard returns on `$ccn < $threshold`,
 *   so a declaration measuring *exactly* `reportLevel` is reported. With the
 *   shipped default of 10, a complexity of 10 violates and 9 does not.
 *   phpmd.org's prose grades 8–10 as merely "high" and 11+ as "very high",
 *   which reads like a strict `>`; a live PHPMD 2.15.0 run over
 *   tests/fixtures/CyclomaticComplexitySniff/failing.php reports at ten. This
 *   sniff follows the tool, not the prose — matching the prose would miss a
 *   report PHPMD makes, which is the one direction that would put `phpmd` back
 *   in the pipeline.
 * - **The metric is PDepend's `ccn2`, not PHPCS's own count.** `ccn2` counts
 *   boolean operators and ignores several tokens PHPCS counts, which is why
 *   this is an independent sniff rather than a configuration of
 *   `Generic.Metrics.CyclomaticComplexity`; the doc tabulates the measured
 *   divergences. Support\CyclomaticComplexity holds the token walk and states
 *   every counting rule; it is shared with
 *   CleanCode.Metrics.ExcessiveClassComplexity (#87), which sums the same
 *   measurement over a class's methods.
 * - **Only named functions and methods are visited.** The rule implements
 *   `FunctionAware` and `MethodAware` and nothing else, so PHPMD never reports
 *   a closure or an arrow function under its own name however complex it is —
 *   PDepend folds their decision points into the declaration that holds them
 *   instead. Registering on T_FUNCTION alone reproduces both halves for free:
 *   T_CLOSURE and T_FN are separate tokens, so no exclusion logic is needed,
 *   and the shared walk already scores an inline closure against its host.
 *
 * Scope decisions:
 *
 * - A method of an **anonymous** class is reported here and is not reported by
 *   PHPMD — PDepend does not surface those methods to a MethodAware rule at
 *   all. That is a gap in PHPMD rather than a decision, reproducing it would
 *   mean writing code to *suppress* a true defect, and the extra report keeps
 *   this sniff a superset. tests/fixtures/CyclomaticComplexitySniff/
 *   divergences.php pins it.
 * - A declaration with **no body** — an abstract method, an interface method —
 *   measures 1 in both tools, so neither reports it. No exclusion is needed.
 * - **Class-level complexity is out of scope.** PHPMD's `showClassesComplexity`
 *   property is vestigial in 2.15.0: the rule implements `FunctionAware` and
 *   `MethodAware` and not `ClassAware`, so it never reaches a class. The
 *   class-level aggregate is a different PHPMD rule, replicated by
 *   CleanCode.Metrics.ExcessiveClassComplexity (#87).
 * - Detection only, matching PHPMD. Splitting a declaration up moves behaviour
 *   between methods and rewrites its call sites, which is a design change with
 *   no mechanical rewrite.
 *
 * The property is spelled `reportLevel`, exactly as PHPMD spells it, and
 * carries PHPMD's own shipped default, so a project's existing PHPMD
 * configuration for this rule transfers verbatim.
 */
class CyclomaticComplexitySniff implements Sniff
{
    /**
     * PHPMD's own shipped threshold, read from PHPMD 2.15.0's
     * rulesets/codesize.xml rather than from phpmd.org. It is named once and
     * used twice — as the property's default and as threshold()'s fallback for
     * an unusable configured value — so the two can never drift apart.
     */
    private const DEFAULT_REPORT_LEVEL = 10;

    /**
     * The cyclomatic complexity at which a declaration is reported —
     * inclusive, as PHPMD's own comparison is. Defaults to
     * DEFAULT_REPORT_LEVEL above.
     *
     * Deliberately typed `int|string|null` rather than `int`, for the reason
     * ExcessiveParameterListSniff's `minimum` records at length: PHPCS assigns
     * a `<property>` value straight from the ruleset XML without casting, so
     * what arrives is a *string* for `value="5"` and *null* for `value=""`. An
     * `int` declaration would turn a typo such as `value="ten"` into an
     * uncaught TypeError that aborts the whole phpcs run before a single file
     * is scanned. The union accepts whatever the XML supplies and lets
     * threshold() validate it.
     */
    public int|string|null $reportLevel = self::DEFAULT_REPORT_LEVEL;

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
        $complexity = CyclomaticComplexity::forDeclaration($phpcsFile, $stackPtr);

        if ($complexity < $threshold) {
            return;
        }

        $phpcsFile->addError(
            'The %s has a cyclomatic complexity of %s, reaching the report level of %s; '
                . 'break it into smaller declarations '
                . '(see docs/phpmd/codesize-cyclomaticcomplexity.md)',
            $stackPtr,
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $complexity, $threshold]
        );
    }

    /**
     * The configured report level as an integer.
     *
     * A value that is not a positive whole number — an empty property, a typo
     * such as `value="ten"`, a negative or zero level — falls back to PHPMD's
     * default rather than being used as written. Zero or a negative level would
     * report *every* declaration in the codebase, including a one-line getter,
     * so a configuration mistake degrades to the documented behaviour instead
     * of burying the report in noise. This is the same call
     * ExcessiveParameterListSniff makes for its own threshold.
     */
    private function threshold(): int
    {
        $configured = trim((string) $this->reportLevel);

        if (preg_match('/^\d+$/', $configured) !== 1 || (int) $configured < 1) {
            return self::DEFAULT_REPORT_LEVEL;
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
