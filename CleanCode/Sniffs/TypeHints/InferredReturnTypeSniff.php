<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Helpers\Declarations;
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
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $closer = $this->signatureCloser($phpcsFile, $stackPtr);

        if (
            $closer === null
            || $this->isSkippable($phpcsFile, $stackPtr, $closer) === true
        ) {
            return;
        }

        $type = (new ReturnTypeInference)->infer($phpcsFile, $stackPtr);

        if ($type === null) {
            return;
        }

        $this->report($phpcsFile, $stackPtr, $closer, $type);
    }

    private function report(File $phpcsFile, int $stackPtr, int $closer, string $type): void
    {
        $name = (new Declarations)->name($phpcsFile, $stackPtr) ?? 'Closure';
        $fix = $phpcsFile->addFixableError(
                "%s has no return type hint; \"%s\" follows from its own declaration",
                $stackPtr,
                'Inferable',
                [$name, $type]
            );

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer
            ->addContent($closer, ": {$type}");
    }

    private function isSkippable(File $phpcsFile, int $stackPtr, int $closer): bool
    {
        if ($this->isMagicMethod($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->hasReturnType($phpcsFile, $closer) === true) {
            return true;
        }

        return FunctionHelper::findReturnAnnotation($phpcsFile, $stackPtr) !== null;
    }

    private function isMagicMethod(File $phpcsFile, int $stackPtr): bool
    {
        $name = (new Declarations)->name($phpcsFile, $stackPtr);

        return $name !== null
            && str_starts_with(strtolower($name), '__');
    }

    private function hasReturnType(File $phpcsFile, int $closer): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        return $next !== false
            && $phpcsFile->getTokens()[$next]['code'] === T_COLON;
    }

    private function signatureCloser(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if ($closer === null) {
            return null;
        }

        $use = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        if (
            $use === false
            || $tokens[$use]['code'] !== T_USE
        ) {
            return $closer;
        }

        $opener = $phpcsFile->findNext(Tokens::$emptyTokens, ($use + 1), null, true);

        if (
            $opener === false
            || $tokens[$opener]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        return $tokens[$opener]['parenthesis_closer'] ?? null;
    }
}
