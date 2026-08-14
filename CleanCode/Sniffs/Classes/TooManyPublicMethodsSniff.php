<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a class that declares more public methods than the configured
 * threshold.
 *
 * Replicates PHPMD's CodeSize TooManyPublicMethods rule
 * (docs/phpmd/codesize-toomanypublicmethods.md, #83). A class with too many
 * public methods carries too many responsibilities; the fix is to split it into
 * more fine-grained objects.
 *
 * PHPMD's rule reads PDepend's `npm` metric, then counts the class's public
 * methods whose names the `ignorepattern` does not match, and reports when that
 * count is *strictly greater* than `maxmethods`. The npm pre-check is only a
 * short circuit — it can never fire on its own, because the counted total is
 * always <= npm — so the effective rule is the count alone. This sniff
 * implements that count directly:
 *
 * - Only `T_CLASS` is registered. PHPMD's rule implements `ClassAware` and
 *   nothing else, so PDepend never hands it an interface, a trait, an enum, or
 *   an anonymous class. Those four token types are separate from `T_CLASS` in
 *   PHP_CodeSniffer, so registering `T_CLASS` alone reproduces that scope
 *   exactly rather than by an explicit exclusion.
 * - Only methods the class declares *itself* count. Inherited methods and
 *   methods imported from a trait are both invisible to PDepend's
 *   `ASTClass::getMethods()`, which is what keeps this rule inside one file for
 *   both tools.
 * - A method nested one level deeper — a named function declared inside a
 *   method body, or a method of an anonymous class returned from one — belongs
 *   to that inner scope, not to the class. The ownership test compares the
 *   *innermost* enclosing scope against the class token rather than searching
 *   for any enclosing class, so neither shape leaks into the count.
 * - Visibility follows PHP's own default: a method declared without a modifier
 *   is public, and PDepend agrees, so both tools count it.
 *
 * Every one of those decisions was checked against a live PHPMD 2.15.0 run over
 * the fixtures in tests/fixtures/TooManyPublicMethodsSniff/, and the sniff
 * reports exactly what PHPMD reports on them — no shape is lost, and none is
 * added. See the doc for the evidence table.
 *
 * Detection only, matching PHPMD. Splitting a class is a design change with no
 * mechanical rewrite, so no violation is offered to the fixer.
 */
class TooManyPublicMethodsSniff implements Sniff
{
    /**
     * PHPMD's own default threshold, held as a constant so the property default
     * and the empty-value fallback below cannot drift apart.
     */
    private const DEFAULT_MAX_METHODS = 10;

    /**
     * PHPMD's own default ignore pattern, kept beside the threshold for the
     * same reason.
     */
    private const DEFAULT_IGNORE_PATTERN = '(^(set|get|is|has|with))i';

    /**
     * The public-method count above which a class is reported. Spelled as PHPMD
     * spells it, and compared as PHPMD compares it: a class holding exactly
     * this many public methods is silent, and one holding a single method more
     * is reported.
     *
     * Nullable because PHP_CodeSniffer turns an empty `<property>` value into
     * `null` before it assigns it, and a non-nullable declaration would make a
     * consumer's `<property name="maxmethods" value=""/>` a fatal TypeError
     * during ruleset parsing rather than a configuration mistake. An empty
     * value falls back to PHPMD's default, which keeps reporting; treating it
     * as "no threshold" would switch the rule off silently.
     */
    public ?int $maxmethods = self::DEFAULT_MAX_METHODS;

    /**
     * A PCRE; a method whose name matches it is left out of the count.
     *
     * The default is the value PHPMD 2.15.0's own `rulesets/codesize.xml` ships
     * for this rule, copied verbatim — including its bracket delimiters — so a
     * project's existing PHPMD configuration transfers across unchanged and an
     * unconfigured run of each tool reports the same classes. Note that
     * phpmd.org's rule page still documents the older `(^(set|get))i`; the
     * shipped ruleset is what PHPMD actually runs, and it is what this default
     * tracks. A project that wants the narrower documented behaviour sets the
     * property explicitly — see the doc.
     *
     * Nullable for the same reason as `maxmethods`. Here an empty value means
     * "exempt nothing", so every public method counts — again the direction
     * that reports more rather than less. PHPMD's own XML cannot express that:
     * an empty `<property>` value leaves its default pattern in place.
     */
    public ?string $ignorepattern = self::DEFAULT_IGNORE_PATTERN;

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
        $tokens = $phpcsFile->getTokens();

        // A class cut short mid-edit has no brace pair to scan between.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $threshold = $this->maxmethods ?? self::DEFAULT_MAX_METHODS;
        $count = $this->countPublicMethods($phpcsFile, $stackPtr);

        if ($count <= $threshold) {
            return;
        }

        $name = (string) $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'The class %s has %s public methods. Consider refactoring %s to keep the number of '
                . 'public methods under %s '
                . '(see docs/phpmd/codesize-toomanypublicmethods.md)',
            $stackPtr,
            'Found',
            [$name, $count, $name, $threshold]
        );
    }

    /**
     * The number of public methods the class declares itself, less the ones the
     * ignore pattern exempts.
     */
    private function countPublicMethods(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $pointer = $tokens[$stackPtr]['scope_opener'];
        $count = 0;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $closer)) !== false) {
            if ($this->isDeclaredBy($phpcsFile, $pointer, $stackPtr) === false) {
                continue;
            }

            if ($phpcsFile->getMethodProperties($pointer)['scope'] !== 'public') {
                continue;
            }

            if ($this->isIgnoredName($phpcsFile->getDeclarationName($pointer)) === true) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    /**
     * Whether the declaration is a method of *this* class rather than something
     * written inside one of its methods.
     *
     * The class has to be the innermost enclosing scope, not merely an
     * enclosing one. A named function declared in a method body, and a method
     * of an anonymous class returned from a method, both still carry the outer
     * class among their conditions; each is excluded here because the last
     * condition is the enclosing method or the anonymous class instead. PHPMD
     * draws the same line — PDepend files both under the inner scope.
     */
    private function isDeclaredBy(File $phpcsFile, int $methodPtr, int $stackPtr): bool
    {
        $conditions = $phpcsFile->getTokens()[$methodPtr]['conditions'] ?? [];

        return array_key_last($conditions) === $stackPtr;
    }

    /**
     * Whether the configured ignore pattern exempts this method name.
     *
     * PHPMD treats an empty pattern as "exempt nothing", and compares
     * `preg_match()` strictly against 1 — so a pattern PHP cannot compile
     * exempts nothing either, and a configuration mistake over-reports rather
     * than switching the rule off silently. Both behaviours are reproduced
     * here, and an unset (null) pattern joins them; matching more loosely than
     * PHPMD would exempt a class PHPMD still reports, which is the one
     * direction that puts `phpmd` back in the pipeline.
     */
    private function isIgnoredName(?string $name): bool
    {
        $pattern = trim((string) $this->ignorepattern);

        return $name !== null
            && $pattern !== ''
            && preg_match($pattern, $name) === 1;
    }
}
