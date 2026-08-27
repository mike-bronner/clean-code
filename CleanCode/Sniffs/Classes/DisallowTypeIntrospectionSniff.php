<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Classes: Introspection / Type Casting" standard.
 *
 * Asking an object what type it is in order to decide a branch couples the
 * caller to a concrete type that the method parameter or class property should
 * already have declared. Reaching for introspection means the logic belongs on
 * the object (polymorphism) or the type belongs in the signature.
 *
 * The sniff flags type introspection *only where it decides a branch*:
 *
 * - `instanceof`
 * - `get_class()`, `get_debug_type()`, `gettype()`, `is_a()`,
 *   `is_subclass_of()`
 *
 * used inside the condition of an `if`/`elseif`/`while`, the subject of a
 * `switch`/`match`, a `case` label, a `match` arm condition, or the condition
 * of a ternary.
 *
 * Introspection outside a branch decision is deliberately left alone — an
 * exception message, a log line, an assertion, or a `return $x instanceof Y;`
 * predicate reports a type, it does not choose behaviour based on one.
 *
 * A function body bounds the search — any function body, whether it opened with
 * `function`, a closure, `fn`, or a PHP 8.4 property hook. Introspection inside
 * one decides that body's *return value*, so it is a predicate, even when the
 * body is itself written as an argument inside some enclosing branch's
 * condition:
 *
 * - `if (array_filter($rows, fn ($r) => $r instanceof Failure))`
 * - `if (array_filter($rows, function ($r) { return $r instanceof Failure; }))`
 * - `if (array_filter($rows, new class { public function __invoke($r) {
 *   return $r instanceof Failure; } }))`
 *
 * All three pass a predicate to `array_filter()`; which keyword opened it says
 * nothing about the role the check plays. Every branch a check is measured
 * against must therefore live inside the same function body the check does.
 *
 * A bare `name(` only reaches the global function when nothing in the file
 * shadows that name — a `use function` import (aliased or not) or a function of
 * the same name declared in the file both resolve elsewhere. `name(...)` is
 * likewise not a call at all: first-class callable syntax defers a reference,
 * and the deferred check decides nothing where it is written.
 *
 * Detection only: replacing a type check with polymorphism means moving
 * behaviour onto the object (or narrowing a signature) and updating call
 * sites, so no token-based auto-fix can be applied.
 *
 * Where the token stream lacks the structure a check needs — a `match` with no
 * scope opener, an unclosed group — the sniff stays silent rather than
 * guessing. Only malformed source reaches those paths (PHPCS reports the parse
 * error itself), and a linter that invents violations there is worse than one
 * that misses them.
 */
class DisallowTypeIntrospectionSniff implements Sniff
{
    /**
     * Global functions that report the runtime type of a value. Compared
     * against the lower-cased call name — PHP function names are
     * case-insensitive.
     */
    private const INTROSPECTION_FUNCTIONS = [
        'get_class',
        'get_debug_type',
        'gettype',
        'is_a',
        'is_subclass_of',
    ];

    /**
     * Constructs whose parenthesised expression *is* the branch decision.
     *
     * `for` is deliberately absent: its parentheses hold the initialiser and
     * the increment alongside the condition, and a token-level check cannot
     * tell them apart.
     */
    private const CONDITION_OWNERS = [
        T_ELSEIF,
        T_IF,
        T_MATCH,
        T_SWITCH,
        T_WHILE,
    ];

    /**
     * Scope owners whose braced body is written where a value is expected, so
     * the expression carries on after the closing brace and a scan crosses the
     * braces whole — as it crosses a parenthesis or a bracket.
     *
     * `new class { … }`, `function () { … }` and `match (…) { … }` are the
     * three PHP has. Every other braced body — `if`, `foreach`, `try`, a named
     * function, a class — *is* a statement, and a scan crossing one would read
     * the statements after it as a continuation of the expression before it.
     * That is why braces are not simply crossed on sight: see
     * {@see opensAnExpressionBody()}.
     *
     * Braces owning no scope at all — `${$name}`, a property-hook list, a
     * trait-adaptation block — are crossed too. None of them ends an
     * expression either.
     */
    private const EXPRESSION_BODY_OWNERS = [
        T_ANON_CLASS,
        T_CLOSURE,
        T_MATCH,
    ];

    /**
     * Tokens that close the expression a forward scan started inside, without
     * that expression having turned out to be a ternary condition.
     *
     * The braces are here for the statement blocks only: an expression body's
     * braces are crossed as a balanced group before this list is consulted, so
     * a brace reaching it is one that really does end the expression.
     */
    private const EXPRESSION_TERMINATORS = [
        T_CLOSE_CURLY_BRACKET,
        T_COLON,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_FN_ARROW,
        T_INLINE_ELSE,
        T_MATCH_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_SEMICOLON,
    ];

    /**
     * Every token that declares a body PHP_CodeSniffer gives a scope of its
     * own, and whose result is that body's own return value.
     *
     * Three tokens carry a scope: `function` (a named function or a method,
     * whether declared at file scope, in a named class, or in an anonymous
     * one), `function () {}` (a closure), and `fn () =>` (an arrow function). A
     * `static` prefix changes neither token, and an abstract or interface
     * method declares no body at all — {@see buildFunctionBodies()} drops it
     * for having no scope, so the enumeration needs no case for it.
     *
     * A PHP 8.4 property hook declares such a body too and is deliberately
     * absent here: the tokenizer opens no scope for one, so it cannot be found
     * by a token code at all. {@see hookBodies()} is what adds it, and the two
     * together are the complete set.
     *
     * Enumerating the whole set is the point: an introspection check is a
     * *predicate* whenever the nearest thing its value flows into is a return,
     * and that is true of every body equally. Listing only the two callback
     * forms made the sniff flag the third — a method body written inline as an
     * argument — which is what this list being complete now prevents.
     */
    private const FUNCTION_LIKE = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    /**
     * Scopes whose `function` declarations are methods. A method never shadows
     * the resolution of a bare `name(` — PHP only consults the current
     * namespace and then the global one.
     */
    private const OO_SCOPES = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * The token stream every index below was built from, so a stream they do
     * not describe is never answered from. The indexes hold pointers into one
     * particular stream, and TokenStreams::key() — the one implementation the
     * sniffs with a per-stream index in this package share — is what tells that
     * stream from every other, including the next `phpcbf` pass over the same
     * file. A file name does not: two sources analysed as STDIN report the same
     * name, and issue #343 is the reproduction of what that costs.
     *
     * One key covers all three indexes because they describe one stream between
     * them. A key apiece bought nothing but three chances for the guards to
     * disagree.
     */
    private ?string $indexKey = null;

    /**
     * How many times the indexes were built, and how many times the key guard
     * answered a read from the indexes already built.
     *
     * The indexes exist to absorb many reads per token stream into one pass,
     * and nothing a black-box test can observe tells "built once, read n times"
     * from "rebuilt on every read": both report the same violations. These two
     * counters are what tell them apart, and
     * tests/Standards/DisallowTypeIntrospectionTest.php pins both numbers.
     *
     * Each increment sits inside the same branch as the guard it counts, so a
     * guard that stopped working cannot leave the counts intact. The totals are
     * cumulative for the life of the sniff instance and are read as a delta
     * around a single run.
     *
     * @var array<string, int>
     */
    private array $cacheCounts = [
        'indexes.builds' => 0,
        'indexes.hits' => 0,
    ];

    /**
     * The introspection function names the indexed stream shadows, or null
     * when that question has not been asked of this stream.
     *
     * Each index below is built the first time it is read rather than with the
     * others, because a file that asks one question of the sniff often asks
     * only that one: a file with no ternary anywhere never pays for
     * {@see $ternaryDecisions}, and one with no unqualified call never pays for
     * the scan behind this.
     *
     * @var array<int, string>|null
     */
    private ?array $shadowedNames = null;

    /**
     * Every function-like body in the indexed stream: the pointer that opens
     * the body mapped to the pointer that closes it, ordered by opener
     * ascending (see {@see buildFunctionBodies()}).
     *
     * @var array<int, int>|null
     */
    private ?array $functionBodies = null;

    /**
     * Each token of the indexed stream that sits inside a function-like body,
     * mapped to the opener of the innermost body holding it (see
     * {@see buildEnclosingBodies()}). A token in no body has no entry.
     *
     * @var array<int, int>|null
     */
    private ?array $enclosingBodies = null;

    /**
     * Where a forward scan from each token of the indexed stream resolves,
     * keyed by token pointer (see {@see buildTernaryDecisions()}).
     *
     * @var array<int, int>|null
     */
    private ?array $ternaryDecisions = null;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_INSTANCEOF, T_STRING];
    }

    /**
     * How many times the per-stream indexes were built and how many times the
     * key guard answered from the indexes already built, cumulative for the
     * life of this instance.
     *
     * @return array<string, int>
     */
    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_STRING) {
            $this->processIntrospectionFunction($phpcsFile, $stackPtr);

            return;
        }

        if ($this->decidesABranch($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Do not branch on `instanceof`; declare the type in the parameter or property, '
                . 'or move the behaviour onto the object',
            $stackPtr,
            'InstanceOf'
        );
    }

    /**
     * Reports a call to a type-introspection function when that call decides a
     * branch. Names that are not such a call — a method of the same name, a
     * function *declaration*, a namespaced same-name function — are ignored.
     */
    private function processIntrospectionFunction(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array(strtolower($tokens[$stackPtr]['content']), self::INTROSPECTION_FUNCTIONS, true) === false) {
            return;
        }

        if ($this->isGlobalCallAccountingForShadowing($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->decidesABranch($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Do not branch on %s; declare the type in the parameter or property, '
                . 'or move the behaviour onto the object',
            $stackPtr,
            'IntrospectionFunction',
            [$tokens[$stackPtr]['content'] . '()']
        );
    }

    /**
     * True when the name at $stackPtr is invoked as the global function of
     * that name: followed by `(`, actually calling rather than referencing,
     * and not qualified as a method, a class member, a `new` target, a
     * declaration, another namespace's function, or a name the file shadows.
     *
     * The name says what this adds rather than repeating the shared helper's,
     * because the two answer different questions and a reader has to be able to
     * tell which one a call site wants. This one composes the helper and layers
     * the same-file declaration shadow the helper documents as outside its
     * scope; it does not re-implement the helper's own check (#320).
     *
     * The first of those is {@see FunctionCalls::isGlobalFunctionCall()}'s
     * question and is asked there rather than answered again here (#320): the
     * shared helper rules out member access, declarations including
     * `function &get_class()`, instantiation however the class name is
     * qualified, `Vendor\get_class()`, an attribute name, and a bare name a
     * `use function` import redirects elsewhere. It resolves an import against
     * the namespace block the call sits in, where the file-wide approximation
     * this sniff used to carry credited a shadow to the whole file.
     *
     * Two things stay here. A first-class callable references the function
     * without calling it, so nothing is evaluated and no branch is decided by
     * it. And a function *declared in this file* shadows the global fallback
     * for a bare call — the one resolution the shared helper documents as
     * deliberately outside its scope, since answering it means reading
     * declarations across a namespace rather than reading one statement.
     */
    private function isGlobalCallAccountingForShadowing(File $phpcsFile, int $stackPtr): bool
    {
        if (FunctionCalls::isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->isFirstClassCallable($phpcsFile, $stackPtr) === true) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        // Only a *bare* name falls back to the global function, so only a bare
        // name can be shadowed by a declaration. A qualifier the helper already
        // resolved to the global namespace — `\get_class()`, or
        // `namespace\get_class()` written outside any namespace — names that
        // function explicitly and outranks whatever this file declares.
        if ($prev !== false && $tokens[$prev]['code'] === T_NS_SEPARATOR) {
            return true;
        }

        $this->index($phpcsFile);
        $this->shadowedNames ??= $this->buildShadowedNames($phpcsFile);

        return in_array(
            strtolower($tokens[$stackPtr]['content']),
            $this->shadowedNames,
            true
        ) === false;
    }

    /**
     * True when `name(...)` is PHP 8.1 first-class callable syntax: the parens
     * hold nothing but `...`, so the expression builds a `Closure` referring to
     * the function instead of invoking it. Nothing is introspected where it is
     * written — the caller that eventually invokes the reference decides that,
     * which is the same reasoning that exempts a callback predicate.
     *
     * A variadic unpack (`is_a(...$args)`) has an argument after the `...` and
     * is an ordinary call.
     */
    private function isFirstClassCallable(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openerPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);
        $closer = $openerPtr === false ? null : ($tokens[$openerPtr]['parenthesis_closer'] ?? null);

        if ($closer === null) {
            return false;
        }

        $first = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), $closer, true);

        return $first !== false
            && $tokens[$first]['code'] === T_ELLIPSIS
            && $phpcsFile->findNext(Tokens::$emptyTokens, ($first + 1), $closer, true) === false;
    }

    /**
     * The introspection function names this file *declares* as functions of its
     * own, which shadow the global fallback for a bare call to that name.
     *
     * `use function` imports used to be collected here too. They are
     * FunctionCalls::isGlobalFunctionCall()'s since #320, which resolves them
     * against the namespace block the call sits in rather than crediting the
     * whole file — strictly better on a multi-block file, and one fewer copy of
     * a shape the shared helper already answers.
     *
     * Declarations are still read file-wide, because that is the resolution the
     * shared helper documents as outside its scope. A file holding several
     * namespace blocks can therefore be credited with a shadow covering only
     * one of them, but the result is a missed report rather than a false one,
     * and PSR-1 rules the shape out anyway.
     *
     * @return array<int, string>
     */
    private function buildShadowedNames(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_FUNCTION) {
                continue;
            }

            $declared = $this->declaredFunctionName($phpcsFile, $i);

            if ($declared !== null) {
                $names[] = $declared;
            }
        }

        return array_values(array_intersect($names, self::INTROSPECTION_FUNCTIONS));
    }

    /**
     * The lower-cased name a `function` declaration at $functionPtr binds in
     * the file's own namespace, or null when it binds none there — a method
     * declares a class member, which bare-call resolution never consults.
     */
    private function declaredFunctionName(File $phpcsFile, int $functionPtr): ?string
    {
        foreach ($phpcsFile->getTokens()[$functionPtr]['conditions'] as $condition) {
            if (in_array($condition, self::OO_SCOPES, true)) {
                return null;
            }
        }

        $name = $phpcsFile->getDeclarationName($functionPtr);

        return $name === null ? null : strtolower($name);
    }

    /**
     * True when the introspection at $stackPtr decides which branch runs.
     *
     * The innermost function-like scope enclosing the token is resolved once
     * and passed to each check, which confines every one of them to that
     * scope's own body. Resolving it in a single place is deliberate: the four
     * checks look for four different constructs, and giving each its own notion
     * of where the body starts is how they came to disagree.
     *
     * The question is asked of a *position*, and `match` is what makes that
     * position move. A `match` is an expression, so it is written wherever a
     * value is — as a `switch` case label, as a ternary's condition. A token in
     * one of its arm *results* is the value the whole `match` produces, so
     * where that token decides a branch is wherever the `match` itself does.
     * The walk therefore steps outward from the token to each enclosing `match`
     * it is a result of, and re-asks {@see decidesWhereItSits()} — the two
     * questions a position answers on its own — at every position it reaches.
     * The remaining two are asked of the scope the walk is standing in.
     *
     * Reading only the innermost enclosing scope answered "the arm's own
     * result" and stopped there, which is silence on both of those shapes: the
     * innermost scope around such a token is always the `match`, never what the
     * `match` is written inside. Any scope that is not a `match` ends the walk
     * — the value stops flowing outward there.
     */
    private function decidesABranch(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $scope = $this->enclosingFunctionScope($phpcsFile, $stackPtr);
        $probe = $stackPtr;

        foreach (array_reverse($tokens[$stackPtr]['conditions'], true) as $ptr => $code) {
            if ($this->decidesWhereItSits($phpcsFile, $probe, $scope)) {
                return true;
            }

            if ($code === T_SWITCH) {
                return $this->isASwitchCaseCondition($phpcsFile, $probe, $scope);
            }

            if ($code !== T_MATCH) {
                return false;
            }

            if ($this->isAMatchArmCondition($phpcsFile, $probe, $ptr, $scope)) {
                return true;
            }

            $probe = $ptr;
        }

        return $this->decidesWhereItSits($phpcsFile, $probe, $scope);
    }

    /**
     * True when the token at $probe is written *in* a branch decision rather
     * than merely inside a construct that holds one: in the parentheses of an
     * `if`, `elseif`, `while`, `switch` or `match`, or in a ternary's condition.
     *
     * These are the two roles a token holds by where it is written, needing no
     * enclosing scope to give them, which is why the walk above asks them again
     * at every position it steps to.
     */
    private function decidesWhereItSits(File $phpcsFile, int $probe, ?array $scope): bool
    {
        return $this->isInsideAConditionParenthesis($phpcsFile, $probe, $scope)
            || $this->isATernaryCondition($phpcsFile, $probe, $scope);
    }

    /**
     * Returns the extent of the innermost function-like body containing
     * $stackPtr — the pointer that opens it and the pointer that closes it — or
     * null when the token sits in no such body.
     *
     * This is the one place the sniff decides what "the same scope" means, and
     * every branch check below is confined by its answer. Resolving it against
     * every body the file has rather than a chosen subset is what makes the
     * confinement general: a body is a body regardless of what opened it, so a
     * method written inline as an argument, and a property hook, bound the
     * search exactly as a closure or an arrow function does.
     *
     * The answer is a table lookup rather than a search. Scanning a list of
     * bodies per token was linear in the number of bodies the file declares,
     * and a class of K sibling methods each holding one check paid that K
     * times over — quadratic in K, on the ordinary shape of a large dispatcher
     * or facade. {@see buildEnclosingBodies()} resolves every token in one
     * pass instead.
     *
     * @return array{start: int, end: int}|null
     */
    private function enclosingFunctionScope(File $phpcsFile, int $stackPtr): ?array
    {
        $this->index($phpcsFile);
        $this->functionBodies ??= $this->buildFunctionBodies($phpcsFile);
        $this->enclosingBodies ??= $this->buildEnclosingBodies($phpcsFile, $this->functionBodies);
        $start = $this->enclosingBodies[$stackPtr] ?? null;

        return $start === null
            ? null
            : ['start' => $start, 'end' => $this->functionBodies[$start]];
    }

    /**
     * Discards every index that describes another token stream, so what is
     * read after this call either describes the stream being processed or has
     * not been built yet.
     *
     * One key covers every index because they describe one stream between
     * them, and each is still built only when it is first read: what the key
     * decides is which stream the indexes may describe, not which of them
     * exists.
     */
    private function index(File $phpcsFile): void
    {
        $key = TokenStreams::key($phpcsFile);

        if ($this->indexKey === $key) {
            $this->cacheCounts['indexes.hits']++;

            return;
        }

        $this->cacheCounts['indexes.builds']++;
        $this->indexKey = $key;
        $this->shadowedNames = null;
        $this->functionBodies = null;
        $this->enclosingBodies = null;
        $this->ternaryDecisions = null;
    }

    /**
     * Every function-like body in the file: its opening pointer mapped to its
     * closing one, ordered by opener ascending.
     *
     * Two kinds of body are collected, because PHP_CodeSniffer describes them
     * differently. A declaration in {@see FUNCTION_LIKE} carries its own scope
     * pointers and is read straight off the token. A PHP 8.4 property hook
     * carries none at all — the tokenizer opens no scope for one — so
     * {@see hookBodies()} reads its extent off the braces instead.
     *
     * A declaration whose scope PHPCS could not resolve is omitted rather than
     * assumed to enclose anything — an abstract or interface method (which has
     * no body) reaches this path legitimately, and malformed source reaches it
     * with a parse error PHPCS reports itself. Omitting it keeps a token that
     * follows from being read as living inside a body that never opened.
     *
     * @return array<int, int>
     */
    private function buildFunctionBodies(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $bodies = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if (in_array($tokens[$i]['code'], self::FUNCTION_LIKE, true)) {
                $opener = $tokens[$i]['scope_opener'] ?? null;
                $closer = $tokens[$i]['scope_closer'] ?? null;

                if ($opener !== null && $closer !== null) {
                    $bodies[$opener] = $closer;
                }

                continue;
            }

            if ($this->isHookList($phpcsFile, $i)) {
                $bodies += $this->hookBodies($phpcsFile, $i);
            }
        }

        ksort($bodies);

        return $bodies;
    }

    /**
     * True when the brace at $stackPtr opens the hook list of a PHP 8.4
     * property declaration.
     *
     * The tokenizer gives a hook no scope and its enclosing property no
     * condition, so a hook list is recognised by where its brace sits rather
     * than by any token of its own: directly inside a class-like body, with no
     * scope of its own. Nothing else in PHP puts a brace there except a
     * trait-adaptation block (`use A, B { … }`), which declares no body — it
     * holds `insteadof` and `as` clauses — so {@see hookBodies()} finds nothing
     * in one and no case is needed for it.
     */
    private function isHookList(File $phpcsFile, int $stackPtr): bool
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] !== T_OPEN_CURLY_BRACKET) {
            return false;
        }

        if (isset($token['bracket_closer']) === false || isset($token['scope_opener'])) {
            return false;
        }

        $conditions = $token['conditions'];

        return $conditions !== [] && in_array(end($conditions), self::OO_SCOPES, true);
    }

    /**
     * The bodies the hook list opening at $listPtr declares: each hook's own
     * body, mapped from its opening pointer to its closing one.
     *
     * A hook is written in one of two forms, and both bound a body the same
     * way a closure or an arrow function does — what is written inside decides
     * what reading or writing the property yields:
     *
     * - `get { … }` — the body is the block, `{` to `}`.
     * - `get => …;` — the body is the expression, `=>` to `;`.
     *
     * The walk steps over balanced groups whole, so neither a `=>` inside a
     * hook parameter's array default nor a brace inside an already-collected
     * body is read as opening another one. A form the walk cannot resolve —
     * an arrow hook with no `;`, a brace PHPCS never saw closed — ends the walk
     * rather than being guessed at: only malformed source reaches that, and
     * PHPCS reports the parse error itself.
     *
     * @return array<int, int>
     */
    private function hookBodies(File $phpcsFile, int $listPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$listPtr]['bracket_closer'];
        $bodies = [];
        $i = ($listPtr + 1);

        while ($i < $closer) {
            $code = $tokens[$i]['code'];

            if ($code === T_DOUBLE_ARROW || $code === T_OPEN_CURLY_BRACKET) {
                $end = $code === T_DOUBLE_ARROW
                    ? $this->endOfArrowHook($tokens, ($i + 1), $closer)
                    : ($tokens[$i]['bracket_closer'] ?? false);

                if ($end === false) {
                    break;
                }

                $bodies[$i] = $end;
                $i = ($end + 1);

                continue;
            }

            $i = ($this->skipGroupForward($tokens, $i) + 1);
        }

        return $bodies;
    }

    /**
     * The semicolon that ends the short-arrow hook whose `=>` sits just before
     * $start, or false when the hook list closes before one is written.
     *
     * The walk crosses balanced groups whole, so a semicolon belonging to a
     * *statement* inside the hook's expression — one written in a closure the
     * expression declares and calls — is not read as the hook's own
     * terminator. Taking the first semicolon at any depth ended the body early,
     * and a body ending before tokens it holds overlaps the closure's own
     * without nesting inside it, which is a shape {@see buildEnclosingBodies()}
     * cannot resolve: it pops both at the closure's brace, and every token past
     * that point resolves to whatever scope encloses the property instead of to
     * the hook.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return int|false
     */
    private function endOfArrowHook(array $tokens, int $start, int $closer)
    {
        for ($i = $start; $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_SEMICOLON) {
                return $i;
            }

            $i = $this->skipGroupForward($tokens, $i);
        }

        return false;
    }

    /**
     * Each token that sits inside a function-like body, mapped to the opener of
     * the innermost body holding it. A token in no body is absent.
     *
     * Resolved in one pass, with the bodies still open at each position held on
     * a stack: bodies nest properly — they never partially overlap — so the one
     * on top of the stack is always the innermost, and each body is pushed and
     * popped exactly once. That is what makes the whole file cost one pass
     * regardless of how many bodies it declares, where asking each token to
     * search the list of bodies cost the length of that list every time.
     *
     * A body's own opening and closing pointers are not inside it, which is the
     * containment the branch checks below are written against: a condition
     * opening a body is the caller's, not the body's.
     *
     * @param array<int, int> $bodies
     *
     * @return array<int, int>
     */
    private function buildEnclosingBodies(File $phpcsFile, array $bodies): array
    {
        $enclosing = [];
        $open = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            while ($open !== [] && $bodies[end($open)] <= $i) {
                array_pop($open);
            }

            if ($open !== []) {
                $enclosing[$i] = end($open);
            }

            if (isset($bodies[$i])) {
                $open[] = $i;
            }
        }

        return $enclosing;
    }

    /**
     * True when the token sits inside the parentheses of an `if`, `elseif`,
     * `while`, `switch`, or `match` — i.e. inside the branch condition itself,
     * at any nesting depth.
     *
     * A condition opening *before* the enclosing function-like scope belongs to
     * the code that receives that body's result, not to the body itself: the
     * token decides what the body returns, and the caller decides the branch.
     *
     * `nested_parenthesis` tracks physical paren nesting only and walks
     * straight through an intervening body, so an enclosing `if`'s opener stays
     * in the chain of a token written inside a callback — or inside an inline
     * method — that the `if` condition merely calls. The scope check is what
     * takes it back out.
     */
    private function isInsideAConditionParenthesis(
        File $phpcsFile,
        int $stackPtr,
        ?array $scope
    ): bool {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis'] ?? []) as $opener) {
            if (
                $scope !== null
                && $opener < $scope['start']
            ) {
                continue;
            }

            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if ($owner !== null && in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the token sits in the *condition* of a ternary — a `?` follows
     * it before the expression it belongs to ends.
     *
     * Balanced groups are skipped whole, so an unmatched closer means the scan
     * has stepped out to the enclosing expression and simply keeps walking
     * outward: `f(get_class($x)) ? a : b` is a ternary condition, whereas
     * `f(get_class($x), $y ? a : b)` terminates at the argument comma.
     *
     * An enclosing function-like scope caps that outward walk at its own end,
     * so a `?` belonging to the caller —
     * `array_filter($i, fn ($x) => $x instanceof Y) ? a : b` — is never
     * mistaken for the arrow function's own ternary. The cap is applied to the
     * resolved position rather than to the walk, which is the same answer: a
     * walk stopped at the cap resolves nowhere before it.
     */
    private function isATernaryCondition(File $phpcsFile, int $stackPtr, ?array $scope): bool
    {
        $tokens = $phpcsFile->getTokens();
        $limit = $scope === null ? $phpcsFile->numTokens : $scope['end'];
        $this->index($phpcsFile);
        $this->ternaryDecisions ??= $this->buildTernaryDecisions($phpcsFile);
        $decision = $this->ternaryDecisions[$stackPtr + 1] ?? $phpcsFile->numTokens;

        return $decision < $limit
            && $tokens[$decision]['code'] === T_INLINE_THEN;
    }

    /**
     * Where a forward scan starting at each token of the file resolves: the
     * position of the first `?` or {@see EXPRESSION_TERMINATORS} member it
     * reaches with balanced groups skipped whole, or the token count when it
     * reaches the end of the file having resolved nothing.
     *
     * Built in one backward pass per file and memoised, for the same reason
     * {@see functionScopes()} is. Every scan and every scan starting inside it
     * end at the same token — the walk is forward-only, so from any position it
     * passes through, the remaining walk is identical. Running it per
     * introspection token therefore re-walked the same suffix once per token:
     * on a chain the terminator list does not break — `$a instanceof X || $b
     * instanceof Y || …`, where `||` is not a boundary and each check scans on
     * to the statement's end — that is quadratic in the length of the chain,
     * and a single generated or vendored file was enough to inflate this one
     * sniff's cost superlinearly while every other sniff in the run stayed
     * flat. Resolving each position from the one it continues to makes the
     * whole file cost one pass.
     *
     * The pass runs backward because that is the direction the answers are
     * already known in: the position a token continues to is always after it,
     * so it has been resolved by the time the token is reached.
     *
     * @return array<int, int>
     */
    private function buildTernaryDecisions(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $decisions = [];

        for ($i = ($phpcsFile->numTokens - 1); $i >= 0; $i--) {
            $code = $tokens[$i]['code'];
            $group = $this->skipGroupForward($tokens, $i);

            if (
                $group === $i
                && ($code === T_INLINE_THEN || in_array($code, self::EXPRESSION_TERMINATORS, true))
            ) {
                $decisions[$i] = $i;

                continue;
            }

            $decisions[$i] = $decisions[$group + 1] ?? $phpcsFile->numTokens;
        }

        return $decisions;
    }

    /**
     * True when the token sits in a `match` arm's condition rather than its
     * result.
     *
     * Walking back from the token to the match's `{`, the nearest `=>` ends
     * the previous arm — unless a comma separates it from the token, which
     * means a new arm's condition list has started (and commas *within* one
     * arm's condition list keep the flag set, so multi-condition arms are
     * covered). Reaching `{` with no `=>` behind is the first arm.
     *
     * A function-like body opening inside the `match` holds the token in its
     * own body, so the arm boundaries around it are the caller's, not the
     * token's.
     */
    private function isAMatchArmCondition(
        File $phpcsFile,
        int $stackPtr,
        int $matchPtr,
        ?array $scope
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $scopeOpener = $tokens[$matchPtr]['scope_opener'] ?? null;

        if ($scopeOpener === null) {
            return false;
        }

        if (
            $scope !== null
            && $scope['start'] > $scopeOpener
        ) {
            return false;
        }

        $passedComma = false;

        for ($i = ($stackPtr - 1); $i > $scopeOpener; $i--) {
            $code = $tokens[$i]['code'];

            if ($code === T_MATCH_ARROW) {
                return $passedComma;
            }

            if ($code === T_COMMA) {
                $passedComma = true;
            }

            $i = $this->skipGroupBackward($tokens, $i);
        }

        return true;
    }

    /**
     * True when the token sits in a `switch` case *label* rather than a case
     * body — walking back reaches `case` before the label's `:` or any
     * statement boundary.
     *
     * An enclosing function-like scope floors that walk: a `case` further back
     * than that body's own opening labels the caller's branch, not the token's.
     */
    private function isASwitchCaseCondition(File $phpcsFile, int $stackPtr, ?array $scope): bool
    {
        $tokens = $phpcsFile->getTokens();
        $floor = $scope === null ? 0 : $scope['start'];

        for ($i = ($stackPtr - 1); $i > $floor; $i--) {
            $group = $this->skipGroupBackward($tokens, $i);

            if ($group !== $i) {
                $i = $group;

                continue;
            }

            $code = $tokens[$i]['code'];

            if ($code === T_CASE) {
                return true;
            }

            if (in_array($code, [T_CLOSE_CURLY_BRACKET, T_COLON, T_OPEN_CURLY_BRACKET, T_SEMICOLON], true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Returns the pointer a forward scan should continue from: the closer of a
     * balanced group opening at $stackPtr, or $stackPtr itself.
     *
     * Every balanced group PHP_CodeSniffer links is crossed here. Parentheses
     * carry `parenthesis_opener`/`parenthesis_closer`; square brackets, short
     * arrays *and braces* carry `bracket_opener`/`bracket_closer` — a brace
     * carries them whether or not it also owns a scope, so a brace is a group
     * on exactly the same terms as the other three, and was the one kind this
     * scan broke on instead of crossing. Nothing else is a group: an attribute
     * (`attribute_opener`/`attribute_closer`) holds a constant expression,
     * where neither an introspection call nor `instanceof` can be written, and
     * `use Foo\{A, B}` and a backtick string are linked by no pointers at all —
     * each a statement of its own, which a scan reaches only past the semicolon
     * that ended the one before it.
     *
     * A brace is crossed only when it opens an expression's own body
     * ({@see opensAnExpressionBody()}).
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function skipGroupForward(array $tokens, int $stackPtr): int
    {
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_OPEN_PARENTHESIS && isset($tokens[$stackPtr]['parenthesis_closer'])) {
            return $tokens[$stackPtr]['parenthesis_closer'];
        }

        if ($code === T_OPEN_CURLY_BRACKET) {
            return $this->opensAnExpressionBody($tokens, $stackPtr)
                ? $tokens[$stackPtr]['bracket_closer']
                : $stackPtr;
        }

        if (
            ($code === T_OPEN_SHORT_ARRAY || $code === T_OPEN_SQUARE_BRACKET)
            && isset($tokens[$stackPtr]['bracket_closer'])
        ) {
            return $tokens[$stackPtr]['bracket_closer'];
        }

        return $stackPtr;
    }

    /**
     * Returns the pointer a backward scan should continue from: the opener of
     * a balanced group closing at $stackPtr, or $stackPtr itself.
     *
     * The same set of groups {@see skipGroupForward()} crosses, read from the
     * other end.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function skipGroupBackward(array $tokens, int $stackPtr): int
    {
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_CLOSE_PARENTHESIS && isset($tokens[$stackPtr]['parenthesis_opener'])) {
            return $tokens[$stackPtr]['parenthesis_opener'];
        }

        if ($code === T_CLOSE_CURLY_BRACKET) {
            $opener = $tokens[$stackPtr]['bracket_opener'] ?? null;

            return $opener !== null && $this->opensAnExpressionBody($tokens, $opener)
                ? $opener
                : $stackPtr;
        }

        if (
            ($code === T_CLOSE_SHORT_ARRAY || $code === T_CLOSE_SQUARE_BRACKET)
            && isset($tokens[$stackPtr]['bracket_opener'])
        ) {
            return $tokens[$stackPtr]['bracket_opener'];
        }

        return $stackPtr;
    }

    /**
     * True when the brace at $opener opens a body written inside an expression
     * — one of {@see EXPRESSION_BODY_OWNERS}, or a braced group owning no scope
     * at all — rather than a statement block.
     *
     * An unbalanced brace answers false: with no closer there is no group to
     * cross, and a scan that guessed where one ended would answer from tokens
     * belonging to something else. That is the same silence the rest of the
     * sniff keeps on a stream PHPCS could not link.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function opensAnExpressionBody(array $tokens, int $opener): bool
    {
        if (isset($tokens[$opener]['bracket_closer']) === false) {
            return false;
        }

        $owner = $tokens[$opener]['scope_condition'] ?? null;

        return $owner === null
            || in_array($tokens[$owner]['code'], self::EXPRESSION_BODY_OWNERS, true);
    }
}
