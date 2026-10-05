<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Methods;

use MikeBronner\CleanCode\Helpers\Declarations;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoNullArgumentsSniff implements Sniff
{
    private const CODE = 'PositionalNull';

    private const MESSAGE = 'Do not pass null positionally into the optional '
        . "parameter \$%s; use the named argument \"%s: null\" instead";

    private const UNNAMEABLE_ARGUMENT = ' (cannot be fixed automatically: a later argument in this call'
        . ' cannot be named)';

    private const LATE_BOUND_CALL = ' (cannot be fixed automatically: this call is dispatched against the'
        . ' runtime class, which may override the method and rename the parameter)';

    private const BIND_EARLY = 'early';

    private const BIND_THIS = 'this';

    private const BIND_TRAIT_SELF = 'trait-self';

    private const BIND_STATIC = 'static';

    private const OPEN_BRACKETS = [
        T_OPEN_PARENTHESIS => true,
        T_OPEN_SQUARE_BRACKET => true,
        T_OPEN_SHORT_ARRAY => true,
        T_OPEN_CURLY_BRACKET => true,
    ];

    private const CLOSE_BRACKETS = [
        T_CLOSE_PARENTHESIS => true,
        T_CLOSE_SQUARE_BRACKET => true,
        T_CLOSE_SHORT_ARRAY => true,
        T_CLOSE_CURLY_BRACKET => true,
    ];

    private const CLASS_SCOPES = [
        T_CLASS => true,
        T_ANON_CLASS => true,
        T_TRAIT => true,
        T_ENUM => true,
        T_INTERFACE => true,
    ];

    public function register(): array
    {
        return [T_NULL];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $opener = $this->getCallOpener($phpcsFile, $stackPtr);

        if ($opener === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $arguments = $this->getArguments($phpcsFile, $opener, $tokens[$opener]['parenthesis_closer']);
        $index = $this->getBareNullArgumentIndex($arguments, $stackPtr);

        if ($index === null) {
            return;
        }

        $callee = $this->resolveCallee($phpcsFile, $opener);

        if ($callee === null) {
            return;
        }

        $parameters = $phpcsFile->getMethodParameters($callee['function']);
        $parameter = $parameters[$index] ?? null;

        if (
            $parameter === null
            || isset($parameter['default']) === false
        ) {
            return;
        }

        $name = ltrim($parameter['name'], '$');

        if ($this->isDispatchProvable($phpcsFile, $callee, $opener) === false) {
            $phpcsFile->addError(
                    self::MESSAGE . self::LATE_BOUND_CALL,
                    $stackPtr,
                    self::CODE,
                    [$name, $name]
                );

            return;
        }

        $names = $this->getNamesForFix($arguments, $index, $parameters);

        if ($names === null) {
            $phpcsFile->addError(
                    self::MESSAGE . self::UNNAMEABLE_ARGUMENT,
                    $stackPtr,
                    self::CODE,
                    [$name, $name]
                );

            return;
        }

        $fix = $phpcsFile->addFixableError(self::MESSAGE, $stackPtr, self::CODE, [$name, $name]);

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer
            ->beginChangeset();

        foreach ($names as $start => $parameterName) {
            $phpcsFile->fixer
                ->addContentBefore($start, "{$parameterName}: ");
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function getCallOpener(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['nested_parenthesis']) === false) {
            return null;
        }

        $nested = $tokens[$stackPtr]['nested_parenthesis'];
        end($nested);
        $opener = key($nested);

        if (
            $opener === null
            || isset($tokens[$opener]['parenthesis_owner']) === true
        ) {
            return null;
        }

        return isset($tokens[$opener]['parenthesis_closer']) === true ? $opener : null;
    }

    private function getArguments(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $arguments = [];
        $depth = 0;
        $start = null;
        $end = null;

        for ($i = $opener + 1; $i < $closer; $i++) {
            $code = $tokens[$i]['code'];

            if (isset(self::OPEN_BRACKETS[$code]) === true) {
                $depth++;
            }

            if (isset(self::CLOSE_BRACKETS[$code]) === true) {
                $depth--;
            }

            if (
                $code === T_COMMA
                && $depth === 0
            ) {
                if ($start !== null) {
                    $arguments[] = $this->describeArgument($tokens, $start, (int) $end);
                }

                $start = null;
                $end = null;

                continue;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $start ??= $i;
            $end = $i;
        }

        if ($start !== null) {
            $arguments[] = $this->describeArgument($tokens, $start, (int) $end);
        }

        return $arguments;
    }

    private function describeArgument(array $tokens, int $start, int $end): array
    {
        return [
            'start' => $start,
            'end' => $end,
            'named' => $tokens[$start]['code'] === T_PARAM_NAME,
            'spread' => $tokens[$start]['code'] === T_ELLIPSIS,
        ];
    }

    private function getBareNullArgumentIndex(array $arguments, int $stackPtr): ?int
    {
        foreach ($arguments as $index => $argument) {
            if ($argument['spread'] === true) {
                return null;
            }

            if ($argument['start'] !== $stackPtr) {
                continue;
            }

            return $argument['end'] === $stackPtr ? $index : null;
        }

        return null;
    }

    private function resolveCallee(File $phpcsFile, int $opener): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);

        if ($namePtr === false) {
            return null;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $namePtr - 1, null, true);
        $previous = $previousPtr === false ? null : $tokens[$previousPtr]['code'];
        $code = $tokens[$namePtr]['code'];

        if ($previous === T_NEW) {
            return $this->resolveConstructor($phpcsFile, $namePtr, $opener);
        }

        if ($code !== T_STRING) {
            return null;
        }

        $name = $tokens[$namePtr]['content'];

        if (
            isset($tokens[$namePtr]['nested_attributes']) === true
            && ($previous === T_ATTRIBUTE || $previous === T_COMMA)
        ) {
            return $this->resolveConstructor($phpcsFile, $namePtr, $opener);
        }

        if (
            $previous === T_OBJECT_OPERATOR
            || $previous === T_NULLSAFE_OBJECT_OPERATOR
        ) {
            return $this->resolveThisMethod($phpcsFile, (int) $previousPtr, $opener, $name);
        }

        if ($previous === T_DOUBLE_COLON) {
            $classPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previousPtr - 1, null, true);

            if ($classPtr === false) {
                return null;
            }

            $reference = $this->resolveClassReference($phpcsFile, $classPtr, $opener);

            return $reference === null ? null : $this->resolveMethod($phpcsFile, $reference, $name);
        }

        if (
            $previous === T_STRING
            || $previous === T_FUNCTION
        ) {
            return null;
        }

        $function = $this->findFunction($phpcsFile, $name, $opener);

        return $function === null ? null : ['function' => $function, 'binding' => self::BIND_EARLY];
    }

    private function resolveConstructor(File $phpcsFile, int $classRefPtr, int $opener): ?array
    {
        $reference = $this->resolveClassReference($phpcsFile, $classRefPtr, $opener);

        return $reference === null ? null : $this->resolveMethod($phpcsFile, $reference, '__construct');
    }

    private function resolveMethod(File $phpcsFile, array $reference, string $name): ?array
    {
        $method = $this->findMethod($phpcsFile, $reference['class'], $name);

        return $method === null ? null : ['function' => $method, 'binding' => $reference['binding']];
    }

    private function resolveThisMethod(
        File $phpcsFile,
        int $operatorPtr,
        int $opener,
        string $name
    ): ?array {
        $tokens = $phpcsFile->getTokens();
        $objectPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $operatorPtr - 1, null, true);

        if (
            $objectPtr === false
            || $tokens[$objectPtr]['content'] !== '$this'
        ) {
            return null;
        }

        $class = $this->getEnclosingClass($phpcsFile, $opener);

        if ($class === null) {
            return null;
        }

        return $this->resolveMethod($phpcsFile, ['class' => $class, 'binding' => self::BIND_THIS], $name);
    }

    private function resolveClassReference(File $phpcsFile, int $stackPtr, int $opener): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if (
            $code === T_SELF
            || $code === T_STATIC
        ) {
            $class = $this->getEnclosingClass($phpcsFile, $opener);

            if ($class === null) {
                return null;
            }

            if ($code === T_STATIC) {
                return ['class' => $class, 'binding' => self::BIND_STATIC];
            }

            $isTrait = $tokens[$class]['code'] === T_TRAIT;

            return [
                'class' => $class,
                'binding' => $isTrait ? self::BIND_TRAIT_SELF : self::BIND_EARLY,
            ];
        }

        if ($code !== T_STRING) {
            return null;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

        if ($previousPtr !== false) {
            $previous = $tokens[$previousPtr]['code'];

            if ($previous === T_STRING) {
                return null;
            }
        }

        $class = $this->findClass($phpcsFile, $tokens[$stackPtr]['content'], $stackPtr);

        return $class === null ? null : ['class' => $class, 'binding' => self::BIND_EARLY];
    }

    private function isDispatchProvable(File $phpcsFile, array $callee, int $opener): bool
    {
        if ($callee['binding'] === self::BIND_EARLY) {
            return true;
        }

        $class = $this->getEnclosingClass($phpcsFile, $opener);

        if ($class === null) {
            return false;
        }

        $code = $phpcsFile->getTokens()[$class]['code'];

        if (
            $code === T_ENUM
            || $code === T_ANON_CLASS
        ) {
            return true;
        }

        if ($code !== T_CLASS) {
            return false;
        }

        if ($phpcsFile->getClassProperties($class)['is_final'] === true) {
            return true;
        }

        $properties = $phpcsFile->getMethodProperties($callee['function']);

        if ($properties['is_final'] === true) {
            return true;
        }

        return $callee['binding'] === self::BIND_THIS && $properties['scope'] === 'private';
    }

    private function getEnclosingClass(File $phpcsFile, int $stackPtr): ?int
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $pointer => $code) {
            if (isset(self::CLASS_SCOPES[$code]) === true) {
                return $pointer;
            }
        }

        return null;
    }

    private function findClass(File $phpcsFile, string $name, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $namespace = $this->getNamespace($phpcsFile, $stackPtr);
        $pointer = 0;

        while (($pointer = $phpcsFile->findNext(array_keys(self::CLASS_SCOPES), $pointer + 1)) !== false) {
            if ($tokens[$pointer]['code'] === T_ANON_CLASS) {
                continue;
            }

            if ($this->getNamespace($phpcsFile, $pointer) !== $namespace) {
                continue;
            }

            $declared = (string) (new Declarations)->name($phpcsFile, $pointer);

            if (strcasecmp($declared, $name) === 0) {
                return $pointer;
            }
        }

        return null;
    }

    private function findFunction(File $phpcsFile, string $name, int $stackPtr): ?int
    {
        $namespace = $this->getNamespace($phpcsFile, $stackPtr);
        $pointer = 0;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1)) !== false) {
            if ($this->getEnclosingClass($phpcsFile, $pointer) !== null) {
                continue;
            }

            if ($this->getNamespace($phpcsFile, $pointer) !== $namespace) {
                continue;
            }

            $declared = (string) (new Declarations)->name($phpcsFile, $pointer);

            if (strcasecmp($declared, $name) === 0) {
                return $pointer;
            }
        }

        return null;
    }

    private function getNamespace(File $phpcsFile, int $stackPtr): ?int
    {
        $pointer = $phpcsFile->findPrevious(T_NAMESPACE, $stackPtr - 1);

        return $pointer === false ? null : $pointer;
    }

    private function findMethod(File $phpcsFile, int $classPtr, string $name): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$classPtr]['scope_opener'], $tokens[$classPtr]['scope_closer']) === false) {
            return null;
        }

        $pointer = $tokens[$classPtr]['scope_opener'];
        $end = $tokens[$classPtr]['scope_closer'];

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $end)) !== false) {
            if ($this->getEnclosingClass($phpcsFile, $pointer) !== $classPtr) {
                continue;
            }

            $declared = (string) (new Declarations)->name($phpcsFile, $pointer);

            if (strcasecmp($declared, $name) === 0) {
                return $pointer;
            }
        }

        return null;
    }

    private function getNamesForFix(array $arguments, int $index, array $parameters): ?array
    {
        $names = [];
        $count = count($arguments);

        for ($i = $index; $i < $count; $i++) {
            $argument = $arguments[$i];

            if ($argument['named'] === true) {
                continue;
            }

            if ($argument['spread'] === true) {
                return null;
            }

            $parameter = $parameters[$i] ?? null;

            if (
                $parameter === null
                || $parameter['variable_length'] === true
            ) {
                return null;
            }

            $names[$argument['start']] = ltrim($parameter['name'], '$');
        }

        return $names;
    }
}
