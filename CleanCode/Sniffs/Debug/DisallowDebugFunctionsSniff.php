<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Debug;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowDebugFunctionsSniff implements Sniff
{
    private const DEBUG_FUNCTIONS = [
        'dd',
        'debug_print_backtrace',
        'debug_zval_dump',
        'dump',
        'print_r',
        'ray',
        'var_dump',
    ];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls()
    ) {
    }

    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $functionCalls = $this->functionCalls;

        $tokens = $phpcsFile->getTokens();
        $content = strtolower($tokens[$stackPtr]['content']);

        if (in_array($content, self::DEBUG_FUNCTIONS, true) === false) {
            return;
        }

        if ($functionCalls->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
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
