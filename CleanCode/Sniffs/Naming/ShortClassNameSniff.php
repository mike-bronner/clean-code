<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a class-like declaration whose name is shorter than the configured
 * minimum.
 *
 * Replicates PHPMD's Naming ShortClassName rule
 * (docs/phpmd/naming-shortclassname.md, #103). A name too short to say what
 * the type is forces every reader to open the file to find out.
 *
 * The detection is PHPMD's, statement for statement: the declaration's
 * unqualified name is compared by *byte* length (`strlen()`, as PHPMD's own
 * rule does) against `minimum`, and a name on the `exceptions` list is let
 * through. Both properties are spelled exactly as PHPMD spells them and are
 * matched the way PHPMD matches them, so a project's existing PHPMD
 * configuration for this rule transfers verbatim.
 *
 * Scope decisions:
 *
 * - Classes, interfaces, traits, and enums are all registered. PHPMD's rule
 *   declares ClassAware, InterfaceAware, TraitAware, and EnumAware, so it
 *   speaks about all four despite the rule name and the phpmd.org description
 *   naming only the first two. Registering the same four is what keeps this
 *   sniff from being looser than the tool it replaces.
 * - An anonymous class is never reported. PHPCS gives it its own T_ANON_CLASS
 *   token, which this sniff does not register, and PDepend hands PHPMD a
 *   generated name well over any sensible threshold — so neither tool reports
 *   one.
 * - The name is the unqualified one. PHPCS's getDeclarationName() returns the
 *   declared name without its namespace, which is what PDepend's getName()
 *   gives PHPMD; a short namespace over a long class name is not a violation
 *   for either tool.
 * - Detection only, matching PHPMD. Renaming a type rewrites every reference
 *   to it across the codebase — and, under PSR-4, the file it lives in. That
 *   is a refactor, not a mechanical fix, and PHPMD offers no fix either.
 * - Error severity, matching PHPMD, where a violation fails the run. A warning
 *   would leave `phpcs` exiting 0 on a short class name, so `phpmd` would
 *   still have to run separately for this rule.
 */
class ShortClassNameSniff implements Sniff
{
    /**
     * The reporting threshold: a name shorter than this many bytes is
     * reported. 3 is what PHPMD's naming.xml ships.
     *
     * Deliberately typed `int` rather than left untyped. A ruleset supplies
     * the value as a string ("4"), which PHP_CodeSniffer assigns from
     * coercive-mode code, so a numeric string is converted here and a
     * non-numeric one fails loudly instead of silently comparing as 0.
     */
    public int $minimum = 3;

    /**
     * Comma-separated names that are exempt however short they are — PHPMD's
     * own property description gives `Log,URL,FTP` as the example. Matching is
     * case-sensitive, as PHPMD's `array_flip()` lookup is, and each entry is
     * trimmed with empties dropped, as PHPMD's Strings::splitToList() does.
     *
     * Empty by default, exactly as PHPMD's naming.xml ships it: the names in
     * the property description are an example of what a project might exempt,
     * not a default. Nothing is exempt out of the box.
     */
    public string $exceptions = '';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A keyword with no name after it is what PHPCS hands a sniff for a
        // file caught mid-edit. There is no name to measure, so it passes over.
        if ($name === null || strlen($name) >= $this->minimum) {
            return;
        }

        if (in_array($name, $this->exceptionList(), true) === true) {
            return;
        }

        $phpcsFile->addError(
            'Avoid classes with short names like %s. Configured minimum length is %s.',
            $stackPtr,
            'TooShort',
            [$name, $this->minimum]
        );
    }

    /**
     * The configured exceptions as a list, trimmed, with empty entries
     * dropped — PHPMD's Strings::splitToList() behaviour, so a list written
     * for PHPMD is read the same way here.
     *
     * @return array<int, string>
     */
    private function exceptionList(): array
    {
        return array_filter(
            array_map('trim', explode(',', $this->exceptions)),
            static fn (string $exception): bool => $exception !== ''
        );
    }
}
