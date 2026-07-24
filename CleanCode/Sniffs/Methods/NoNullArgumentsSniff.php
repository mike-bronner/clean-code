<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Methods;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "no null arguments" clean-code standard: a literal `null` is
 * never passed positionally into a parameter that is optional (declares a
 * default). Skipping over an optional parameter with `null` is dead code the
 * reader has to decode; a named argument says which parameter is meant and
 * lets the rest keep their defaults.
 *
 * `$mailer->send('body', subject: null)` is compliant;
 * `$mailer->send('body', null)` is flagged (`PositionalNull`).
 *
 * Only a *bare* `null` argument is flagged. A `null` nested inside a larger
 * argument (`[null]`, `$a ?? null`, `fn () => null`) is part of an expression,
 * not a skipped parameter, and is left alone — as is every `null` outside a
 * call-argument position entirely.
 *
 * **Resolution is deliberately file-local.** Deciding whether the target
 * parameter is optional — and naming it in the fix — requires the callee's
 * declaration, and PHPCS analyses one file at a time with no cross-file symbol
 * table. So the sniff resolves only what the file itself proves: calls to
 * functions, `$this->`/`self::`/`static::`/`ClassName::` methods, and
 * `new ClassName(...)`/`new self(...)` constructors declared in the same file.
 * Any call it cannot resolve is left alone rather than guessed at, because
 * passing `null` to a *required* nullable parameter is perfectly legitimate
 * and flagging it would be a false positive. Under-reporting beats crying
 * wolf; see docs/standards/methods-no-null-arguments.md.
 */
class NoNullArgumentsSniff implements Sniff
{
    private const CODE = 'PositionalNull';

    private const MESSAGE = 'Do not pass null positionally into the optional '
        . 'parameter $%s; use the named argument "%s: null" instead';

    /**
     * Bracket tokens that open a nested structure inside an argument list.
     * Tracking their depth keeps argument splitting from breaking on commas
     * that belong to a nested call, array, or closure body.
     *
     * @var array<int|string, true>
     */
    private const OPEN_BRACKETS = [
        T_OPEN_PARENTHESIS => true,
        T_OPEN_SQUARE_BRACKET => true,
        T_OPEN_SHORT_ARRAY => true,
        T_OPEN_CURLY_BRACKET => true,
    ];

    /**
     * @var array<int|string, true>
     */
    private const CLOSE_BRACKETS = [
        T_CLOSE_PARENTHESIS => true,
        T_CLOSE_SQUARE_BRACKET => true,
        T_CLOSE_SHORT_ARRAY => true,
        T_CLOSE_CURLY_BRACKET => true,
    ];

    /**
     * Token codes that introduce a class-like scope, used to find the class
     * enclosing a `$this->`/`self::` call.
     *
     * @var array<int|string, true>
     */
    private const CLASS_SCOPES = [
        T_CLASS => true,
        T_ANON_CLASS => true,
        T_TRAIT => true,
        T_ENUM => true,
        T_INTERFACE => true,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_NULL];
    }

    /**
     * @param int $stackPtr
     */
    public function process(File $phpcsFile, $stackPtr): void
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

        $parameters = $this->getCalleeParameters($phpcsFile, $opener);
        $parameter = $parameters[$index] ?? null;

        // Only an *optional* parameter (one declaring a default) can be
        // skipped with a named argument; null into a required parameter is
        // legitimate and stays unflagged. This also excludes a null landing in
        // a variadic parameter, which PHP forbids from declaring a default.
        if ($parameter === null || isset($parameter['default']) === false) {
            return;
        }

        $name = ltrim($parameter['name'], '$');
        $names = $this->getNamesForFix($arguments, $index, $parameters);

        if ($names === null) {
            $phpcsFile->addError(
                self::MESSAGE . ' (cannot be fixed automatically: a later argument in this call'
                    . ' cannot be named)',
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

        // Every positional argument *after* the flagged one has to be named
        // too — PHP rejects a positional argument that follows a named one.
        $phpcsFile->fixer->beginChangeset();

        foreach ($names as $start => $parameterName) {
            $phpcsFile->fixer->addContentBefore($start, $parameterName . ': ');
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Returns the opening parenthesis of the call whose argument list directly
     * contains $stackPtr, or null when the token is not inside a call at all.
     *
     * A call's parentheses are ownerless; declarations (`function foo(...)`)
     * and control structures (`if (...)`) own theirs, which is what separates
     * a call site from a default value in a signature.
     */
    private function getCallOpener(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['nested_parenthesis']) === false) {
            return null;
        }

        $nested = $tokens[$stackPtr]['nested_parenthesis'];
        end($nested);
        $opener = key($nested);

        if ($opener === null || isset($tokens[$opener]['parenthesis_owner']) === true) {
            return null;
        }

        return isset($tokens[$opener]['parenthesis_closer']) === true ? $opener : null;
    }

    /**
     * Splits an argument list into its top-level arguments.
     *
     * @return array<int, array{start: int, end: int, named: bool, spread: bool}>
     */
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
            } elseif (isset(self::CLOSE_BRACKETS[$code]) === true) {
                $depth--;
            } elseif ($code === T_COMMA && $depth === 0) {
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

    /**
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{start: int, end: int, named: bool, spread: bool}
     */
    private function describeArgument(array $tokens, int $start, int $end): array
    {
        return [
            'start' => $start,
            'end' => $end,
            'named' => $tokens[$start]['code'] === T_PARAM_NAME,
            'spread' => $tokens[$start]['code'] === T_ELLIPSIS,
        ];
    }

    /**
     * Returns the positional index of the argument that consists of exactly
     * the `null` at $stackPtr, or null when that token is not a bare
     * positional argument (it is named, part of a bigger expression, or sits
     * behind a spread that makes its position unknowable).
     *
     * @param array<int, array{start: int, end: int, named: bool, spread: bool}> $arguments
     */
    private function getBareNullArgumentIndex(array $arguments, int $stackPtr): ?int
    {
        foreach ($arguments as $index => $argument) {
            if ($argument['spread'] === true) {
                // Arguments unpacked from a spread consume an unknown number
                // of positions, so nothing after it can be placed.
                return null;
            }

            if ($argument['start'] !== $stackPtr) {
                continue;
            }

            // The argument has to be *only* this null. Starting at it is not
            // enough — `null !== $x` starts here too, and is an expression.
            // (A named argument starts at its label, so it never gets here.)
            return $argument['end'] === $stackPtr ? $index : null;
        }

        return null;
    }

    /**
     * Resolves the declaration the call targets and returns its parameters, or
     * null when the callee is not declared in this file. See the class
     * docblock for why resolution stops at the file boundary.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function getCalleeParameters(File $phpcsFile, int $opener): ?array
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
            $class = $this->resolveClassReference($phpcsFile, $namePtr, $opener);

            return $class === null ? null : $this->getMethodParameters($phpcsFile, $class, '__construct');
        }

        if ($code !== T_STRING) {
            return null;
        }

        $name = $tokens[$namePtr]['content'];

        if ($previous === T_OBJECT_OPERATOR || $previous === T_NULLSAFE_OBJECT_OPERATOR) {
            return $this->getThisMethodParameters($phpcsFile, (int) $previousPtr, $opener, $name);
        }

        if ($previous === T_DOUBLE_COLON) {
            $classPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previousPtr - 1, null, true);

            if ($classPtr === false) {
                return null;
            }

            $class = $this->resolveClassReference($phpcsFile, $classPtr, $opener);

            return $class === null ? null : $this->getMethodParameters($phpcsFile, $class, $name);
        }

        // A qualified name (\Foo\bar(), Foo\bar()) refers outside this file
        // even when a same-named function is declared in it.
        if ($previous === T_NS_SEPARATOR || $previous === T_STRING || $previous === T_FUNCTION) {
            return null;
        }

        $function = $this->findFunction($phpcsFile, $name);

        return $function === null ? null : $phpcsFile->getMethodParameters($function);
    }

    /**
     * Resolves a method call made on `$this` — the only object reference whose
     * class is knowable from the file alone.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function getThisMethodParameters(
        File $phpcsFile,
        int $operatorPtr,
        int $opener,
        string $name
    ): ?array {
        $tokens = $phpcsFile->getTokens();
        $objectPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $operatorPtr - 1, null, true);

        if ($objectPtr === false || strtolower($tokens[$objectPtr]['content']) !== '$this') {
            return null;
        }

        $class = $this->getEnclosingClass($phpcsFile, $opener);

        return $class === null ? null : $this->getMethodParameters($phpcsFile, $class, $name);
    }

    /**
     * Resolves a class reference token (`self`, `static`, or an unqualified
     * class name) to the class declaration in this file, or null when it names
     * something the file does not declare.
     */
    private function resolveClassReference(File $phpcsFile, int $stackPtr, int $opener): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_SELF || $code === T_STATIC) {
            return $this->getEnclosingClass($phpcsFile, $opener);
        }

        // `parent::` and qualified names (\Foo\Bar, Foo\Bar) resolve outside
        // this file; only a bare, unqualified name can be matched locally.
        if ($code !== T_STRING) {
            return null;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

        if ($previousPtr !== false) {
            $previous = $tokens[$previousPtr]['code'];

            if ($previous === T_NS_SEPARATOR || $previous === T_STRING) {
                return null;
            }
        }

        return $this->findClass($phpcsFile, $tokens[$stackPtr]['content']);
    }

    /**
     * Finds the class-like declaration enclosing $stackPtr.
     */
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

    /**
     * Finds a class-like declaration by unqualified name.
     */
    private function findClass(File $phpcsFile, string $name): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $pointer = 0;

        while (($pointer = $phpcsFile->findNext(array_keys(self::CLASS_SCOPES), $pointer + 1)) !== false) {
            // An anonymous class has no name to match against.
            if ($tokens[$pointer]['code'] === T_ANON_CLASS) {
                continue;
            }

            if (strcasecmp((string) $phpcsFile->getDeclarationName($pointer), $name) === 0) {
                return $pointer;
            }
        }

        return null;
    }

    /**
     * Finds a namespace-level function declaration by name — one whose
     * T_FUNCTION is not nested in a class-like scope.
     */
    private function findFunction(File $phpcsFile, string $name): ?int
    {
        $pointer = 0;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1)) !== false) {
            if ($this->getEnclosingClass($phpcsFile, $pointer) !== null) {
                continue;
            }

            if (strcasecmp((string) $phpcsFile->getDeclarationName($pointer), $name) === 0) {
                return $pointer;
            }
        }

        return null;
    }

    /**
     * Returns the parameters of $name declared directly on the class at
     * $classPtr, or null when the class does not declare it (an inherited
     * method lives in another file and stays unresolved).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function getMethodParameters(File $phpcsFile, int $classPtr, string $name): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$classPtr]['scope_opener'], $tokens[$classPtr]['scope_closer']) === false) {
            return null;
        }

        $pointer = $tokens[$classPtr]['scope_opener'];
        $end = $tokens[$classPtr]['scope_closer'];

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $end)) !== false) {
            // Skip methods of a class nested inside this one (an anonymous
            // class in a method body declares its own methods).
            if ($this->getEnclosingClass($phpcsFile, $pointer) !== $classPtr) {
                continue;
            }

            if (strcasecmp((string) $phpcsFile->getDeclarationName($pointer), $name) === 0) {
                return $phpcsFile->getMethodParameters($pointer);
            }
        }

        return null;
    }

    /**
     * Builds the argument-start => parameter-name map the fixer applies, or
     * null when the call cannot be rewritten safely — a spread or a variadic
     * target has no name to give it, and leaving it positional after a named
     * argument would not parse.
     *
     * @param array<int, array{start: int, end: int, named: bool, spread: bool}> $arguments
     * @param array<int, array<string, mixed>>                                   $parameters
     *
     * @return array<int, string>|null
     */
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

            if ($parameter === null || $parameter['variable_length'] === true) {
                return null;
            }

            $names[$argument['start']] = ltrim($parameter['name'], '$');
        }

        return $names;
    }
}
