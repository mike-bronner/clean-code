<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\CodeSize;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a class that declares more methods than the configured maximum.
 *
 * Replicates PHPMD's CodeSize/TooManyMethods
 * (docs/phpmd/codesize-toomanymethods.md, #80). A class with too many methods
 * is a candidate for refactoring into smaller objects; accessors are ignored,
 * because a wide accessor surface says nothing about how much the class does.
 *
 * No PHPCS, Slevomat, or Squiz sniff counts methods per class. The nearest
 * neighbours measure something else entirely — SlevomatCodingStandard.Classes
 * .ClassLength and SlevomatCodingStandard.Files.FileLength count lines,
 * SlevomatCodingStandard.Functions.FunctionLength counts a single function's
 * lines, and Generic.Metrics.CyclomaticComplexity scores branching within one
 * method — so this metric needs a custom sniff.
 *
 * Behaviour verified against a live PHPMD 2.15.0 install rather than
 * phpmd.org's rule page, which is stale on one point (see below):
 *
 * - **Classes only.** PHPMD's rule is `ClassAware`, so it never speaks about
 *   an interface, trait, or enum, and pdepend reports nothing for an
 *   anonymous class. Registering `T_CLASS` alone reproduces that exactly:
 *   PHPCS gives interfaces, traits, enums, and anonymous classes their own
 *   tokens (`T_INTERFACE`, `T_TRAIT`, `T_ENUM`, `T_ANON_CLASS`).
 * - **Every method counts, whatever its visibility.** PHPMD reads
 *   `getMethodNames()`, which covers public, protected, private, static, and
 *   abstract declarations, and the constructor.
 * - **Directly declared methods only.** Methods of a nested anonymous class,
 *   and functions declared inside a method body, belong to their own scope.
 * - **Names matching `$ignorepattern` are not counted.** The shipped default
 *   is a case-insensitive *prefix* match with no word boundary, so `isolate`,
 *   `hash`, and `within` are ignored the same way `isReady` is. That is
 *   PHPMD's own behaviour, not an approximation of it.
 * - **The threshold is exclusive.** PHPMD returns early on
 *   `count <= maxmethods`, so a class holding exactly `maxmethods` counted
 *   methods is silent and `maxmethods + 1` is reported.
 * - **Reported on the class declaration.** The defect is the size of the whole
 *   class, so it has no statement line of its own.
 *
 * Detection only, matching PHPMD: splitting a class is a design decision about
 * where each behaviour belongs, which no mechanical rewrite can make.
 *
 * The one documented divergence from the issue's transcription of the rule is
 * $ignorepattern's default. phpmd.org still prints the pre-2.x `(^(set|get))i`
 * while every shipped PHPMD 2.x `codesize.xml` sets
 * `(^(set|get|is|has|with))i`. The shipped value wins here, so that a class
 * PHPMD passes is a class this sniff passes.
 */
class TooManyMethodsSniff implements Sniff
{
    /**
     * The largest number of counted methods a class may declare. A class with
     * exactly this many is compliant. PHPMD's own default, raised from 10 in
     * PHPMD 2.3.
     */
    public int $maxmethods = 25;

    /**
     * Methods whose name matches this expression are left out of the count.
     * PHPMD's shipped default ignores accessors and fluent setters, in any
     * casing.
     */
    public string $ignorepattern = '(^(set|get|is|has|with))i';

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
        if ($this->hasUsableIgnorePattern() === false) {
            $phpcsFile->addError(
                'The ignorepattern property is not a valid regular expression: %s. '
                    . 'No method can be excluded from the count until it is corrected',
                $stackPtr,
                'InvalidIgnorePattern',
                [$this->ignorepattern]
            );

            return;
        }

        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A class PHPCS tokenised mid-edit carries no body to count and no
        // name to report, so there is nothing to say about it yet.
        if ($opener === null || $closer === null || $name === null) {
            return;
        }

        $counted = $this->countMethods($phpcsFile, $stackPtr, $opener, $closer);

        if ($counted <= $this->maxmethods) {
            return;
        }

        $phpcsFile->addError(
            'Class %s declares %s counted methods, more than the maximum of %s. Methods '
                . 'matching %s are not counted. Split it into smaller classes '
                . '(see docs/phpmd/codesize-toomanymethods.md)',
            $stackPtr,
            'MaxExceeded',
            [$name, $counted, $this->maxmethods, $this->ignorepattern]
        );
    }

    /**
     * Whether $ignorepattern compiles. The property reaches this sniff from a
     * ruleset, so a typo in a consumer's XML would otherwise make preg_match()
     * return false on every method: each one would be treated as *not*
     * ignored, quietly turning a mis-typed pattern into a stricter rule than
     * the one that was configured. Reporting the broken pattern instead keeps
     * a configuration defect from reading as a code defect.
     *
     * The compile failure is swallowed by a handler of this sniff's own rather
     * than by `@`: since PHP 8 the suppression operator still routes the
     * diagnostic to whatever error handler is installed, so `@` would leave
     * PHPCS printing a raw "preg_match(): ..." warning over its own report.
     * The finding this method feeds says the same thing, in the report.
     */
    private function hasUsableIgnorePattern(): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return preg_match($this->ignorepattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Counts the methods declared directly in the class body, excluding those
     * whose name matches $ignorepattern.
     *
     * A method's innermost condition is the scope that owns it, so comparing
     * it to the class pointer keeps out the methods of a nested anonymous
     * class and any function declared inside a method body. Closures and
     * arrow functions carry their own tokens (T_CLOSURE, T_FN) and are never
     * found by this search.
     *
     * A method left nameless mid-edit is skipped: there is no name to test
     * against the pattern, so counting it would be a guess either way.
     */
    private function countMethods(File $phpcsFile, int $classPtr, int $opener, int $closer): int
    {
        $tokens = $phpcsFile->getTokens();
        $count = 0;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            $name = $phpcsFile->getDeclarationName($ptr);

            if ($name === null || preg_match($this->ignorepattern, $name) === 1) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
