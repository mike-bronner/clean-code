<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a method whose name starts with an action verb but that hands a value
 * back. Methods: Naming (#61, docs/standards/methods-naming.md) says "methods
 * that perform an action should be a verb and not return anything" — the
 * command half of command-query separation. A caller reading `$order->save()`
 * is told a thing is done; a return value quietly makes the same call a
 * question too, and the two roles then travel together forever.
 *
 * The standard as a whole is Tier 3: whether a name is a genuine verb, and
 * whether it describes what the method does, are natural-language judgements a
 * token-based sniff cannot make. This sniff takes the one slice that is
 * token-visible (#172) — a name that starts with a *known* action verb, and a
 * return that is a declared type or a `return <expr>;`. Everything else about
 * the standard stays code review.
 *
 * Two questions decide a report, and both are answered from tokens:
 *
 * - **Is the name an action?** The name starts with one of $actionPrefixes and
 *   the next character is not lower-case, so `setName()` and `set()` match
 *   while `settle()` and `addressOf()` do not.
 * - **Does it return a value?** Either the declaration says so — any return
 *   type other than `void`/`never` — or, lacking a declared type, the body
 *   holds a `return` with an expression after it. A bare `return;` is flow
 *   control, not a value.
 *
 * Methods only. A plain function, a closure, an arrow function, and a named
 * function nested inside a method are all left alone: the standard speaks about
 * a class's methods, and the call site it describes ("an action being taken on
 * the class") does not exist for a free function. Only the *innermost*
 * enclosing scope decides, since a function declared in a method still lists
 * that method's class among its conditions.
 *
 * Warning severity, not error. Framework idioms legitimately return a status
 * from an action method — Eloquent's `save(): bool` is the obvious one — so
 * this points at review candidates rather than mandating a rewrite. Detection
 * only: dropping a return type changes what every call site can do with the
 * call, which is not a mechanical rewrite.
 *
 * The file is written to pass rules.xml, the standard it belongs to, and that
 * is why it reads the way it does: match(true) guard chains rather than `if`
 * (CleanCode.Conditionals.AvoidConditionals), counted folds rather than
 * array_map()/array_filter() (CleanCode.Arrays.ConvertToCollection), the File
 * API — getCondition(), findNext(), getTokensAsString() — rather than the token
 * array (CleanCode.Arrays.ArrayAccessors), and a NOWDOC message
 * (CleanCode.Strings.MultilineStrings). Each replacement those three Arrays and
 * Strings rules ask for is a Laravel helper this package does not ship, so each
 * has to be written around rather than adopted.
 *
 * scopeBoundary() is the one read the File API cannot express: PHP_CodeSniffer
 * publishes no accessor for a declaration's `scope_opener`/`scope_closer`, so
 * the pointers are read off getTokens()' return value. That spelling is rooted
 * in a call rather than a variable, which is a blind spot
 * CleanCode.Arrays.ArrayAccessors documents about itself — recorded here rather
 * than left to look like an oversight.
 *
 * tests/Standards/ActionMethodReturnTest.php asserts the clean run, so a
 * sibling standard landing later cannot falsify this claim unnoticed.
 */
class ActionMethodReturnSniff implements Sniff
{
    /**
     * The action verbs a method name is matched against, from #172. Matched
     * case-sensitively and in the order given, so a project can both retune the
     * list and decide which verb is reported when two of them could match the
     * same name.
     *
     * Case-sensitive because a method named `SetName()` is a *casing* problem,
     * owned by Naming: Casing conventions (#22/#100) — folding case here would
     * report the same declaration twice for two different reasons and describe
     * neither. Configurable from a ruleset via
     * <property name="actionPrefixes" type="array" .../>.
     *
     * Written several to a line because one verb per line is fourteen lines of
     * identical token shape, which CleanCode.Pattern.AvoidDuplicateCodeBlocks
     * reads — correctly — as a repeated block.
     *
     * @var array<int, string>
     */
    public array $actionPrefixes = [
        'add', 'apply', 'attach', 'clear', 'delete', 'detach', 'post',
        'remove', 'reset', 'save', 'send', 'set', 'store', 'update',
    ];

    /**
     * Whether a fluent interface is exempt. On (the default), a method that
     * hands back its own object — `: static`, `: self`, a bare return type
     * naming the enclosing class, a union of nothing but those, or a body whose
     * every value-return is `return $this;` — is not reported: chaining is a
     * builder idiom, and reporting it would bury the finding this sniff exists
     * for under every fluent setter in the codebase.
     *
     * Off, those declarations are read like any other returning method. A
     * project that has decided against fluent setters gets the whole rule.
     */
    public bool $allowFluentInterface = true;

    /**
     * Scopes whose declarations are methods. T_ANON_CLASS is included: a method
     * of an anonymous class is a method, and the standard's rule about what a
     * call site reads like applies to it unchanged.
     */
    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * The scopes that own a `return`. The innermost one holding a `return`
     * decides which declaration that `return` belongs to, which is what keeps a
     * closure's or a nested function's value-return from being read as the
     * enclosing method's.
     */
    private const CALLABLE_TOKENS = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    /**
     * The return types that declare "this returns nothing". Both are what the
     * standard asks an action method to say, so neither is ever reported —
     * `never` included, since a method that always throws hands back no value
     * either.
     */
    private const COMMAND_RETURN_TYPES = [
        'never',
        'void',
    ];

    /**
     * The return types that mean "my own object", for the fluent exemption.
     * A bare class name equal to the enclosing class is the third spelling and
     * is resolved separately, because it depends on the declaration's context.
     */
    private const FLUENT_RETURN_TYPES = [
        'self',
        'static',
    ];

    /**
     * Stands in for "no such token" so a pointer comparison stays arithmetic.
     * Below every real pointer, since PHP_CodeSniffer numbers tokens from zero.
     */
    private const NO_POINTER = -1;

    /**
     * A NOWDOC rather than a concatenated string, and folded back to one line by
     * message(): CleanCode.Strings.MultilineStrings asks for the heredoc form,
     * CleanCode.Strings.EscapeNestedQuotes rejects the nested quotes the verb is
     * named in, and the CSV and checkstyle reports put one violation on one
     * line.
     */
    private const MESSAGE = <<<'MESSAGE'
        The %s() method starts with the action verb "%s", so it commands rather than
        answers and should not return a value — return nothing, or rename it for what
        it hands back (see docs/standards/methods-naming.md)
        MESSAGE;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * The native int hint SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * asks for on $stackPtr cannot be written: PHP_CodeSniffer's Sniff
     * interface declares the parameter untyped, and narrowing an inherited
     * untyped parameter is a fatal error, so the hint would stop the sniff
     * loading at all. The return hint has no such constraint and is written.
     *
     * Reported at the name, since the name is half of what the rule asks to be
     * changed — the other half is the return, and only the pair of them is the
     * violation. getDeclarationName() resolves the name by scanning forward for
     * the first T_STRING, so the two always agree on a token.
     *
     * @param int $stackPtr
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- see above
    public function process(File $phpcsFile, $stackPtr): void
    {
        $name = (string) $phpcsFile->getDeclarationName($stackPtr);
        $prefix = $this->matchedPrefix($name);

        match (true) {
            $this->isMethod($phpcsFile, $stackPtr) === false => null,
            $prefix === null => null,
            $this->returnsValue($phpcsFile, $stackPtr) === false => null,
            default => $phpcsFile->addWarning(
                $this->message(),
                $phpcsFile->findNext(T_STRING, $stackPtr),
                'Found',
                [$name, $prefix]
            ),
        };
    }

    /**
     * Whether the declaration is a method — that is, whether the innermost
     * scope holding it is class-like.
     *
     * Conditions nest, so the ancestor that opens last is the innermost one:
     * comparing the nearest class-like ancestor against the nearest
     * callable one answers which of the two the declaration sits in. That is
     * what tells a method apart from a named function declared inside one —
     * the nested function's conditions list still holds the enclosing class, so
     * a search for "any class-like condition" would call it a method.
     */
    private function isMethod(File $phpcsFile, int $stackPtr): bool
    {
        $classPtr = $this->innermostCondition($phpcsFile, $stackPtr, self::CLASS_LIKE_TOKENS);
        $callablePtr = $this->innermostCondition($phpcsFile, $stackPtr, self::CALLABLE_TOKENS);

        return $classPtr > $callablePtr;
    }

    /**
     * The first configured verb the name starts with, or null when it starts
     * with none.
     *
     * `??=` short-circuits, so the first verb that matches is the one kept and
     * no later verb is even tested — which is what makes the configured order
     * decide between two verbs that could both match one name.
     */
    private function matchedPrefix(string $name): ?string
    {
        $matched = null;

        foreach ($this->actionPrefixes as $prefix) {
            $matched ??= $this->prefixOrNull($name, (string) $prefix);
        }

        return $matched;
    }

    /**
     * The verb when the name starts with it as a whole word, null otherwise.
     *
     * The character after the verb must not be lower-case, which is what makes
     * `set` a verb in `setName()`, `set()` and `set_name()` but not in
     * `settle()`. That boundary is camelCase read backwards: a new word starts
     * at a capital, so a lower-case continuation means the verb was never a
     * word of its own. A name that is nothing but the verb leaves an empty
     * remainder, and ctype_lower() answers false for that, so `set()` matches.
     */
    private function prefixOrNull(string $name, string $prefix): ?string
    {
        $rest = substr($name, strlen($prefix));

        return match (true) {
            $prefix === '' => null,
            str_starts_with($name, $prefix) === false => null,
            ctype_lower(substr($rest, 0, 1)) => null,
            default => $prefix,
        };
    }

    /**
     * Whether the declaration hands a value back.
     *
     * The declared type is the whole answer when there is one: it is what every
     * call site reads, and it is what rules.xml requires on every method
     * (SlevomatCodingStandard.TypeHints.ReturnTypeHint). The body is only
     * consulted when nothing is declared — an interface method, a legacy
     * signature — because there the `return` statements are all a reader has.
     */
    private function returnsValue(File $phpcsFile, int $stackPtr): bool
    {
        $declared = $this->declaredReturnType($phpcsFile, $stackPtr);

        return match ($declared) {
            '' => $this->bodyReturnsValue($phpcsFile, $stackPtr),
            default => $this->declarationReturnsValue($phpcsFile, $stackPtr, $declared),
        };
    }

    /**
     * Whether a declared return type says a value comes back.
     */
    private function declarationReturnsValue(File $phpcsFile, int $stackPtr, string $declared): bool
    {
        return match (true) {
            in_array($declared, self::COMMAND_RETURN_TYPES, true) => false,
            $this->isFluentType($phpcsFile, $stackPtr, $declared) => false,
            default => true,
        };
    }

    /**
     * Whether an undeclared body hands a value back.
     */
    private function bodyReturnsValue(File $phpcsFile, int $stackPtr): bool
    {
        $returns = $this->valueReturns($phpcsFile, $stackPtr);

        return match (true) {
            $returns === [] => false,
            $this->isFluentBody($phpcsFile, $returns) => false,
            default => true,
        };
    }

    /**
     * The declaration's return type, normalised — whitespace removed, folded to
     * lower case (PHP type names are case-insensitive), and stripped of the
     * nullable marker and any `null` union member, since neither changes
     * whether a value comes back. An empty string means none was declared.
     */
    private function declaredReturnType(File $phpcsFile, int $stackPtr): string
    {
        $written = (string) $phpcsFile->getMethodProperties($stackPtr)['return_type'];
        $normalized = ltrim(strtolower((string) preg_replace('/\s+/', '', $written)), '?');
        $members = array_values(array_diff(explode('|', $normalized), ['null', '']));

        return implode('|', $members);
    }

    /**
     * Whether a normalised return type says "my own object", and the exemption
     * is switched on.
     */
    private function isFluentType(File $phpcsFile, int $stackPtr, string $type): bool
    {
        return match ($this->allowFluentInterface) {
            false => false,
            default => $this->everyMemberIsFluent($phpcsFile, $stackPtr, $type),
        };
    }

    /**
     * Whether *every* member of a union means "my own object".
     *
     * Every one of them, because a union is exempt only when no member of it
     * can be a value: `self|static` and `Builder|static` are two spellings of
     * the same object and chain like any other builder, while `static|false`
     * hands back either the object or a value, and the value is the half this
     * rule is about. A type with no `|` in it is the one-member case of the
     * same test.
     *
     * Counted rather than folded with array_filter(), which
     * CleanCode.Arrays.ConvertToCollection rejects in favour of collect(), a
     * Laravel helper this package does not ship.
     */
    private function everyMemberIsFluent(File $phpcsFile, int $stackPtr, string $type): bool
    {
        $className = $this->enclosingClassName($phpcsFile, $stackPtr);
        $members = explode('|', $type);
        $fluent = 0;

        foreach ($members as $member) {
            $fluent += (int) $this->isFluentMember($member, $className);
        }

        return $fluent === count($members);
    }

    /**
     * Whether one normalised union member names the declaration's own object.
     *
     * The bare class name is accepted unqualified only — a namespaced spelling
     * cannot be compared against a declaration name without resolving imports,
     * and a wrong answer there would silence a real finding.
     */
    private function isFluentMember(string $member, ?string $className): bool
    {
        return match (true) {
            in_array($member, self::FLUENT_RETURN_TYPES, true) => true,
            $className === null => false,
            default => $member === strtolower($className),
        };
    }

    /**
     * The name of the innermost class-like scope holding the declaration, or
     * null when it is an anonymous class (which has no name to compare against,
     * so only `self`/`static` can express its fluent interface).
     */
    private function enclosingClassName(File $phpcsFile, int $stackPtr): ?string
    {
        $classPtr = $this->innermostCondition($phpcsFile, $stackPtr, self::CLASS_LIKE_TOKENS);

        return match ($classPtr) {
            self::NO_POINTER => null,
            default => $phpcsFile->getDeclarationName($classPtr),
        };
    }

    /**
     * Every `return <expr>;` the declaration's own body holds, as the pointer to
     * the first token of each expression.
     *
     * A declaration with no body at all — abstract, or an interface method —
     * has neither scope pointer, so both read NO_POINTER and the search runs
     * over the empty range `0` to `-1`. No separate "has no body" guard is
     * written for that: findNext() bounds its walk with `$i < $end`, so an end
     * below the start ends the search before it starts and the result is the
     * empty list either way. The guard was written first and removed once
     * mutation testing showed it could not change an outcome.
     *
     * @return array<int, int>
     */
    private function valueReturns(File $phpcsFile, int $stackPtr): array
    {
        $opener = $this->scopeBoundary($phpcsFile, $stackPtr, 'scope_opener');
        $closer = $this->scopeBoundary($phpcsFile, $stackPtr, 'scope_closer');
        $returns = [];
        $pointer = $phpcsFile->findNext(T_RETURN, ($opener + 1), $closer);

        while ($pointer !== false) {
            $returns = $this->withExpression(
                $returns,
                $this->ownReturnedExpression($phpcsFile, $stackPtr, $pointer, $closer)
            );
            $pointer = $phpcsFile->findNext(T_RETURN, ($pointer + 1), $closer);
        }

        return $returns;
    }

    /**
     * The pointer to what a `return` hands back, or null when it hands back
     * nothing this declaration answers for.
     *
     * A `return` is this declaration's only when the innermost callable scope
     * holding it is this one, so a closure, an arrow function, a nested
     * function, and a method of an anonymous class declared inside this body all
     * keep their own returns. A bare `return;` contributes nothing: it ends the
     * method, it does not answer anything.
     */
    private function ownReturnedExpression(
        File $phpcsFile,
        int $stackPtr,
        int $returnPtr,
        int $closer
    ): ?int {
        $expression = $this->orNull(
            $phpcsFile->findNext(Tokens::$emptyTokens, ($returnPtr + 1), $closer, true)
        );

        return match (true) {
            $this->owningCallable($phpcsFile, $returnPtr) !== $stackPtr => null,
            $this->isToken($phpcsFile, $expression, T_SEMICOLON) => null,
            default => $expression,
        };
    }

    /**
     * The list with the expression appended, or unchanged when there is none.
     *
     * @param array<int, int> $returns
     *
     * @return array<int, int>
     */
    private function withExpression(array $returns, ?int $expression): array
    {
        return match ($expression) {
            null => $returns,
            default => [...$returns, $expression],
        };
    }

    /**
     * The declaration a token sits inside, or NO_POINTER when it sits in none.
     */
    private function owningCallable(File $phpcsFile, int $stackPtr): int
    {
        return $this->innermostCondition($phpcsFile, $stackPtr, self::CALLABLE_TOKENS);
    }

    /**
     * Whether every value-return in the body is `return $this;`, and the
     * exemption is switched on.
     */
    private function isFluentBody(File $phpcsFile, array $returns): bool
    {
        return match ($this->allowFluentInterface) {
            false => false,
            default => $this->everyReturnIsThis($phpcsFile, $returns),
        };
    }

    /**
     * Every one of them, because a body that returns `$this` on one path and a
     * result on another is exactly the mixed command-query this rule is about.
     *
     * @param array<int, int> $returns
     */
    private function everyReturnIsThis(File $phpcsFile, array $returns): bool
    {
        $fluent = 0;

        foreach ($returns as $expression) {
            $fluent += (int) $this->isBareThis($phpcsFile, $expression);
        }

        return $fluent === count($returns);
    }

    /**
     * Whether the expression is `$this` and nothing more.
     *
     * Matched on content and adjacency — the `$this` token followed immediately
     * by the semicolon — so `$this->name`, `$this->save()` and `$this ?: $other`
     * are values built from `$this`, not `$this` itself. No token-type check
     * accompanies the content one: `$this` is spelled with a sigil no other
     * token in an expression can carry, so the content answers the type too,
     * and mutation testing confirmed the extra check could not change an
     * outcome.
     */
    private function isBareThis(File $phpcsFile, int $expression): bool
    {
        $next = $this->orNull(
            $phpcsFile->findNext(Tokens::$emptyTokens, ($expression + 1), null, true)
        );

        return match ($this->contentOf($phpcsFile, $expression)) {
            '$this' => $this->isToken($phpcsFile, $next, T_SEMICOLON),
            default => false,
        };
    }

    /**
     * The pointer to the nearest enclosing scope of any of the given types, or
     * NO_POINTER when the token is inside none of them.
     *
     * Folded with max() rather than mapped and filtered, because
     * CleanCode.Arrays.ConvertToCollection rejects array_map()/array_filter() in
     * favour of collect(), a Laravel helper this package does not ship.
     *
     * @param array<int, int|string> $types
     */
    private function innermostCondition(File $phpcsFile, int $stackPtr, array $types): int
    {
        $innermost = self::NO_POINTER;

        foreach ($types as $type) {
            $innermost = max($innermost, $this->conditionPointer($phpcsFile, $stackPtr, $type));
        }

        return $innermost;
    }

    /**
     * The innermost condition of one type, or NO_POINTER when there is none.
     */
    private function conditionPointer(File $phpcsFile, int $stackPtr, int|string $type): int
    {
        // Hoisted out of the match subject rather than written inline: rules.xml
        // reports an assignment in a condition (#79), and a match subject is one
        // of the conditions it reads.
        $pointer = $phpcsFile->getCondition($stackPtr, $type, false);

        return match ($pointer) {
            false => self::NO_POINTER,
            default => $pointer,
        };
    }

    /**
     * One of a declaration's scope pointers, or NO_POINTER when it has none.
     *
     * PHP_CodeSniffer publishes no accessor for `scope_opener`/`scope_closer`,
     * so this is the one read in the file taken off the token array rather than
     * through the File API — see the class docblock.
     */
    private function scopeBoundary(File $phpcsFile, int $stackPtr, string $boundary): int
    {
        return $phpcsFile->getTokens()[$stackPtr][$boundary] ?? self::NO_POINTER;
    }

    /**
     * Whether the token at the pointer is one of the given types. An absent
     * pointer is no token, so it is nothing's type — which is what lets a
     * caller ask about the end of a file without guarding for it first.
     */
    private function isToken(File $phpcsFile, ?int $stackPtr, array|int|string $types): bool
    {
        return match ($stackPtr) {
            null => false,
            default => $phpcsFile->findNext($types, $stackPtr, ($stackPtr + 1)) !== false,
        };
    }

    /**
     * PHP_CodeSniffer's `int|false` "not found" answer, as a pointer or null.
     */
    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
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
}
