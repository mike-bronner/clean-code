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
 * functions, `$this->`/`self::`/`static::`/`ClassName::` methods,
 * `new ClassName(...)`/`new self(...)` constructors, and `#[Attribute(...)]`
 * instantiations declared in the same file. Any call it cannot resolve is left
 * alone rather than guessed at, because passing `null` to a *required* nullable
 * parameter is perfectly legitimate and flagging it would be a false positive.
 * Under-reporting beats crying wolf; see
 * docs/standards/methods-no-null-arguments.md.
 *
 * **Finding the declaration is not the same as knowing it runs.** `$this->m()`,
 * `static::m()` and `new static(...)` are dispatched against the *runtime*
 * class, so a subclass — which usually lives in a file this sniff never sees —
 * may override the method and rename the very parameter the fix would write.
 * That rewrite turns working code into an `Unknown named parameter` fatal, so
 * those calls are reported but **not auto-fixed** unless the file proves
 * dispatch cannot be diverted (see {@see self::isDispatchProvable()}).
 */
class NoNullArgumentsSniff implements Sniff
{
    private const CODE = 'PositionalNull';

    private const MESSAGE = 'Do not pass null positionally into the optional '
        . 'parameter $%s; use the named argument "%s: null" instead';

    private const UNNAMEABLE_ARGUMENT = ' (cannot be fixed automatically: a later argument in this call'
        . ' cannot be named)';

    private const LATE_BOUND_CALL = ' (cannot be fixed automatically: this call is dispatched against the'
        . ' runtime class, which may override the method and rename the parameter)';

    /**
     * The resolved declaration is the one that runs: the call site names it
     * outright (`self::`, `ClassName::`, `new ClassName`, a function, an
     * attribute), so no runtime dispatch can reach a different body.
     */
    private const BIND_EARLY = 'early';

    /**
     * A `$this->` call. Dispatched against the runtime class, but a `private`
     * method is still resolved in the scope that declares it.
     */
    private const BIND_THIS = 'this';

    /**
     * A `static::` call or `new static(...)`. Late static binding resolves the
     * target against the runtime class with no scope-private escape hatch.
     */
    private const BIND_STATIC = 'static';

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
     * enclosing a `$this->`/`self::` call, to match a class by name, and to
     * tell a namespace-level function from a method.
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

        $callee = $this->resolveCallee($phpcsFile, $opener);

        if ($callee === null) {
            return;
        }

        $parameters = $phpcsFile->getMethodParameters($callee['function']);
        $parameter = $parameters[$index] ?? null;

        // Only an *optional* parameter (one declaring a default) can be
        // skipped with a named argument; null into a required parameter is
        // legitimate and stays unflagged. This also excludes a null landing in
        // a variadic parameter, which PHP forbids from declaring a default.
        if ($parameter === null || isset($parameter['default']) === false) {
            return;
        }

        $name = ltrim($parameter['name'], '$');

        // The violation is real either way — the standard wants a named
        // argument here — but a call whose target is chosen at runtime has no
        // parameter name this file can prove, so it is reported unfixed.
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
     * a call site from a default value in a signature. Every owned-parenthesis
     * construct reachable with a bare `null` argument is also rejected further
     * down (a signature default is not a lone argument, and a control
     * structure resolves to no callee), so the owner test is belt-and-braces —
     * it states the contract at the point that depends on it.
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
                // of positions, so nothing after it can be placed. Only a
                // spread *before* the null reaches this, and PHP rejects that
                // outright ("Cannot use positional argument after argument
                // unpacking"), so it is unreachable from code that compiles —
                // kept because the index it would otherwise return is wrong.
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
     * Resolves the declaration the call targets, together with how the call
     * binds to it, or null when the callee is not declared in this file. See
     * the class docblock for why resolution stops at the file boundary.
     *
     * @return array{function: int, binding: string}|null
     */
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

        // The callee has to be an identifier to be looked up by name. A
        // dynamic call (`$callback(...)`, `($expr)(...)`) is named by a token
        // whose content could never match a declaration anyway, so this is a
        // contract guard rather than an observable behaviour change.
        if ($code !== T_STRING) {
            return null;
        }

        $name = $tokens[$namePtr]['content'];

        // An attribute instantiates its class, so its arguments are the
        // constructor's. The attribute's name follows `#[`, or the comma
        // separating it from the previous one in a grouped attribute; anything
        // else inside the brackets is part of an argument expression.
        if (
            isset($tokens[$namePtr]['nested_attributes']) === true
            && ($previous === T_ATTRIBUTE || $previous === T_COMMA)
        ) {
            return $this->resolveConstructor($phpcsFile, $namePtr, $opener);
        }

        if ($previous === T_OBJECT_OPERATOR || $previous === T_NULLSAFE_OBJECT_OPERATOR) {
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

        // A qualified name (\Foo\bar(), Foo\bar()) refers outside this file
        // even when a same-named function is declared in it.
        if ($previous === T_NS_SEPARATOR || $previous === T_STRING || $previous === T_FUNCTION) {
            return null;
        }

        $function = $this->findFunction($phpcsFile, $name, $opener);

        return $function === null ? null : ['function' => $function, 'binding' => self::BIND_EARLY];
    }

    /**
     * Resolves the constructor a `new ...` expression or an attribute
     * instantiation targets.
     *
     * @return array{function: int, binding: string}|null
     */
    private function resolveConstructor(File $phpcsFile, int $classRefPtr, int $opener): ?array
    {
        $reference = $this->resolveClassReference($phpcsFile, $classRefPtr, $opener);

        return $reference === null ? null : $this->resolveMethod($phpcsFile, $reference, '__construct');
    }

    /**
     * Looks $name up on an already-resolved class reference, carrying the
     * reference's binding through to the result.
     *
     * @param array{class: int, binding: string} $reference
     *
     * @return array{function: int, binding: string}|null
     */
    private function resolveMethod(File $phpcsFile, array $reference, string $name): ?array
    {
        $method = $this->findMethod($phpcsFile, $reference['class'], $name);

        return $method === null ? null : ['function' => $method, 'binding' => $reference['binding']];
    }

    /**
     * Resolves a method call made on `$this` — the only object reference whose
     * class is knowable from the file alone.
     *
     * @return array{function: int, binding: string}|null
     */
    private function resolveThisMethod(
        File $phpcsFile,
        int $operatorPtr,
        int $opener,
        string $name
    ): ?array {
        $tokens = $phpcsFile->getTokens();
        $objectPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, $operatorPtr - 1, null, true);

        // PHP variable names are case-sensitive, so only the exact spelling is
        // the `$this` whose class this file knows; `$This` is an ordinary
        // variable that may hold an object of any class at all.
        if ($objectPtr === false || $tokens[$objectPtr]['content'] !== '$this') {
            return null;
        }

        $class = $this->getEnclosingClass($phpcsFile, $opener);

        if ($class === null) {
            return null;
        }

        return $this->resolveMethod($phpcsFile, ['class' => $class, 'binding' => self::BIND_THIS], $name);
    }

    /**
     * Resolves a class reference token (`self`, `static`, or an unqualified
     * class name) to the class declaration in this file, or null when it names
     * something the file does not declare.
     *
     * @return array{class: int, binding: string}|null
     */
    private function resolveClassReference(File $phpcsFile, int $stackPtr, int $opener): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_SELF || $code === T_STATIC) {
            $class = $this->getEnclosingClass($phpcsFile, $opener);

            if ($class === null) {
                return null;
            }

            // `self` names the declaring class outright; `static` defers to
            // the runtime class, which may be a subclass this file never sees.
            return [
                'class' => $class,
                'binding' => $code === T_STATIC ? self::BIND_STATIC : self::BIND_EARLY,
            ];
        }

        // `parent::` resolves outside this file. The name lookup below could
        // not match it either — `parent` is reserved and cannot name a class —
        // so this states the intent rather than changing the outcome.
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

        $class = $this->findClass($phpcsFile, $tokens[$stackPtr]['content'], $stackPtr);

        return $class === null ? null : ['class' => $class, 'binding' => self::BIND_EARLY];
    }

    /**
     * Answers whether the declaration the call resolved to is provably the one
     * that runs — the question the auto-fixer depends on, since it writes that
     * declaration's parameter name into the source.
     *
     * An early-bound call names its target outright. A `$this->`/`static::`
     * call does not: PHP picks the body from the *runtime* class, and an
     * override may rename the parameter (renaming one is valid PHP — signature
     * compatibility covers types and defaults, never names). The overriding
     * subclass is usually in another file, so the sniff can only fix the call
     * when the file shows dispatch cannot be diverted at all.
     *
     * @param array{function: int, binding: string} $callee
     */
    private function isDispatchProvable(File $phpcsFile, array $callee, int $opener): bool
    {
        if ($callee['binding'] === self::BIND_EARLY) {
            return true;
        }

        // Late binding only arises inside a class-like scope, and always
        // dispatches from the one enclosing the call site — so this is never
        // null in practice. Guarded anyway, failing closed.
        $class = $this->getEnclosingClass($phpcsFile, $opener);

        if ($class === null) {
            return false;
        }

        $code = $phpcsFile->getTokens()[$class]['code'];

        // An enum cannot be extended, and an anonymous class has no name to
        // extend, so in both the declaration in this file is the one that runs.
        if ($code === T_ENUM || $code === T_ANON_CLASS) {
            return true;
        }

        // Only a class can be subclassed in a way that diverts dispatch, and
        // only a class carries the modifiers that could rule that out. A
        // trait's methods are copied into every using class, which may declare
        // its own version of any of them — `private` and `final` included — so
        // a trait proves nothing about which body runs. An interface declares
        // no body to dispatch to at all. Both fail closed.
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

        // A private method is resolved in the scope that declares it, so
        // `$this->m()` reaches this declaration even when a subclass declares
        // the same name. `static::m()` gets no such protection: it binds to the
        // subclass first and only then checks visibility.
        return $callee['binding'] === self::BIND_THIS && $properties['scope'] === 'private';
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
     * Finds a class-like declaration by unqualified name, within the namespace
     * governing $stackPtr.
     */
    private function findClass(File $phpcsFile, string $name, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $namespace = $this->getNamespace($phpcsFile, $stackPtr);
        $pointer = 0;

        while (($pointer = $phpcsFile->findNext(array_keys(self::CLASS_SCOPES), $pointer + 1)) !== false) {
            // An anonymous class has no name to match against.
            if ($tokens[$pointer]['code'] === T_ANON_CLASS) {
                continue;
            }

            if ($this->getNamespace($phpcsFile, $pointer) !== $namespace) {
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
     * T_FUNCTION is not nested in a class-like scope — within the namespace
     * governing $stackPtr.
     */
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

            if (strcasecmp((string) $phpcsFile->getDeclarationName($pointer), $name) === 0) {
                return $pointer;
            }
        }

        return null;
    }

    /**
     * Returns the declaration governing $stackPtr's namespace, or null when the
     * file declares none.
     *
     * The same short name may be declared once per namespace block, so a file
     * with several blocks can hold two unrelated classes that share it.
     * Comparing the governing declarations tells them apart. `namespace\foo()`
     * reuses this token as an operator; the worst it can do is make two
     * pointers disagree, which withholds resolution rather than misdirecting
     * it.
     */
    private function getNamespace(File $phpcsFile, int $stackPtr): ?int
    {
        $pointer = $phpcsFile->findPrevious(T_NAMESPACE, $stackPtr - 1);

        return $pointer === false ? null : $pointer;
    }

    /**
     * Returns the pointer to $name declared directly on the class at
     * $classPtr, or null when the class does not declare it (an inherited
     * method lives in another file and stays unresolved).
     */
    private function findMethod(File $phpcsFile, int $classPtr, string $name): ?int
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
                return $pointer;
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
