<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags function and method declarations whose name is shorter than a
 * configured minimum.
 *
 * Replicates PHPMD's Naming/ShortMethodName (#111) —
 * docs/phpmd/naming-shortmethodname.md. No PHPCS, Generic, Squiz or Slevomat
 * sniff measures declaration-name *length*: none of the twelve
 * NamingConventions sniffs php_codesniffer ships looks at how long a name is
 * — they judge casing, affixes, or whether a method name matches its class —
 * and Slevomat ships no name-length rule either. A custom sniff is
 * therefore the only option, and this one mirrors PHPMD's own rule class
 * (PHPMD\Rule\Naming\ShortMethodName) decision for decision:
 *
 * - PHPMD's rule implements both MethodAware and FunctionAware, so global
 *   functions count as well as methods. This sniff registers T_FUNCTION,
 *   which PHPCS assigns to exactly those two — closures are T_CLOSURE and
 *   arrow functions are T_FN, so neither reaches this sniff, matching PHPMD
 *   (an unnamed declaration has no name to measure).
 * - The comparison is `strlen($name) >= $threshold`, so a name of exactly
 *   `minimum` characters passes and one character less fails.
 * - Byte length, not character length. PHPMD calls strlen(), so a two-letter
 *   name written with multi-byte letters measures three or more bytes and
 *   passes in both tools. Using mb_strlen() here would flag names PHPMD
 *   accepts.
 * - Exceptions are matched with a strict in_array() against
 *   explode(',', $exceptions), with no per-entry trim — again PHPMD's own
 *   code. "a, b" therefore exempts `a` and ` b`, not `b`. Trimming here would
 *   exempt names PHPMD still reports, which is the unsafe direction for a
 *   ruleset whose purpose is to make running phpmd unnecessary.
 * - No magic-method carve-out, because PHPMD has none. It needs none at the
 *   default threshold — the shortest magic method, __get, is five characters
 *   — but a raised `minimum` reports __get in both tools alike.
 *
 * Detection only, matching PHPMD: renaming a function or method means
 * updating every call site, every interface it implements and anything
 * reaching it by string, so there is no safe mechanical rewrite.
 *
 * Two shapes PHPMD cannot see are reported here: methods of an anonymous
 * class, and a function declared inside a function or method body. Both are
 * invisible to pdepend, so phpmd stays silent on them whatever the threshold.
 * Reporting them is the safe direction — this ruleset replaces phpmd for the
 * rule, so catching more than phpmd never leaves a violation unreported. Both
 * are pinned by tests/fixtures/ShortMethodNameSniff/divergences.php.
 */
class ShortMethodNameSniff implements Sniff
{
    /**
     * PHPMD's own default for the `minimum` property, and the value this
     * sniff falls back to when the configured one is unusable.
     */
    public const DEFAULT_MINIMUM = 3;

    /**
     * Shortest acceptable declaration name. Configurable from a ruleset via
     * <property name="minimum" value="…"/>, matching PHPMD's property name.
     *
     * Left untyped because PHPCS hands ruleset properties over as raw strings
     * and turns an empty value into null; minimum() normalises both.
     *
     * @var int|string|null
     */
    public $minimum = self::DEFAULT_MINIMUM;

    /**
     * Comma-separated declaration names that are never reported, however
     * short. Configurable from a ruleset via
     * <property name="exceptions" value="…"/>, matching PHPMD's property name
     * and its comma-separated format.
     *
     * @var string|null
     */
    public $exceptions = '';

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
        $namePtr = $this->namePointer($phpcsFile, $stackPtr);

        if ($namePtr === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $name = $tokens[$namePtr]['content'];
        $minimum = $this->minimum();

        if (strlen($name) >= $minimum) {
            return;
        }

        if (in_array($name, $this->exceptions(), true) === true) {
            return;
        }

        $phpcsFile->addError(
            'Avoid using short method names like %s(). The configured minimum method name length is %s.',
            $namePtr,
            'TooShort',
            [$name, $minimum]
        );
    }

    /**
     * The token holding the declared name, or null when there is none to
     * measure.
     *
     * The name is the first non-empty token after the `function` keyword that
     * is not the return-by-reference ampersand. PHPCS re-tokenises a
     * semi-reserved word used as a method name (`list`, `for`, `do`) to
     * T_STRING, so no token type has to be enumerated here.
     *
     * The search stops at the parameter list's opening parenthesis. That
     * bound is the search's natural range rather than a second guard: once
     * the declaration is known to have a parameter list, the name is always
     * the first token found, so no input distinguishes the bounded search
     * from an unbounded one. The guard below is what does the work.
     */
    private function namePointer(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        // Absent on a declaration PHP could not parse a parameter list for.
        // Refusing to guess is the closed behaviour: without the boundary
        // there is nothing to prove the token found is the name at all.
        if (isset($tokens[$stackPtr]['parenthesis_opener']) === false) {
            return null;
        }

        $skipped = Tokens::$emptyTokens;
        $skipped[T_BITWISE_AND] = T_BITWISE_AND;

        $namePtr = $phpcsFile->findNext(
            $skipped,
            ($stackPtr + 1),
            $tokens[$stackPtr]['parenthesis_opener'],
            true
        );

        return $namePtr === false ? null : $namePtr;
    }

    /**
     * The configured minimum, normalised to a usable positive integer.
     *
     * A ruleset property arrives as a string, and PHPCS turns an empty one
     * into null. Anything that is not a positive integer falls back to
     * PHPMD's default rather than being cast — (int) null is 0, and a
     * threshold of 0 passes every name, silently disabling the rule instead
     * of reporting the misconfiguration.
     */
    private function minimum(): int
    {
        $configured = filter_var($this->minimum, FILTER_VALIDATE_INT);

        return $configured === false || $configured < 1 ? self::DEFAULT_MINIMUM : $configured;
    }

    /**
     * The configured exceptions, split PHPMD's way.
     *
     * PHPMD explodes the raw property on commas and compares strictly,
     * without trimming the parts; this reproduces that exactly. Casting first
     * keeps explode() off a null property, which PHP 8.1 deprecates.
     *
     * @return array<int, string>
     */
    private function exceptions(): array
    {
        return explode(',', (string) $this->exceptions);
    }
}
