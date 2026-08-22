<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Debug;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Forbids calls to debug/dump functions that must never be committed.
 *
 * This is the example sniff wired end-to-end through the package scaffold:
 * registered via the CleanCode standard (CleanCode/ruleset.xml, referenced by
 * the master rules.xml), fixtured in tests/fixtures/DisallowDebugFunctionsSniff/
 * and covered by tests/Standards/DisallowDebugFunctionsTest.php. Use it as the
 * template for new sniffs — see CONTRIBUTING.md.
 *
 * Note what it does *not* carry: telling a real call to a global function apart
 * from a same-named method, declaration, class, attribute, or imported symbol
 * is FunctionCalls' job, shared with every other sniff that asks the same
 * question. All this sniff owns is the list of names.
 */
class DisallowDebugFunctionsSniff implements Sniff
{
    /**
     * Debug functions that must not appear in committed code.
     */
    private const DEBUG_FUNCTIONS = [
        'dd',
        'debug_print_backtrace',
        'debug_zval_dump',
        'dump',
        'print_r',
        'ray',
        'var_dump',
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

        if (FunctionCalls::isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
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
