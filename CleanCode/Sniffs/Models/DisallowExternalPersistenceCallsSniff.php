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

    private const FUNCTION_TOKENS = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    private const UNREAD_RECEIVER_PRECEDERS = [
        T_DOLLAR,
        T_DOUBLE_COLON,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    private const MEMBER_READ_OPERATORS = [
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    private const QUERY_BUILDER_FACADES = [
        'db',
        'illuminate\\support\\facades\\db',
    ];

    private const NON_CLASS_TYPES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'parent',
        'self',
        'static',
        'string',
        'true',
        'void',
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
                    . '$this, static, self, parent or a class the file states is not a model; '
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

        return $this->isRootedOutsideModels($phpcsFile, $operatorPtr);
    }

    private function isOutsideModelClass(File $phpcsFile, int $namePtr): bool
    {
        $written = $phpcsFile->getTokens()[$namePtr]['content'];
        $className = $this->resolvedClassName($phpcsFile, $written, $namePtr);

        return $this->isOwnOrNonModelClass($phpcsFile, $className, $namePtr);
    }

    private function resolvedClassName(File $phpcsFile, string $written, int $stackPtr): string
    {
        return strtolower(ltrim((new ClassNames)->resolve($phpcsFile, $written, $stackPtr), '\\'));
    }

    private function isOwnOrNonModelClass(File $phpcsFile, string $className, int $stackPtr): bool
    {
        return $className === $this->enclosingClassName($phpcsFile, $stackPtr)
            || $this->isInModelNamespace($className) === false;
    }

    private function isStatedNonModelClass(File $phpcsFile, string $written, int $stackPtr): bool
    {
        $className = $this->resolvedClassName($phpcsFile, $written, $stackPtr);
        $isStated = str_contains($written, '\\')
            || $className !== strtolower($written);

        return $isStated
            && in_array($className, self::QUERY_BUILDER_FACADES, true) === false
            && $this->isOwnOrNonModelClass($phpcsFile, $className, $stackPtr);
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

    private function isRootedOutsideModels(File $phpcsFile, int $operatorPtr): bool
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

        return match (true) {
            $receiverPtr === false => false,
            $this->isOwnClassReference($tokens[$receiverPtr]) => true,
            default => $this->isNonModelParameter($phpcsFile, $receiverPtr)
                || $this->isNonModelStaticRoot($phpcsFile, $operatorPtr, $receiverPtr),
        };
    }

    private function isNonModelStaticRoot(File $phpcsFile, int $operatorPtr, int $receiverPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $receiver = $tokens[$receiverPtr];

        return $tokens[$operatorPtr]['code'] === T_DOUBLE_COLON
            && in_array($receiver['code'], self::CLASS_NAMES, true)
            && $this->isStatedNonModelClass($phpcsFile, $receiver['content'], $receiverPtr);
    }

    private function isNonModelParameter(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $variable = $tokens[$variablePtr]['content'];
        $precederPtr = $this->previousCodePtr($phpcsFile, $variablePtr);
        $functionPtr = $this->enclosingFunctionPtr($phpcsFile, $variablePtr);

        if (
            $functionPtr === false
            || in_array($tokens[$precederPtr]['code'], self::UNREAD_RECEIVER_PRECEDERS, true)
            || $this->isOnlyReadForMembers($phpcsFile, $functionPtr, $variable) === false
        ) {
            return false;
        }

        $parameters = array_filter(
                $phpcsFile->getMethodParameters($functionPtr),
                static fn (array $parameter): bool => $parameter['name'] === $variable
            );

        return $parameters !== []
            && $this->isNonModelType($phpcsFile, reset($parameters));
    }

    private function enclosingFunctionPtr(File $phpcsFile, int $stackPtr): int|false
    {
        $tokens = $phpcsFile->getTokens();
        $functionPtr = $phpcsFile->findPrevious(self::FUNCTION_TOKENS, ($stackPtr - 1));

        while (
            $functionPtr !== false
            && ($tokens[$functionPtr]['scope_closer'] ?? 0) < $stackPtr
        ) {
            $functionPtr = $phpcsFile->findPrevious(self::FUNCTION_TOKENS, ($functionPtr - 1));
        }

        return $functionPtr;
    }

    private function isOnlyReadForMembers(File $phpcsFile, int $functionPtr, string $variable): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openerPtr = $tokens[$functionPtr]['scope_opener'];
        $closerPtr = $tokens[$functionPtr]['scope_closer'];
        $usePtr = $phpcsFile->findNext(T_VARIABLE, $openerPtr, $closerPtr, false, $variable);

        while ($usePtr !== false) {
            $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($usePtr + 1), null, true);
            $nextCode = $tokens[$nextPtr]['code'] ?? null;

            if (in_array($nextCode, self::MEMBER_READ_OPERATORS, true) === false) {
                return false;
            }

            $usePtr = $phpcsFile->findNext(T_VARIABLE, ($usePtr + 1), $closerPtr, false, $variable);
        }

        return true;
    }

    private function isNonModelType(File $phpcsFile, array $parameter): bool
    {
        $types = preg_split('/[|&()]/', $parameter['type_hint'], -1, PREG_SPLIT_NO_EMPTY);
        $typePtr = $parameter['token'];
        $classTypes = array_filter(
                $types,
                fn (string $type): bool => $this->isNonModelClassType($phpcsFile, $type, $typePtr)
            );

        return $types !== []
            && count($classTypes) === count($types)
            && $parameter['nullable_type'] === false
            && $parameter['variable_length'] === false
            && strtolower($parameter['default'] ?? '') !== 'null';
    }

    private function isNonModelClassType(File $phpcsFile, string $type, int $stackPtr): bool
    {
        return in_array(strtolower($type), self::NON_CLASS_TYPES, true) === false
            && $this->isStatedNonModelClass($phpcsFile, $type, $stackPtr);
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
