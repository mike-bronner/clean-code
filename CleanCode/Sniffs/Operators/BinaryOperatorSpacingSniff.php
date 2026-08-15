<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces exactly one space around each *binary* operator — the assignment,
 * comparison and arithmetic/bitwise spacing the "Arrays: Operator spacing &
 * line breaks" (#35) and "Operators: Active" (#62) standards ask for.
 *
 * This is Squiz.WhiteSpace.OperatorSpacing with one behaviour corrected, and it
 * replaces that sniff in the master ruleset. Everything it reports, every
 * message code it emits, and both of its configurable properties are the
 * parent's, unchanged.
 *
 * The correction closes an oscillation between two fixers. A `+`/`-` is only a
 * binary operator when a value precedes it; otherwise it is a unary sign, which
 * the "Operators: Passive" standard (#64) requires to sit flush against its
 * operand. The parent decides "is a value to my left?" from a fixed set of
 * preceding tokens, and that set omits four contexts in which no value can
 * possibly precede the sign:
 *
 * - `T_SEMICOLON` — the sign opens a new statement (`$a = 1; -$b;`).
 * - `T_OPEN_TAG` / `T_OPEN_TAG_WITH_ECHO` — the sign opens a PHP block
 *   (`<?php -$x;`, `<?= -$total ?>`).
 * - `T_ASPERAND` — the sign is the operand of error control (`@-$a`).
 *
 * In each, the parent reads the sign as binary and *inserts* a space, while
 * CleanCode.WhiteSpace.PassiveOperatorSpacing reads it as unary and *removes*
 * one. The two fixers then undo each other on every pass and
 * `phpcbf --standard=rules.xml` gives up on the whole file (exit 2) — for
 * ordinary template code such as `<?= -$total ?>`.
 *
 * Rather than have the passive standard cede those contexts, this sniff yields
 * them: it declines the four, the passive sniff owns them, and each sign is
 * governed by exactly one fixer. That resolution is Mike's call on #64 —
 * resolve the collision at the ruleset level instead of narrowing what
 * "Operators: Passive" promises.
 *
 * The four are not a hand-maintained list to keep in step by eye.
 * tests/Standards/BinaryOperatorSpacingTest.php derives the divergence between
 * the two sniffs' operand detection directly from both classes and asserts this
 * sniff declines every token in it, so a new context appearing on either side
 * fails the suite instead of shipping as a fresh oscillation.
 */
class BinaryOperatorSpacingSniff extends OperatorSpacingSniff
{
    /**
     * The contexts in which a `+`/`-` cannot be binary, but which the parent's
     * own operand detection does not recognise. Ceded to
     * CleanCode.WhiteSpace.PassiveOperatorSpacing, which owns unary signs.
     *
     * @var array<int|string, int|string>
     */
    public const UNARY_SIGN_PRECEDERS = [
        T_ASPERAND => T_ASPERAND,
        T_OPEN_TAG => T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO => T_OPEN_TAG_WITH_ECHO,
        T_SEMICOLON => T_SEMICOLON,
    ];

    /**
     * Declines the `+`/`-` signs the parent would misread as binary, and defers
     * to the parent for every other decision it makes.
     *
     * @param int $stackPtr
     *
     * @return bool
     */
    protected function isOperator(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_PLUS || $code === T_MINUS) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

            // Nothing at all precedes the sign, so nothing can be its left
            // operand. The parent reads a false pointer as position 0 instead.
            if ($previous === false) {
                return false;
            }

            if (isset(self::UNARY_SIGN_PRECEDERS[$tokens[$previous]['code']]) === true) {
                return false;
            }
        }

        return parent::isOperator($phpcsFile, $stackPtr);
    }
}
