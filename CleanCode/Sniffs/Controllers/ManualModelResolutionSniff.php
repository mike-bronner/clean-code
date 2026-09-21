<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Controllers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ManualModelResolutionSniff implements Sniff
{
    private const RESOLUTION_METHODS = [
        'find',
        'findorfail',
    ];

    private const MAGIC_PREFIX = '__';

    private const ROUTED_MAGIC_METHOD = '__invoke';

    private const CLASS_SCOPES = [
        T_CLASS,
        T_ANON_CLASS,
    ];

    private const FUNCTION_SCOPES = [
        T_FUNCTION,
        T_CLOSURE,
    ];

    private const NO_POINTER = -1;

    private const ARGUMENT_ENDS = [
        T_COMMA,
        T_CLOSE_PARENTHESIS,
    ];

    private const MESSAGE = <<<MESSAGE
        Model %s resolved by hand from %s, a parameter of %s(); type-hint the model on the
        action signature instead and let route-model binding resolve it
        (see docs/standards/controllers-route-model-binding.md)
        MESSAGE;

    public function register(): array
    {
        return [T_DOUBLE_COLON];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $methodPtr = $this->resolutionMethodPointer($phpcsFile, $stackPtr);
        $argumentPtr = $this->firstArgumentVariablePointer($phpcsFile, $methodPtr);
        $receiverPtr = $this->modelNamePointer($phpcsFile, $stackPtr);
        $actionPtr = $this->controllerActionPointer($phpcsFile, $stackPtr);

        match (true) {
            $methodPtr === null => null,
            $receiverPtr === null => null,
            $actionPtr === null => null,
            $this->isOwnParameter($phpcsFile, $argumentPtr, $actionPtr) === false => null,
            default => $phpcsFile->addWarning(
                $this->message(),
                $methodPtr,
                'Found',
                [
                    $this->contentOf($phpcsFile, $receiverPtr),
                    $this->contentOf($phpcsFile, (int) $argumentPtr),
                    $phpcsFile->getDeclarationName($actionPtr),
                ]
            ),
        };
    }

    private function resolutionMethodPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $methodPtr = $this->nextSignificant($phpcsFile, $stackPtr);
        $openPtr = $this->nextSignificant($phpcsFile, $methodPtr);

        return match (true) {
            $this->isToken($phpcsFile, $methodPtr, T_STRING) === false => null,
            $this->isResolutionMethod($phpcsFile, $methodPtr) === false => null,
            $this->isToken($phpcsFile, $openPtr, T_OPEN_PARENTHESIS) === false => null,
            default => $methodPtr,
        };
    }

    private function isResolutionMethod(File $phpcsFile, ?int $methodPtr): bool
    {
        return match ($methodPtr) {
            null => false,
            default => in_array(
                strtolower($this->contentOf($phpcsFile, $methodPtr)),
                self::RESOLUTION_METHODS,
                true
            ),
        };
    }

    private function modelNamePointer(File $phpcsFile, int $stackPtr): ?int
    {
        $receiverPtr = $this->previousSignificant($phpcsFile, $stackPtr);

        return match ($this->isToken($phpcsFile, $receiverPtr, T_STRING)) {
            false => null,
            default => $receiverPtr,
        };
    }

    private function firstArgumentVariablePointer(File $phpcsFile, ?int $methodPtr): ?int
    {
        $openPtr = $this->nextSignificant($phpcsFile, $methodPtr);
        $labelPtr = $this->nextSignificant($phpcsFile, $openPtr);
        $argumentPtr = $this->pastArgumentLabel($phpcsFile, $labelPtr);
        $afterPtr = $this->nextSignificant($phpcsFile, $argumentPtr);

        return match (true) {
            $this->isToken($phpcsFile, $argumentPtr, T_VARIABLE) === false => null,
            $this->isToken($phpcsFile, $afterPtr, self::ARGUMENT_ENDS) === false => null,
            default => $argumentPtr,
        };
    }

    private function pastArgumentLabel(File $phpcsFile, ?int $argumentPtr): ?int
    {
        return match ($this->isToken($phpcsFile, $argumentPtr, T_PARAM_NAME)) {
            false => $argumentPtr,
            default => $this->nextSignificant(
                $phpcsFile,
                $this->nextSignificant($phpcsFile, $argumentPtr)
            ),
        };
    }

    private function isOwnParameter(File $phpcsFile, ?int $argumentPtr, int $actionPtr): bool
    {
        $parameters = array_column($phpcsFile->getMethodParameters($actionPtr), 'name');

        return match ($argumentPtr) {
            null => false,
            default => in_array($this->contentOf($phpcsFile, $argumentPtr), $parameters, true),
        };
    }

    private function controllerActionPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $classPtr = $this->conditionPointer($phpcsFile, $stackPtr, T_CLASS);
        $functionPtr = $this->conditionPointer($phpcsFile, $stackPtr, T_FUNCTION);

        return match (true) {
            $classPtr === null => null,
            $this->isControllerClass($phpcsFile, $classPtr) === false => null,
            $functionPtr === null => null,
            $this->isMethod($phpcsFile, $functionPtr) === false => null,
            $this->isPublic($phpcsFile, $functionPtr) === false => null,
            $this->isRoutable($phpcsFile, $functionPtr) === false => null,
            default => $functionPtr,
        };
    }

    private function isControllerClass(File $phpcsFile, int $classPtr): bool
    {
        return str_ends_with((string) $phpcsFile->getDeclarationName($classPtr), 'Controller');
    }

    private function isPublic(File $phpcsFile, int $functionPtr): bool
    {
        return $phpcsFile->getMethodProperties($functionPtr)['scope'] === 'public';
    }

    private function isRoutable(File $phpcsFile, int $functionPtr): bool
    {
        $name = strtolower((string) $phpcsFile->getDeclarationName($functionPtr));

        return match (true) {
            $name === self::ROUTED_MAGIC_METHOD => true,
            default => str_starts_with($name, self::MAGIC_PREFIX) === false,
        };
    }

    private function isMethod(File $phpcsFile, int $functionPtr): bool
    {
        $classPtr = $this->innermostConditionPointer($phpcsFile, $functionPtr, self::CLASS_SCOPES);
        $enclosingPtr = $this->innermostConditionPointer(
            $phpcsFile,
            $functionPtr,
            self::FUNCTION_SCOPES
        );

        return match ($classPtr) {
            null => false,
            default => $classPtr > ($enclosingPtr ?? self::NO_POINTER),
        };
    }

    private function innermostConditionPointer(File $phpcsFile, int $stackPtr, array $types): ?int
    {
        $innermost = self::NO_POINTER;

        foreach ($types as $type) {
            $innermost = max(
                $innermost,
                $this->conditionPointer($phpcsFile, $stackPtr, $type) ?? self::NO_POINTER
            );
        }

        return match ($innermost) {
            self::NO_POINTER => null,
            default => $innermost,
        };
    }

    private function conditionPointer(File $phpcsFile, int $stackPtr, int|string $type): ?int
    {
        // The assignment is hoisted out of the match subject rather than
        // written inline: CleanCode/ruleset.xml now reports an assignment in a condition
        // (#79), and a match subject is one of the conditions it reads.
        $pointer = $phpcsFile->getCondition($stackPtr, $type, false);

        return match ($pointer) {
            false => null,
            default => $pointer,
        };
    }

    private function nextSignificant(File $phpcsFile, ?int $stackPtr): ?int
    {
        return match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true)
            ),
        };
    }

    private function previousSignificant(File $phpcsFile, ?int $stackPtr): ?int
    {
        return match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true)
            ),
        };
    }

    private function isToken(File $phpcsFile, ?int $stackPtr, array|int|string $types): bool
    {
        return match ($stackPtr) {
            null => false,
            default => $phpcsFile->findNext($types, $stackPtr, ($stackPtr + 1)) !== false,
        };
    }

    private function contentOf(File $phpcsFile, int $stackPtr): string
    {
        return $phpcsFile->getTokensAsString($stackPtr, 1);
    }

    private function message(): string
    {
        return str_replace("\n", ' ', self::MESSAGE);
    }

    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
        };
    }
}
