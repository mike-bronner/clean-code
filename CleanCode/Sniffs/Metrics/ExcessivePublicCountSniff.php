<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Reports a class or trait that declares too many public methods and
 * attributes — PHPMD's CodeSize/ExcessivePublicCount rule.
 *
 * A large public surface means more effort is required to test the type
 * thoroughly, and usually says the type should be broken up.
 *
 * The threshold is PHPMD's own `minimum` property, default 45, and it is
 * inclusive: PHPMD reports when the count is *greater than or equal to*
 * `minimum` (`if ($cis < $threshold) { return; }` in PHPMD's rule class), so
 * exactly 45 public members is already a violation. See
 * docs/phpmd/codesize-excessivepubliccount.md for the empirical evidence and
 * for every point on which this sniff deliberately diverges from PHPMD.
 *
 * Only members declared directly by the type are counted; a nested anonymous
 * class is its own scope and is counted separately, never folded into the type
 * that encloses it.
 */
class ExcessivePublicCountSniff implements Sniff
{
    /**
     * The public-member count at which a type is reported. Inclusive — a type
     * with exactly this many public members is already a violation, matching
     * PHPMD's `minimum` property of the same name and default.
     *
     * Untyped so that a ruleset supplying it as XML (`<property name="minimum"
     * value="30"/>`, always a string) sets it without a TypeError; it is cast
     * where it is read.
     *
     * @var int|string
     */
    public $minimum = 45;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        // Interfaces and enums are absent deliberately, matching PHPMD: its
        // rule class is declared `implements ClassAware, TraitAware`, and
        // PDepend's ClassLevelAnalyzer::visitInterface() is an empty method
        // carrying the comment "we don't want interface metrics".
        return [T_CLASS, T_ANON_CLASS, T_TRAIT];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // An unterminated declaration leaves PHPCS with no scope to walk. The
        // file is already a parse error; say nothing rather than guess.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $minimum = (int) $this->minimum;
        $count = $this->countPublicMembers($phpcsFile, $stackPtr);

        if ($count < $minimum) {
            return;
        }

        $phpcsFile->addError(
            'The %s has %s public methods and attributes.'
                . ' Consider reducing the number of public items to less than %s',
            $stackPtr,
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $count, $minimum]
        );
    }

    /**
     * Counts the public methods and public properties the type at $stackPtr
     * declares itself.
     */
    private function countPublicMembers(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $count = 0;

        for ($ptr = ($tokens[$stackPtr]['scope_opener'] + 1); $ptr < $closer; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if ($code !== T_FUNCTION && $code !== T_VARIABLE) {
                continue;
            }

            // Everything nested deeper than this type's own body — a method's
            // local variables, an anonymous class's members, a closure's
            // parameters — belongs to the innermost scope holding it, not here.
            if (array_key_last($tokens[$ptr]['conditions']) !== $stackPtr) {
                continue;
            }

            // A parameter list is parenthesised, not scoped, so a promoted or
            // plain parameter still reports this type as its innermost
            // condition. Property defaults are constant expressions and can
            // hold no variable, so any parenthesised variable here is a
            // parameter; the promoted ones are counted with their method.
            if ($code === T_VARIABLE && isset($tokens[$ptr]['nested_parenthesis']) === true) {
                continue;
            }

            $count += $code === T_FUNCTION
                ? $this->countPublicMethodAndPromotedProperties($phpcsFile, $ptr)
                : (int) ($phpcsFile->getMemberProperties($ptr)['scope'] === 'public');
        }

        return $count;
    }

    /**
     * The method at $methodPtr itself when public, plus every public property
     * it promotes. Promotion is legal only in a constructor, so no name check
     * is needed: no other method can carry a promoted parameter.
     */
    private function countPublicMethodAndPromotedProperties(File $phpcsFile, int $methodPtr): int
    {
        $count = (int) ($phpcsFile->getMethodProperties($methodPtr)['scope'] === 'public');

        foreach ($phpcsFile->getMethodParameters($methodPtr) as $parameter) {
            if (($parameter['property_visibility'] ?? null) === 'public') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * How the violation message names the type — `class Foo`, `trait Bar`, or
     * `anonymous class`, which has no name to report.
     */
    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_ANON_CLASS) {
            return 'anonymous class';
        }

        $kind = $tokens[$stackPtr]['code'] === T_TRAIT ? 'trait' : 'class';

        return $kind . ' ' . $phpcsFile->getDeclarationName($stackPtr);
    }
}
