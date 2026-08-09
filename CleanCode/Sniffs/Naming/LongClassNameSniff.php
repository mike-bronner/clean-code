<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Replicates PHPMD's Naming/LongClassName rule.
 *
 * PHPMD flags a class, interface, trait, or enum whose declared name is longer
 * than a configured maximum — an excessively long name usually hints at a type
 * that does too much. No PHPCS, Generic, or Slevomat sniff measures a type
 * name's length, so this custom sniff carries the rule; see
 * docs/phpmd/naming-longclassname.md for the mapping.
 *
 * The detection mirrors PHPMD 2.15.0's own implementation
 * (PHPMD\Rule\Naming\LongClassName plus PHPMD\Utility\Strings) exactly:
 *
 * - The rule is `ClassAware`, `InterfaceAware`, `TraitAware`, and `EnumAware`,
 *   so all four declaration keywords are registered here.
 * - The name measured is the *declared* (short) name. A long namespace does not
 *   contribute, and neither does a long reference to a type declared elsewhere.
 * - Length is a byte count (PHP's `strlen`), not a character count, because
 *   `Strings::lengthWithoutPrefixesAndSuffixes()` uses `strlen`.
 * - At most one suffix and at most one prefix are subtracted, each being the
 *   *first* entry of its configured list that matches — PHPMD breaks out of
 *   both loops on the first hit, so a longer later entry never wins.
 * - Both subtractions are taken from the length of the *original* name, so a
 *   prefix and a suffix that overlap are each subtracted in full. That is
 *   PHPMD's behaviour and is reproduced rather than corrected.
 * - A violation is raised only when the resulting length is strictly greater
 *   than the maximum: at the threshold exactly, PHPMD stays silent.
 *
 * Anonymous classes are not reported. PHPCS tokenises `new class {}` as
 * `T_ANON_CLASS`, which this sniff does not register, and PHPMD likewise never
 * applies the rule to a type that has no declared name.
 *
 * Detection only, matching PHPMD: renaming a type means rewriting every
 * reference to it across the codebase, which a single-file, token-based fixer
 * cannot do safely.
 */
class LongClassNameSniff implements Sniff
{
    /**
     * The name-length reporting threshold. A name longer than this is flagged;
     * a name of exactly this length is not. PHPMD's `maximum` property, same
     * default.
     */
    public int $maximum = 40;

    /**
     * Comma-separated prefixes that do not count towards the name length. Only
     * the first entry that matches is subtracted. PHPMD's `subtract-prefixes`
     * property, same default (none).
     */
    public string $subtractPrefixes = '';

    /**
     * Comma-separated suffixes that do not count towards the name length. Only
     * the first entry that matches is subtracted. PHPMD's `subtract-suffixes`
     * property, same default (none).
     */
    public string $subtractSuffixes = '';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A truncated declaration — a `class` keyword with no name after it —
        // has no length to measure, so say nothing rather than guess.
        if ($name === null) {
            return;
        }

        $length = $this->lengthWithoutPrefixesAndSuffixes($name);

        if ($length <= $this->maximum) {
            return;
        }

        $phpcsFile->addError(
            'Name %s is %s characters long; keep it to %s or fewer',
            $stackPtr,
            'TooLong',
            [$name, $length, $this->maximum]
        );
    }

    /**
     * The byte length of $name less at most one configured prefix and at most
     * one configured suffix, each the first matching entry of its list. Both
     * are subtracted from the original name's length, so an overlapping prefix
     * and suffix are each counted in full — a faithful port of PHPMD's
     * Strings::lengthWithoutPrefixesAndSuffixes().
     */
    private function lengthWithoutPrefixesAndSuffixes(string $name): int
    {
        $length = strlen($name);

        foreach ($this->splitToList($this->subtractSuffixes) as $suffix) {
            if (substr($name, -strlen($suffix)) === $suffix) {
                $length -= strlen($suffix);

                break;
            }
        }

        foreach ($this->splitToList($this->subtractPrefixes) as $prefix) {
            if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                $length -= strlen($prefix);

                break;
            }
        }

        return $length;
    }

    /**
     * Splits a comma-separated property value into trimmed, non-empty entries,
     * as PHPMD's Strings::splitToList() does. Dropping the empty entries
     * matters on the prefix side: `strncmp($name, '', 0)` is 0 for every name,
     * so an empty prefix would match first, subtract nothing, and — the loop
     * stopping at the first match — strand every real prefix behind it. An
     * empty suffix is inert either way, since `substr($name, -0)` returns the
     * whole name rather than the empty string.
     *
     * @return array<int, string>
     */
    private function splitToList(string $value): array
    {
        return array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $entry): bool => $entry !== ''
        );
    }
}
