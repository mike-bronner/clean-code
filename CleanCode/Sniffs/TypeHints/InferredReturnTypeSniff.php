<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\ReturnTypeInference;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\FunctionHelper;

class InferredReturnTypeSniff implements Sniff
{
    public function register(): array
    {
        return [
            T_FUNCTION,
            T_CLOSURE,
        ];
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint -- interface-mandated, see CONTRIBUTING.md
    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isSkippable($phpcsFile, $stackPtr) === true) {
            return;
        }

        $type = (new ReturnTypeInference())->infer($phpcsFile, $stackPtr);

        if ($type === null) {
            return;
        }

        $this->report($phpcsFile, $stackPtr, $type);
    }

    private function report(File $phpcsFile, int $stackPtr, string $type): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr) ?? 'Closure';
        $fix = $phpcsFile->addFixableError(
            "%s has no return type hint; \"%s\" follows from its own declaration",
            $stackPtr,
            'Inferable',
            [$name, $type]
        );

        if ($fix === false) {
            return;
        }

        $closer = $this->parameterListCloser($phpcsFile, $stackPtr);

        if ($closer === null) {
            return;
        }

        $phpcsFile->fixer
            ->addContent($closer, ": {$type}");
    }

    private function isSkippable(File $phpcsFile, int $stackPtr): bool
    {
        if ($this->isMagicMethod($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->hasReturnType($phpcsFile, $stackPtr) === true) {
            return true;
        }

        return FunctionHelper::findReturnAnnotation($phpcsFile, $stackPtr) !== null;
    }

    private function isMagicMethod(File $phpcsFile, int $stackPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        return $name !== null
            && str_starts_with(strtolower($name), '__');
    }

    private function hasReturnType(File $phpcsFile, int $stackPtr): bool
    {
        $closer = $this->parameterListCloser($phpcsFile, $stackPtr);

        if ($closer === null) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        return $next !== false
            && $phpcsFile->getTokens()[$next]['code'] === T_COLON;
    }

    private function parameterListCloser(File $phpcsFile, int $stackPtr): ?int
    {
        return $phpcsFile->getTokens()[$stackPtr]['parenthesis_closer'] ?? null;
    }
}
