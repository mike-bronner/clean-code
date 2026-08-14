<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Forbids `exit`/`die` expressions inside a function or method body.
 *
 * Replicates PHPMD's Design/ExitExpression rule — see
 * docs/phpmd/design-exitexpression.md for the mapping and the two shapes on
 * which this sniff is deliberately stricter.
 *
 * PHPMD reports an exit expression only where it sits inside a named function
 * or method, which is why this sniff scopes itself the same way rather than
 * flagging every `exit` in the file: PHPMD's own remedy is to "relocate exit
 * expressions to startup scripts", and a startup script's exit lives at file
 * scope. Flagging file scope would forbid the fix the rule asks for.
 *
 * Detection-only, matching PHPMD: replacing a termination with a return value
 * or an exception is a judgement about what the code was meant to do, so there
 * is no safe mechanical rewrite.
 *
 * A method may legally carry `exit`/`die` as its name (PHP 7.0+), but no guard
 * against that is needed here: `exit` and `die` are context-sensitive keywords
 * to PHPCS, so the tokenizer demotes them to T_STRING after `function`, `::`,
 * and the object operators before any sniff runs. Only the construct itself is
 * ever T_EXIT. tests/fixtures/DisallowExitExpressionSniff/passing.php pins it.
 */
class DisallowExitExpressionSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_EXIT];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Named functions and methods both open a T_FUNCTION scope, and a
        // closure or arrow function nested inside one still carries it among
        // its conditions. A closure at file scope carries only T_CLOSURE, so
        // it is left alone — as PHPMD leaves it alone.
        if (in_array(T_FUNCTION, $tokens[$stackPtr]['conditions'], true) === false) {
            return;
        }

        $phpcsFile->addError(
            'Exit expression %s must not appear inside a function or method; '
                . 'relocate it to a startup script that returns an error code',
            $stackPtr,
            'Found',
            [$tokens[$stackPtr]['content']]
        );
    }
}
