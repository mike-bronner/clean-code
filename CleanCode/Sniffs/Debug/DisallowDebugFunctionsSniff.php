<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Debug;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids calls to debug/dump functions that must never be committed.
 *
 * This is the example sniff wired end-to-end through the package scaffold:
 * registered via the CleanCode standard (CleanCode/ruleset.xml, referenced by
 * the master rules.xml) and covered by the PHPCS unit-test harness in
 * CleanCode/Tests/Debug/. Use it as the template for new sniffs — see
 * CONTRIBUTING.md.
 */
class DisallowDebugFunctionsSniff implements Sniff
{
    /**
     * Debug functions that must not appear in committed code.
     */
    private const DEBUG_FUNCTIONS = [
        'dd',
        'dump',
        'ray',
        'var_dump',
    ];

    /**
     * Tokens that, when directly preceding the function name, mean this is
     * not a global function call (method call, static call, declaration, …).
     */
    private const NON_FUNCTION_CALL_PRECEDERS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $content = strtolower($tokens[$stackPtr]['content']);

        if (in_array($content, self::DEBUG_FUNCTIONS, true) === false) {
            return;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $prev !== false
            && in_array($tokens[$prev]['code'], self::NON_FUNCTION_CALL_PRECEDERS, true) === true
        ) {
            return;
        }

        $phpcsFile->addError(
            'Debug function %s() must not be committed',
            $stackPtr,
            'Found',
            [$tokens[$stackPtr]['content']]
        );
    }
}
