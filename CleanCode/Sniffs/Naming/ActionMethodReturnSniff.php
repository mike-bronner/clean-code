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
     * @var array<int, string>
     */
    public array $actionPrefixes = [
        'add',
        'apply',
        'attach',
        'clear',
        'delete',
        'detach',
        'post',
        'remove',
        'reset',
        'save',
        'send',
        'set',
        'store',
        'update',
    ];

    /**
     * Whether a fluent interface is exempt. On (the default), a method that
     * hands back its own object — `: static`, `: self`, a bare return type
     * naming the enclosing class, or a body whose every value-return is
     * `return $this;` — is not reported: chaining is a builder idiom, and
     * reporting it would bury the finding this sniff exists for under every
     * fluent setter in the codebase.
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
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isMethod($phpcsFile, $stackPtr) === false) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);
        $prefix = $name === null ? null : $this->matchedPrefix($name);

        if ($prefix === null) {
            return;
        }

        if ($this->returnsValue($phpcsFile, $stackPtr) === false) {
            return;
        }

        // Reported at the name, since the name is half of what the rule asks to
        // be changed — the other half is the return, and only the pair of them
        // is the violation. getDeclarationName() resolves the name by scanning
        // forward for the first T_STRING, so the two always agree on a token.
        $phpcsFile->addWarning(
            'The %s() method starts with the action verb "%s", so it commands rather than '
                . 'answers and should not return a value — return nothing, or rename it for '
                . 'what it hands back (see docs/standards/methods-naming.md)',
            $phpcsFile->findNext(T_STRING, $stackPtr),
            'Found',
            [$name, $prefix]
        );
    }

    /**
     * Whether the declaration is a method — that is, whether the innermost
     * scope holding it is class-like.
     *
     * Reading only the innermost scope is what tells a method apart from a
     * named function declared inside one: the nested function's conditions list
     * still holds the enclosing class, so a search for "any class-like
     * condition" would call it a method.
     */
    private function isMethod(File $phpcsFile, int $stackPtr): bool
    {
        foreach (array_reverse($phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [], true) as $code) {
            if (in_array($code, self::CLASS_LIKE_TOKENS, true) === true) {
                return true;
            }

            if (in_array($code, self::CALLABLE_TOKENS, true) === true) {
                return false;
            }
        }

        return false;
    }

    /**
     * The first configured verb the name starts with, or null when it starts
     * with none.
     *
     * The character after the verb must not be lower-case, which is what makes
     * `set` a verb in `setName()`, `set()` and `set_name()` but not in
     * `settle()`. That boundary is camelCase read backwards: a new word starts
     * at a capital, so a lower-case continuation means the verb was never a
     * word of its own.
     */
    private function matchedPrefix(string $name): ?string
    {
        foreach ($this->actionPrefixes as $prefix) {
            if ($prefix === '' || str_starts_with($name, $prefix) === false) {
                continue;
            }

            $rest = substr($name, strlen($prefix));

            if ($rest === '' || ctype_lower($rest[0]) === false) {
                return $prefix;
            }
        }

        return null;
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

        if ($declared !== '') {
            return in_array($declared, self::COMMAND_RETURN_TYPES, true) === false
                && $this->isFluentType($phpcsFile, $stackPtr, $declared) === false;
        }

        $returns = $this->valueReturns($phpcsFile, $stackPtr);

        return $returns !== [] && $this->isFluentBody($phpcsFile, $returns) === false;
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
     *
     * The comparison is against the *whole* normalised type, so only a
     * single-member one can match: `static|false` hands back either the object
     * or a value, and the value is the half this rule is about. The bare class
     * name is accepted unqualified only — a namespaced spelling cannot be
     * compared against a declaration name without resolving imports, and a
     * wrong answer there would silence a real finding.
     */
    private function isFluentType(File $phpcsFile, int $stackPtr, string $type): bool
    {
        if ($this->allowFluentInterface === false) {
            return false;
        }

        if (in_array($type, self::FLUENT_RETURN_TYPES, true) === true) {
            return true;
        }

        $className = $this->enclosingClassName($phpcsFile, $stackPtr);

        return $className !== null && $type === strtolower($className);
    }

    /**
     * The name of the innermost class-like scope holding the declaration, or
     * null when it is an anonymous class (which has no name to compare against,
     * so only `self`/`static` can express its fluent interface).
     */
    private function enclosingClassName(File $phpcsFile, int $stackPtr): ?string
    {
        foreach (array_reverse($phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [], true) as $pointer => $code) {
            if (in_array($code, self::CLASS_LIKE_TOKENS, true) === true) {
                return $phpcsFile->getDeclarationName($pointer);
            }
        }

        return null;
    }

    /**
     * Every `return <expr>;` the declaration's own body holds, as the pointer to
     * the first token of each expression.
     *
     * A `return` is this declaration's only when the innermost callable scope
     * holding it is this one, so a closure, an arrow function, a nested
     * function, and a method of an anonymous class declared inside this body all
     * keep their own returns. A bare `return;` contributes nothing: it ends the
     * method, it does not answer anything. A declaration with no body at all —
     * abstract, or an interface method — has no returns to find.
     *
     * @return array<int, int>
     */
    private function valueReturns(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return [];
        }

        $returns = [];

        for ($pointer = ($opener + 1); $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] !== T_RETURN) {
                continue;
            }

            if ($this->owningCallable($phpcsFile, $pointer) !== $stackPtr) {
                continue;
            }

            $expression = $phpcsFile->findNext(Tokens::$emptyTokens, ($pointer + 1), $closer, true);

            if ($expression === false || $tokens[$expression]['code'] === T_SEMICOLON) {
                continue;
            }

            $returns[] = $expression;
        }

        return $returns;
    }

    /**
     * The declaration a token sits inside, or null when it sits in none.
     */
    private function owningCallable(File $phpcsFile, int $stackPtr): ?int
    {
        foreach (array_reverse($phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [], true) as $pointer => $code) {
            if (in_array($code, self::CALLABLE_TOKENS, true) === true) {
                return $pointer;
            }
        }

        return null;
    }

    /**
     * Whether every value-return in the body is `return $this;`, and the
     * exemption is switched on.
     *
     * Every one of them, because a body that returns `$this` on one path and a
     * result on another is exactly the mixed command-query this rule is about.
     * Matched on token type and adjacency — a `$this` variable followed
     * immediately by the semicolon — so `$this->name`, `$this->save()` and
     * `$this ?: $other` are values built from `$this`, not `$this` itself.
     *
     * @param array<int, int> $returns
     */
    private function isFluentBody(File $phpcsFile, array $returns): bool
    {
        if ($this->allowFluentInterface === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        foreach ($returns as $expression) {
            if ($tokens[$expression]['code'] !== T_VARIABLE || $tokens[$expression]['content'] !== '$this') {
                return false;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($expression + 1), null, true);

            if ($next === false || $tokens[$next]['code'] !== T_SEMICOLON) {
                return false;
            }
        }

        return true;
    }
}
