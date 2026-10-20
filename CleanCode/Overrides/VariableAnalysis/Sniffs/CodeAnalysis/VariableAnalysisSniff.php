<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Overrides\VariableAnalysis\Sniffs\CodeAnalysis;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use ReflectionFunction;
use VariableAnalysis\Lib\Helpers;
use VariableAnalysis\Sniffs\CodeAnalysis\VariableAnalysisSniff as UpstreamVariableAnalysisSniff;

class VariableAnalysisSniff extends UpstreamVariableAnalysisSniff
{
    protected function processVariableAsPassByReferenceFunctionCall(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ): bool {
        $argument = $this->namedArgumentAt($phpcsFile, $stackPtr);

        if ($argument === null) {
            return parent::processVariableAsPassByReferenceFunctionCall($phpcsFile, $stackPtr, $varName, $currScope);
        }

        if (
            $this->isWholeArgument($phpcsFile, $argument, $stackPtr) === false
            || $this->namesReferenceParameter($phpcsFile, $stackPtr, $argument['name']) === false
        ) {
            return false;
        }

        $this->markVariableAssignment($varName, $stackPtr, $currScope);
        $this->markVariableRead($varName, $stackPtr, $currScope);

        return true;
    }

    protected function markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $stackPtr, $currScope): void
    {
        $scope = $this->arrowFunctionDefining($phpcsFile, $varName, $stackPtr, $currScope) ?? $currScope;

        parent::markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $stackPtr, $scope);
    }

    private function namedArgumentAt(File $phpcsFile, int $stackPtr): ?array
    {
        foreach (Helpers::findFunctionCallArguments($phpcsFile, $stackPtr) as $argument) {
            if (
                $stackPtr >= $argument['start']
                && $stackPtr <= $argument['end']
            ) {
                return isset($argument['name']) === true ? $argument : null;
            }
        }

        return null;
    }

    private function isWholeArgument(File $phpcsFile, array $argument, int $stackPtr): bool
    {
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $argument['start'], ($argument['end'] + 1), true);
        $last = $phpcsFile->findPrevious(Tokens::$emptyTokens, $argument['end'], ($argument['start'] - 1), true);

        return $first === $stackPtr
            && $last === $stackPtr;
    }

    private function namesReferenceParameter(File $phpcsFile, int $stackPtr, string $name): bool
    {
        $functionPtr = Helpers::findFunctionCall($phpcsFile, $stackPtr);
        $calls = new FunctionCalls;

        if (
            $functionPtr === null
            || $calls->isGlobalFunctionCall($phpcsFile, $functionPtr) === false
        ) {
            return false;
        }

        $function = $calls->calleeName($phpcsFile, $functionPtr);

        if (function_exists($function) === false) {
            return false;
        }

        foreach ((new ReflectionFunction($function))->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter->isPassedByReference();
            }
        }

        return false;
    }

    private function arrowFunctionDefining(File $phpcsFile, string $varName, int $stackPtr, int $enclosingScope): ?int
    {
        $arrowFunction = Helpers::getContainingArrowFunctionIndex($phpcsFile, $stackPtr, $enclosingScope);

        while ($arrowFunction !== null) {
            if (in_array($varName, Helpers::getVariablesDefinedByArrowFunction($phpcsFile, $arrowFunction), true)) {
                return $arrowFunction;
            }

            $arrowFunction = Helpers::getContainingArrowFunctionIndex($phpcsFile, $arrowFunction, $enclosingScope);
        }

        return null;
    }
}
