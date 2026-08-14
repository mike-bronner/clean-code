<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a method or function whose line count reaches a configured threshold.
 *
 * Replicates PHPMD's CodeSize ExcessiveMethodLength rule
 * (docs/phpmd/codesize-excessivemethodlength.md, #91). A long method is nearly
 * always a method doing several jobs, and very often the residue of copy-paste;
 * the fix is to extract helpers until each one states a single intent.
 *
 * PHPMD implements the rule in PHPMD\Rule\Design\LongMethod, which reads a
 * line-count metric PDepend computed and reports when it is *greater than or
 * equal to* the threshold:
 *
 *     $loc = ignore-whitespace ? $node->getMetric('eloc') : $node->getMetric('loc');
 *     if ($loc < $threshold) { return; }
 *
 * so a declaration measuring exactly `minimum` lines is already a violation.
 * The two metrics behind it, from PDepend's NodeLocAnalyzer::visitMethod():
 *
 * - `loc` — `getEndLine() - getStartLine() + 1`, the raw span of the
 *   declaration. Blank lines and comments inside it count; the docblock and any
 *   attributes above it do not, because they sit outside the node's span.
 * - `eloc` — the number of distinct lines, from the body's opening brace to the
 *   closing brace, that carry at least one non-comment token. Blank lines and
 *   comment-only lines drop out, and so does the signature whenever the brace
 *   is on a line of its own.
 *
 * Both are reproduced here from PHPCS's token stream rather than approximated;
 * `docs/phpmd/codesize-excessivemethodlength.md` records the live PHPMD 2.15.0
 * run that every fixture in tests/fixtures/ExcessiveMethodLengthSniff/ was
 * cross-checked against.
 *
 * Scope decisions, each matching PHPMD rather than being chosen freely:
 *
 * - Named methods *and* named functions are measured. PHPMD's rule class
 *   implements both MethodAware and FunctionAware, so despite the rule's name a
 *   long plain function is reported too.
 * - Closures and arrow functions are not registered. PDepend models neither as
 *   a function node: a closure written inside a method is already part of that
 *   method's span, and one written at file scope is a PHPMD blind spot.
 *   Registering T_CLOSURE here would report declarations PHPMD never reports
 *   and double-count the nested case.
 * - Attributes are excluded from the span but modifiers are included, because
 *   PDepend's node starts at the first modifier keyword. `public` on its own
 *   line ahead of `function` therefore adds a line, and `#[Attribute]` does not.
 * - Detection only. Shortening a method means extracting helpers and naming
 *   them, which is a design decision with no mechanical rewrite. PHPMD offers
 *   no fix either.
 *
 * Both properties carry PHPMD's own defaults, so the sniff out of the box
 * reports exactly what `phpmd`'s codesize ruleset reports.
 */
class ExcessiveMethodLengthSniff implements Sniff
{
    /**
     * The line count at which a declaration is reported — PHPMD's `minimum`
     * property, and its default of 100. The comparison is `>=`, as PHPMD's is,
     * so a declaration of exactly this many lines is already too long.
     *
     * Left untyped on purpose. PHPCS hands a `<property>` value over as the
     * string it read from the XML and only ever converts the literals `true`
     * and `false` (Ruleset::setSniffProperty()), so an `int` declaration would
     * lean on PHP's coercion of a numeric string and raise a TypeError on
     * anything else. normalizedMinimum() casts instead, which keeps a
     * misconfigured threshold from silently disabling the rule.
     *
     * @var int|string
     */
    public $minimum = 100;

    /**
     * PHPMD's `ignore-whitespace` property, spelled the way PHPCS requires
     * (a property name, not an XML attribute name), and defaulted to PHPMD's
     * `false`.
     *
     * The name understates what it does, in PHPMD as here: it does not subtract
     * whitespace from `loc`, it swaps the metric for `eloc`, which drops
     * comment lines and the signature as well as blank ones. Replicated as-is,
     * because a project moving its PHPMD configuration across has to get the
     * same answer from the same value.
     */
    public bool $ignoreWhitespace = false;

    /**
     * Keywords PDepend counts as part of the declaration. A method node starts
     * at the first of these, which is why `public` sitting on its own line
     * lengthens the measured span. T_READONLY is absent because PHP does not
     * accept it on a method.
     */
    private const MODIFIER_TOKENS = [
        T_ABSTRACT,
        T_FINAL,
        T_PRIVATE,
        T_PROTECTED,
        T_PUBLIC,
        T_STATIC,
    ];

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
        $minimum = $this->normalizedMinimum();
        $start = $this->declarationStart($phpcsFile, $stackPtr);

        $length = $this->ignoreWhitespace === true
            ? $this->executableLines($phpcsFile, $stackPtr)
            : $this->spannedLines($phpcsFile, $stackPtr, $start);

        if ($length < $minimum) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'The %s %s() has %s lines of code, and the threshold is %s; a declaration '
                . 'this long is doing several jobs, so extract each one into its own '
                . 'method (see docs/phpmd/codesize-excessivemethodlength.md)',
            $start,
            'Found',
            [
                $this->describe($phpcsFile, $stackPtr),
                $name ?? 'anonymous',
                $length,
                $minimum,
            ]
        );
    }

    /**
     * The configured threshold as an integer.
     *
     * A value PHPCS could not have come from a sane `<property>` — a non-numeric
     * string, or a number below one — falls back to PHPMD's default of 100
     * rather than to zero, which is the fail-closed direction: casting "abc" to
     * 0 would make every declaration in the codebase a violation, and a
     * negative threshold would do the same, so neither is honoured.
     */
    private function normalizedMinimum(): int
    {
        $minimum = is_numeric($this->minimum) === true ? (int) $this->minimum : 0;

        return $minimum < 1 ? 100 : $minimum;
    }

    /**
     * The token PDepend would treat as the declaration's first, and the one the
     * diagnostic is reported against: the earliest modifier keyword attached to
     * it, or the `function` keyword when it carries none.
     *
     * The walk steps back over whitespace and comments as well as modifiers, so
     * that a comment written between `public` and `function` still leaves the
     * span starting at `public`. It stops at the first token that is none of
     * the three — the enclosing brace, the previous statement's semicolon, or
     * an attribute's closing bracket — which is also what bounds it. Skipping
     * comments cannot drag the start up into a docblock, because the walk only
     * ever remembers a *modifier* it actually saw: a docblock above a bare
     * `function f()` is stepped over and nothing is recorded, leaving the
     * `function` keyword as the start, which is where PDepend puts it too.
     */
    private function declarationStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $found = $stackPtr;

        for ($i = $stackPtr - 1; $i >= 0; $i--) {
            $code = $tokens[$i]['code'];

            if (in_array($code, self::MODIFIER_TOKENS, true) === true) {
                $found = $i;

                continue;
            }

            if ($code === T_WHITESPACE || isset(Tokens::$commentTokens[$code]) === true) {
                continue;
            }

            break;
        }

        return $found;
    }

    /**
     * PDepend's `loc`: the inclusive line span of the declaration, blank lines
     * and comments included.
     *
     * The last line is the body's closing brace, or — for an abstract or
     * interface method, which has no body — the semicolon that ends the
     * signature. The search for that semicolon starts after the parameter list
     * and refuses to cross an opening brace, so a declaration truncated
     * mid-edit cannot borrow a semicolon from some later statement and be
     * measured as hundreds of lines long. When nothing usable is found the
     * declaration measures a single line and is never reported, which is the
     * fail-closed direction for a fragment that is not really a declaration.
     */
    private function spannedLines(File $phpcsFile, int $stackPtr, int $start): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$stackPtr]['scope_closer'] ?? $this->signatureTerminator($phpcsFile, $stackPtr);

        return $tokens[$end]['line'] - $tokens[$start]['line'] + 1;
    }

    /**
     * The semicolon ending a bodyless declaration, or the `function` keyword
     * when there is no such semicolon to be found.
     */
    private function signatureTerminator(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $afterParameters = $tokens[$stackPtr]['parenthesis_closer'] ?? $stackPtr;

        $terminator = $phpcsFile->findNext(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET],
            $afterParameters + 1
        );

        if ($terminator === false || $tokens[$terminator]['code'] !== T_SEMICOLON) {
            return $stackPtr;
        }

        return $terminator;
    }

    /**
     * PDepend's `eloc`: how many distinct lines between the body's braces
     * (inclusive) carry at least one non-comment token.
     *
     * PDepend has no whitespace tokens to begin with and counts a comment
     * token's lines separately, so a blank line and a comment-only line both
     * fall out; a line holding code *and* a trailing comment stays in, because
     * the code token put it there. An abstract or interface method has no body
     * and PDepend scores it zero.
     *
     * Marking each token's own line is enough to cover the constructs that span
     * several of them. PHPCS's tokenizer splits a multi-line string, a heredoc,
     * a nowdoc and inline HTML into one token per line, so a four-line string
     * literal arrives as four tokens carrying four line numbers rather than as
     * one token to be measured; no token outside the comment and whitespace
     * types this loop already skips holds a newline anywhere but at its end.
     */
    private function executableLines(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return 0;
        }

        $lines = [];

        for ($i = $opener; $i <= $closer; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_WHITESPACE || isset(Tokens::$commentTokens[$code]) === true) {
                continue;
            }

            $lines[$tokens[$i]['line']] = true;
        }

        return count($lines);
    }

    /**
     * Whether to call the declaration a method or a function, matching the word
     * PHPMD puts in its own message. PDepend calls anything inside a
     * class-like scope a method, including a trait's and an enum's.
     */
    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach ($conditions as $code) {
            if (in_array($code, Tokens::$ooScopeTokens, true) === true) {
                return 'method';
            }
        }

        return 'function';
    }
}
