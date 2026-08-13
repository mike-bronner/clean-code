<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's CodeSize/ExcessiveClassLength rule (PHPMD\Rule\Design\LongClass).
 *
 * Every behaviour below was read off PHPMD 2.15.0 + PDepend 2.16.2 and then
 * confirmed against live runs; the numbers are quoted in
 * docs/phpmd/codesize-excessiveclasslength.md.
 *
 * - Classes only. PHPMD's rule is ClassAware, so interfaces, traits, and enums
 *   are never reported however long they get, and an anonymous class is not
 *   reported in its own right. Registering T_CLASS gives that for free:
 *   PHP_CodeSniffer tokenises those four constructs as T_INTERFACE, T_TRAIT,
 *   T_ENUM, and T_ANON_CLASS.
 * - The violation is reported at the *declaration start* — the first of the
 *   `abstract`/`final`/`readonly` modifiers when there is one, otherwise the
 *   `class` keyword. A doc block or an attribute above it is not part of the
 *   class for either the report position or the count.
 * - The threshold is inclusive. PHPMD returns early on `$loc < $threshold`, so
 *   a class whose length *equals* `minimum` is already a violation.
 * - `ignoreWhitespace` mirrors PHPMD's `ignore-whitespace` property, which does
 *   not merely drop blank lines: it swaps the metric from PDepend's `loc` to
 *   its `eloc`. See executableLines() for what that entails.
 */
class ExcessiveClassLengthSniff implements Sniff
{
    /**
     * The class length at (or above) which a class is reported. PHPMD's own
     * default for the `minimum` property.
     */
    public int $minimum = 1000;

    /**
     * PHPMD's `ignore-whitespace` property: false counts every physical line of
     * the class, true switches to executable lines only.
     */
    public bool $ignoreWhitespace = false;

    /**
     * Class modifiers that may precede the `class` keyword. PDepend takes the
     * class's start line from the first of these, not from `class` itself.
     */
    private const DECLARATION_MODIFIERS = [
        T_ABSTRACT,
        T_FINAL,
        T_READONLY,
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
        $tokens = $phpcsFile->getTokens();

        // A class the tokenizer never found a closing brace for — an
        // unterminated class body — carries no scope, so it has no end line to
        // measure from and nothing this rule can honestly report.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $declarationPtr = $this->declarationStart($phpcsFile, $stackPtr);

        $length = $this->ignoreWhitespace === true
            ? $this->executableLines($phpcsFile, $stackPtr)
            : $this->physicalLines($phpcsFile, $stackPtr, $declarationPtr);

        if ($length < $this->minimum) {
            return;
        }

        $phpcsFile->addError(
            'The class %s has %s lines of code. Current threshold is %s. Avoid really long classes.',
            $declarationPtr,
            'TooLong',
            [
                $phpcsFile->getDeclarationName($stackPtr),
                $length,
                $this->minimum,
            ]
        );
    }

    /**
     * The token the class declaration starts at: the outermost modifier
     * preceding `class`, or `class` itself when it carries none.
     *
     * PDepend's ASTClass::getStartLine() is the line of that first modifier, so
     * `abstract` sitting on its own line above `class Foo` both moves the
     * report up a line and adds one to the length.
     */
    private function declarationStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $stackPtr;

        while (true) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

            if (
                $previous === false
                || in_array($tokens[$previous]['code'], self::DECLARATION_MODIFIERS, true) === false
            ) {
                return $start;
            }

            $start = $previous;
        }
    }

    /**
     * PDepend's `loc` metric for a class: every physical line from the
     * declaration start through the closing brace, inclusive. Blank lines and
     * comment lines count — that is what PHPMD's `ignore-whitespace: false`
     * default means.
     */
    private function physicalLines(File $phpcsFile, int $stackPtr, int $declarationPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];

        return ($tokens[$closer]['line'] - $tokens[$declarationPtr]['line'] + 1);
    }

    /**
     * PDepend's `eloc` metric for a class, which is a far narrower thing than
     * "loc minus the blank lines": NodeLocAnalyzer::visitClass() sums the eloc
     * of the class's own methods and nothing else, and a method's eloc is the
     * number of distinct lines carrying a non-comment token from its opening
     * brace onwards.
     *
     * Three consequences fall out of that, all confirmed against a live PHPMD
     * 2.15.0 run: constants, properties, method signature lines, and the class
     * braces themselves contribute nothing; an abstract method contributes
     * nothing, because it has no body; and the body of an anonymous class
     * declared inside a method *is* counted, because those lines lie inside the
     * enclosing method's own token range.
     */
    private function executableLines(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $total = 0;

        for ($i = ($tokens[$stackPtr]['scope_opener'] + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_FUNCTION) {
                continue;
            }

            // Methods of a nested anonymous class, and functions declared inside
            // a method body, belong to that inner scope rather than to this
            // class. Their lines still reach the total through the enclosing
            // method, exactly as they do in PDepend.
            if (array_key_last($tokens[$i]['conditions']) !== $stackPtr) {
                continue;
            }

            // An abstract method has no scope to measure.
            if (isset($tokens[$i]['scope_opener'], $tokens[$i]['scope_closer']) === false) {
                continue;
            }

            $total += $this->bodyLines($phpcsFile, $tokens[$i]['scope_opener'], $tokens[$i]['scope_closer']);
        }

        return $total;
    }

    /**
     * The number of distinct lines between two scope markers that carry at
     * least one token which is neither whitespace nor comment.
     */
    private function bodyLines(File $phpcsFile, int $opener, int $closer): int
    {
        $tokens = $phpcsFile->getTokens();
        $lines = [];

        for ($i = $opener; $i <= $closer; $i++) {
            if (
                $tokens[$i]['code'] === T_WHITESPACE
                || isset(Tokens::$commentTokens[$tokens[$i]['code']]) === true
            ) {
                continue;
            }

            $lines[$tokens[$i]['line']] = true;
        }

        return count($lines);
    }
}
