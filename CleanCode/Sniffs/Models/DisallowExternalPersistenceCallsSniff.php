<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Helpers\ClassNames;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

class DisallowExternalPersistenceCallsSniff implements Sniff
{
    private const MEMBER_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const OWN_CLASS_KEYWORDS = [
        T_PARENT,
        T_SELF,
        T_STATIC,
    ];

    private const CLASS_NAMES = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
    ];

    public array $persistenceMethods = [
        'create',
        'delete',
        'save',
        'update',
    ];

    public array $modelNamespaces = [
        'App\\Models',
    ];

    public function register(): array
    {
        return self::MEMBER_OPERATORS;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $methodPtr === false
            || $tokens[$methodPtr]['code'] !== T_STRING
        ) {
            return;
        }

        $method = strtolower($tokens[$methodPtr]['content']);

        if (in_array($method, array_map('strtolower', $this->persistenceMethods), true) === false) {
            return;
        }

        $afterMethod = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if (
            $afterMethod === false
            || $tokens[$afterMethod]['code'] !== T_OPEN_PARENTHESIS
            || $this->isExempt($phpcsFile, $stackPtr)
        ) {
            return;
        }

        $phpcsFile->addWarning(
                'Generic Eloquent CRUD method %s() called on a chain not rooted at '
                    . '$this, static, self or parent; '
                    . 'persistence belongs inside the model behind a descriptive method '
                    . '(see resources/boost/skills/clean-code/references/'
                    . 'models-persistence-methods-repository-pattern.md)',
                $methodPtr,
                'Found',
                [$tokens[$methodPtr]['content']]
            );
    }

    private function isExempt(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $receiverPtr = $this->previousCodePtr($phpcsFile, $operatorPtr);

        if (
            $tokens[$operatorPtr]['code'] === T_DOUBLE_COLON
            && $receiverPtr !== false
            && in_array($tokens[$receiverPtr]['code'], self::CLASS_NAMES, true)
        ) {
            return $this->isOutsideModelClass($phpcsFile, $receiverPtr);
        }

        return $this->isRootedInOwnClass($phpcsFile, $operatorPtr);
    }

    private function isOutsideModelClass(File $phpcsFile, int $namePtr): bool
    {
        $written = $phpcsFile->getTokens()[$namePtr]['content'];
        $resolved = (new ClassNames)->resolve($phpcsFile, $written, $namePtr);
        $className = strtolower(ltrim($resolved, '\\'));

        return $className === $this->enclosingClassName($phpcsFile, $namePtr)
            || $this->isInModelNamespace($className) === false;
    }

    private function isInModelNamespace(string $className): bool
    {
        $prefixes = array_map(
                static fn (string $namespace): string => strtolower(trim($namespace, '\\')) . '\\',
                $this->modelNamespaces
            );

        $matches = array_filter(
                $prefixes,
                static fn (string $prefix): bool => str_starts_with($className, $prefix)
            );

        return $matches !== [];
    }

    private function enclosingClassName(File $phpcsFile, int $stackPtr): string
    {
        $classPtr = (int) $phpcsFile->getCondition($stackPtr, T_CLASS);

        return match ($classPtr) {
            0 => '',
            default => $this->qualifiedName($phpcsFile, $classPtr),
        };
    }

    private function qualifiedName(File $phpcsFile, int $classPtr): string
    {
        $namespace = (string) NamespaceHelper::findCurrentNamespaceName($phpcsFile, $classPtr);
        $className = (string) $phpcsFile->getDeclarationName($classPtr);

        return strtolower(ltrim("{$namespace}\\{$className}", '\\'));
    }

    private function isRootedInOwnClass(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $receiverPtr = $this->previousCodePtr($phpcsFile, $operatorPtr);

        while (
            $receiverPtr !== false
            && $tokens[$receiverPtr]['code'] === T_CLOSE_PARENTHESIS
        ) {
            $operatorPtr = $this->methodCallOperator($phpcsFile, $receiverPtr);

            if ($operatorPtr === false) {
                return false;
            }

            $receiverPtr = $this->previousCodePtr($phpcsFile, $operatorPtr);
        }

        return $receiverPtr !== false
            && $this->isOwnClassReference($tokens[$receiverPtr]);
    }

    private function methodCallOperator(File $phpcsFile, int $closerPtr): int|false
    {
        $tokens = $phpcsFile->getTokens();
        $openerPtr = $tokens[$closerPtr]['parenthesis_opener'] ?? 0;
        $namePtr = $this->previousCodePtr($phpcsFile, $openerPtr);

        if (
            $namePtr === false
            || $tokens[$namePtr]['code'] !== T_STRING
        ) {
            return false;
        }

        $operatorPtr = $this->previousCodePtr($phpcsFile, $namePtr);

        if (
            $operatorPtr === false
            || in_array($tokens[$operatorPtr]['code'], self::MEMBER_OPERATORS, true) === false
        ) {
            return false;
        }

        return $operatorPtr;
    }

    private function previousCodePtr(File $phpcsFile, int $stackPtr): int|false
    {
        return $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
    }

    private function isOwnClassReference(array $token): bool
    {
        return in_array($token['code'], self::OWN_CLASS_KEYWORDS, true)
            || (
                $token['code'] === T_VARIABLE
                && $token['content'] === '$this'
            );
    }
}
