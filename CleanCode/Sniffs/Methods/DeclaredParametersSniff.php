<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Methods;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DeclaredParametersSniff implements Sniff
{
    private const DYNAMIC_ARGUMENT_FUNCTIONS = [
        'func_get_args',
        'func_get_arg',
        'func_num_args',
    ];

    private const MAGIC_METHODS = [
        '__construct',
        '__destruct',
        '__call',
        '__callstatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
        '__tostring',
        '__invoke',
        '__set_state',
        '__clone',
        '__debuginfo',
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
        $name = $tokens[$stackPtr]['content'];

        if (in_array(strtolower($name), self::DYNAMIC_ARGUMENT_FUNCTIONS, true) === false) {
            return;
        }

        if ($functionCalls->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->isInsideMagicMethod($phpcsFile, $stackPtr) === true) {
            return;
        }

        $phpcsFile->addError(
            '%s() is not allowed; declare the parameter list instead of reading arguments dynamically',
            $stackPtr,
            'DynamicArguments',
            [$name]
        );
    }

    private function isInsideMagicMethod(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $functionPtr = null;

        // The innermost declared scope owns the parameter list the call reads.
        // A closure declares its own, so it never inherits the exemption of a
        // magic method it happens to sit in.
        foreach (array_reverse($tokens[$stackPtr]['conditions'], true) as $scopePtr => $code) {
            if ($code === T_CLOSURE) {
                return false;
            }

            if ($code === T_FUNCTION) {
                $functionPtr = $scopePtr;

                break;
            }
        }

        if ($functionPtr === null) {
            return false;
        }

        if ($this->isInsideArrowFunction($phpcsFile, $functionPtr, $stackPtr) === true) {
            return false;
        }

        return $this->isMagicMethod($phpcsFile, $functionPtr);
    }

    private function isInsideArrowFunction(File $phpcsFile, int $searchFrom, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $arrowPtr = $phpcsFile->findPrevious(T_FN, ($stackPtr - 1), $searchFrom);

        while ($arrowPtr !== false) {
            if (($tokens[$arrowPtr]['scope_closer'] ?? 0) >= $stackPtr) {
                return true;
            }

            $arrowPtr = $phpcsFile->findPrevious(T_FN, ($arrowPtr - 1), $searchFrom);
        }

        return false;
    }

    private function isMagicMethod(File $phpcsFile, int $functionPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'];

        if (
            $conditions === []
            || in_array(end($conditions), Tokens::$ooScopeTokens, true) === false
        ) {
            return false;
        }

        $name = $phpcsFile->getDeclarationName($functionPtr);

        return $name !== null && in_array(strtolower($name), self::MAGIC_METHODS, true);
    }
}
