<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Collections;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids generic PHP array/string functions applied to a Collection.
 *
 * Collections ship optimized equivalents for every manipulation the native
 * functions perform, so reaching for `array_map()`/`count()`/`in_array()` on a
 * Collection re-couples the code to the PHP implementation the framework
 * deliberately abstracts. See docs/standards/collections-only-use-collection-methods.md.
 *
 * A value is treated as a Collection when it is *statically* provable from the
 * tokens alone — a `collect()` call, a `Collection::make()`/`::wrap()` factory
 * call, a `new Collection(...)`, a parameter type-hinted as a Collection, or a
 * variable *unconditionally* assigned one of those. Anything the tokens cannot
 * prove is left alone: this sniff never guesses, because a false accusation
 * trains people to ignore the rule — and a false accusation that `phpcbf` then
 * acts on rewrites working code into a fatal.
 *
 * That posture is enforced by two rules, and everything else here follows from
 * them:
 *
 * - **A name is tracked only when every binding of it in the scope proved a
 *   Collection** (see mapCollectionVariables()). Retirement is the default for
 *   any construct that binds a name and cannot be read, and it applies to the
 *   whole scope rather than from the failing binding onwards.
 * - **The fixer only rewrites a receiver the tokens prove outright** (see
 *   isFixable()). Where the sniff has inferred a type rather than proved one —
 *   through TERMINAL_METHODS, or across a call that may take the variable by
 *   reference — the finding is reported and left alone. An inference good
 *   enough for a warning is not good enough to rewrite source.
 */
class OnlyUseCollectionMethodsSniff implements Sniff
{
    /**
     * Generic PHP functions that have a direct Collection equivalent, mapped to
     * the Collection method that replaces them.
     */
    private const GENERIC_FUNCTIONS = [
        'array_diff' => 'diff',
        'array_filter' => 'filter',
        'array_intersect' => 'intersect',
        'array_key_exists' => 'has',
        'array_keys' => 'keys',
        'array_map' => 'map',
        'array_merge' => 'merge',
        'array_reduce' => 'reduce',
        'array_search' => 'search',
        'array_slice' => 'slice',
        'array_sum' => 'sum',
        'array_unique' => 'unique',
        'array_values' => 'values',
        'count' => 'count',
        'implode' => 'implode',
        'in_array' => 'contains',
        'join' => 'implode',
    ];

    /**
     * Functions whose replacement is a 1:1 method swap and therefore safe to
     * auto-fix: the Collection is the only argument, the Collection method
     * takes none, and the return type is unchanged (`count()` and
     * `array_sum()` both yield the same scalar their method equivalent does).
     *
     * Everything else in GENERIC_FUNCTIONS stays detection-only, because the
     * swap needs semantic judgement a fixer cannot make — argument order
     * changes (`array_map($fn, $c)` → `$c->map($fn)`), flags that alter
     * behaviour (`in_array()`'s `$strict`, `array_filter()`'s `$mode`), or a
     * changed return type (`array_keys()` returns an array, `keys()` returns a
     * Collection).
     *
     * Membership here is necessary but not sufficient: isFixable() also
     * requires the Collection to be the call's only argument and to be an
     * unchained origin, so the fixer never acts on a type inferred through
     * TERMINAL_METHODS.
     */
    private const FIXABLE_FUNCTIONS = [
        'array_sum',
        'count',
    ];

    /**
     * Collection methods that provably hand back another Collection, used to
     * type a *chained* receiver for the fixer and nothing else.
     *
     * This list and TERMINAL_METHODS answer opposite questions and fail in
     * opposite directions, which is the whole point of keeping both. Reporting
     * asks "did this chain stop being a Collection?" and consults
     * TERMINAL_METHODS, which fails open: a method it has never heard of is
     * assumed to keep the chain alive, so an omission costs a spurious error
     * and never a missed one. Fixing asks the stronger question "is this chain
     * still a Collection *for certain*?" and consults this list, which fails
     * closed: a method it has never heard of ends provability, so an omission
     * costs a declined fix and never a rewrite.
     *
     * Round 2 weighed inverting TERMINAL_METHODS to an allowlist and rejected
     * it, correctly — for the *report*, where fail-closed would silently stop
     * detecting as Laravel adds methods. That argument does not reach the
     * fixer, whose failure mode is a runtime fatal rather than a missed
     * warning, so the polarity that is wrong for one path is right for the
     * other.
     *
     * Entries are confined to methods whose Collection return is part of the
     * documented contract and stated as `static`/`self` on Illuminate's own
     * Enumerable — never one that returns an item, a scalar, or a plain array.
     * A method carrying arity overloads that can change its return type
     * (`implode()`, `get()`, `random()`, `search()`) is deliberately absent.
     */
    private const CHAINABLE_METHODS = [
        'diff' => true,
        'except' => true,
        'filter' => true,
        'flatten' => true,
        'flip' => true,
        'intersect' => true,
        'keys' => true,
        'map' => true,
        'merge' => true,
        'only' => true,
        'pluck' => true,
        'reject' => true,
        'reverse' => true,
        'slice' => true,
        'sort' => true,
        'sortby' => true,
        'sortbydesc' => true,
        'sortdesc' => true,
        'take' => true,
        'unique' => true,
        'values' => true,
        'where' => true,
        'wherein' => true,
        'wherenotin' => true,
    ];

    /**
     * Static factory methods that produce a Collection from a class whose name
     * ends in "Collection".
     */
    private const FACTORY_METHODS = [
        'make',
        'wrap',
    ];

    /**
     * Collection methods that return something *other* than a Collection, so a
     * chain ending in one of them is no longer a Collection and calls wrapping
     * it are not violations (`count($collection->toArray())` is plain-array
     * code).
     *
     * Keys are lower-cased for case-insensitive comparison; each value records
     * what the method returns, so the list's intent stays legible to whoever
     * extends it. The two ways to get an entry wrong are not symmetrical:
     *
     * - **Omitting** a method that returns a non-Collection makes the sniff
     *   treat its result as a Collection — a false positive on that chain.
     * - **Listing** a method that returns a non-Collection only for *some*
     *   arguments (`pop()`, `shift()`, `random()` and `find()` all hand back a
     *   Collection when given a count or a list of keys) makes the sniff stay
     *   silent on the Collection form — a false negative, which is the
     *   direction this sniff fails in by design. Those entries are marked
     *   "argument-dependent" below.
     *
     * This list is a hand-curated mirror of a third-party API that changes
     * without us, so it will always be *somewhat* stale — which is why the
     * fixer no longer depends on it. `isFixable()` requires a receiver the
     * tokens prove outright (a variable, `collect()`, a factory call, `new`),
     * never one typed through this list, so an omission costs a spurious
     * warning rather than a rewrite into a runtime fatal. Keeping the list
     * current still matters for report quality; it is just no longer load
     * bearing for correctness. `OnlyUseCollectionMethodsTest` pins the exact
     * key set, so any edit here is a deliberate one.
     *
     * Methods that return `$this` (`each()`, `push()`, `tap()`, `dump()`, …)
     * are deliberately absent: the chain is still a Collection after them.
     */
    private const TERMINAL_METHODS = [
        'after' => 'mixed — the item following the given one',
        'all' => 'array',
        'average' => 'int|float|null',
        'avg' => 'int|float|null',
        'before' => 'mixed — the item preceding the given one',
        'contains' => 'bool',
        'containsoneitem' => 'bool',
        'containsstrict' => 'bool',
        'count' => 'int',
        'doesntcontain' => 'bool',
        'every' => 'bool',
        'find' => 'mixed, or a Collection when given a list of keys — argument-dependent (Eloquent)',
        'first' => 'mixed',
        'firstorfail' => 'mixed',
        'firstwhere' => 'mixed',
        'get' => 'mixed',
        'getiterator' => 'Traversable',
        'getorput' => 'mixed',
        'has' => 'bool',
        'hasany' => 'bool',
        'implode' => 'string',
        'isempty' => 'bool',
        'isnotempty' => 'bool',
        'join' => 'string',
        'jsonserialize' => 'array',
        'last' => 'mixed',
        'max' => 'mixed',
        'median' => 'int|float|null',
        'min' => 'mixed',
        'mode' => 'array|null',
        'modelkeys' => 'array (Eloquent)',
        'offsetexists' => 'bool',
        'offsetget' => 'mixed',
        'offsetset' => 'void',
        'offsetunset' => 'void',
        'percentage' => 'float|null',
        'pipe' => 'mixed — the callback\'s return; argument-dependent',
        'pipeinto' => 'object',
        'pipethrough' => 'mixed — the last callback\'s return; argument-dependent',
        'pop' => 'mixed, or a Collection when given $count — argument-dependent',
        'pull' => 'mixed',
        'random' => 'mixed, or a Collection when given $number — argument-dependent',
        'reduce' => 'mixed — the callback\'s return; argument-dependent',
        'reducespread' => 'array',
        'reducewithkeys' => 'mixed — delegates to reduce(); argument-dependent',
        'search' => 'int|string|false',
        'shift' => 'mixed, or a Collection when given $count — argument-dependent',
        'sole' => 'mixed',
        'some' => 'bool',
        'sum' => 'int|float',
        'toarray' => 'array',
        'tojson' => 'string',
        'toquery' => 'Builder (Eloquent)',
        'unless' => 'mixed — the callback\'s return, else $this; argument-dependent',
        'unlessempty' => 'mixed — alias of whenNotEmpty(); argument-dependent',
        'unlessnotempty' => 'mixed — alias of whenEmpty(); argument-dependent',
        'value' => 'mixed',
        'when' => 'mixed — the callback\'s return, else $this; argument-dependent',
        'whenempty' => 'mixed — the callback\'s return, else $this; argument-dependent',
        'whennotempty' => 'mixed — the callback\'s return, else $this; argument-dependent',
    ];

    /**
     * Tokens that, when directly preceding the function name, mean this is not
     * a global function call (method call, static call, declaration, …).
     */
    private const NON_FUNCTION_CALL_PRECEDERS = [
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    /**
     * The class aliases the file's `use` imports introduce, as lower-cased
     * alias => imported short name (`use …\Collection as Coll` gives
     * `coll => Collection`). Held as state rather than threaded through every
     * name check; rebuilt at the top of each process() call, because PHPCS
     * reuses one sniff instance for the whole run.
     *
     * @var array<string, string>
     */
    private array $importAliases = [];

    /**
     * The lower-cased local names the file's `use function` imports bind, as
     * name => imported short name. An unqualified call to one of these is the
     * imported function, not the builtin it shadows: `use function
     * App\Support\countDistinctTags as count;` makes a bare `count($c)` a call
     * to `countDistinctTags()`, so rewriting it to `$c->count()` would silently
     * change the answer. A fully-qualified `\count()` is unaffected — the
     * leading separator pins it to the global function.
     *
     * @var array<string, string>
     */
    private array $functionImports = [];

    /**
     * Variables handed bare to a call the sniff cannot prove takes them by
     * value, as scope => name => true. PHP lets any callee declare a parameter
     * `&$x` and rebind the caller's variable through it, and neither a userland
     * signature nor the by-reference builtins are knowable from this file's
     * tokens.
     *
     * Rather than mirror PHP's by-reference builtins — a moving third-party API
     * whose every omission would be a false positive — the sniff treats a bare
     * variable handed to any *other* call as no longer provably unmutated, and
     * collapses the severity: such a call is still reported, but never fixed.
     * The seventeen functions this sniff reports on all take their arguments by
     * value, so `count($c)` itself never escapes its own receiver.
     *
     * @var array<int, array<string, bool>>
     */
    private array $escapedVariables = [];

    /**
     * The file's arrow functions, as the token range of each one's body plus
     * the parameters it declares, mapped to whether each is a Collection.
     *
     * Arrow functions need a table of their own because PHPCS does not record
     * T_FN in a token's `conditions` (unlike T_CLOSURE and T_FUNCTION), so
     * containment cannot be read off the token the way scopeOf() reads it for
     * the other two — it has to come from the T_FN's own scope range.
     *
     * Built in T_FN order, so for nested arrow functions the innermost match is
     * the last one. Rebuilt at the top of each process() call, because PHPCS
     * reuses one sniff instance for the whole run.
     *
     * @var array<int, array{start: int, end: int, parameters: array<string, bool>}>
     */
    private array $arrowFunctions = [];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    /**
     * Runs once per file: the variable map has to exist before any call site is
     * judged, so the whole file is walked here rather than per call token.
     *
     * @param int $stackPtr
     *
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        // Order matters: the alias map decides what counts as a Collection type
        // hint, which the arrow-function table records, which the variable map
        // consults.
        [$this->importAliases, $this->functionImports] = $this->mapImports($phpcsFile);
        $this->arrowFunctions = $this->mapArrowFunctions($phpcsFile);
        $this->escapedVariables = $this->mapEscapedVariables($phpcsFile);

        $this->flagGenericCalls($phpcsFile, $this->mapCollectionVariables($phpcsFile));

        return $phpcsFile->numTokens;
    }

    /**
     * Records every arrow function's body range and the parameters it declares.
     *
     * An arrow function auto-captures the enclosing scope, so a name it does
     * *not* declare is the enclosing scope's — but a parameter it declares is a
     * new binding that shadows the outer name, in an arrow function exactly as
     * in a closure. Both directions are recorded here (`false` for a parameter
     * that is not a Collection), because a parameter shadowing an enclosing
     * Collection has to stop the outward lookup rather than fall through it.
     *
     * @return array<int, array{start: int, end: int, parameters: array<string, bool>}>
     */
    private function mapArrowFunctions(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $arrowFunctions = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_FN) {
                continue;
            }

            // An unterminated arrow function is not analysed rather than
            // guessed at.
            if (isset($tokens[$ptr]['scope_opener'], $tokens[$ptr]['scope_closer']) === false) {
                continue;
            }

            $parameters = [];

            foreach ($phpcsFile->getMethodParameters($ptr) as $parameter) {
                $parameters[$parameter['name']] = $this->isCollectionHint($parameter['type_hint']);
            }

            $arrowFunctions[] = [
                'start' => $tokens[$ptr]['scope_opener'],
                'end' => $tokens[$ptr]['scope_closer'],
                'parameters' => $parameters,
            ];
        }

        return $arrowFunctions;
    }

    /**
     * Maps the file's imports by the name the code actually uses, so an aliased
     * import is judged by what it names rather than by its alias.
     *
     * Class imports decide what counts as a Collection: `use …\Collection as
     * Coll` makes `Coll::make()` a Collection, and `use …\Arr as
     * RowCollection` stops `RowCollection::wrap()` looking like one.
     *
     * Function imports decide what counts as a *builtin*: an unqualified name a
     * `use function` import has bound is that function, not the global one it
     * shadows, so the sniff must leave it alone.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     *     the class aliases and the function imports, both keyed by lower-cased
     *     local name
     */
    private function mapImports(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $classes = [];
        $functions = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_USE) {
                continue;
            }

            $kind = $this->importKind($phpcsFile, $ptr);
            $end = $kind === null ? null : $this->statementEnd($phpcsFile, ($ptr + 1));

            if ($end === null) {
                continue;
            }

            $names = [];
            $this->collectImportAliases($phpcsFile, ($ptr + 1), ($end - 1), $names);

            if ($kind === 'function') {
                $functions = array_merge($functions, $names);
            } else {
                $classes = array_merge($classes, $names);
            }

            $ptr = $end;
        }

        return [$classes, $functions];
    }

    /**
     * What the T_USE at $usePtr imports — 'class' or 'function' — or null when
     * it imports nothing the sniff cares about: a trait `use`, a closure's
     * capture list, or a `use const`.
     */
    private function importKind(File $phpcsFile, int $usePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        // A trait `use` lives inside a class body. A braced namespace is the
        // only enclosing construct an import can legitimately sit in.
        foreach ($tokens[$usePtr]['conditions'] ?? [] as $code) {
            if ($code !== T_NAMESPACE) {
                return null;
            }
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($usePtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
            return null;
        }

        // Matched on content: `function`/`const` after `use` tokenise
        // differently across PHPCS versions, but never read otherwise.
        return match (strtolower($tokens[$next]['content'])) {
            'const' => null,
            'function' => 'function',
            default => 'class',
        };
    }

    /**
     * Records every alias in one import statement spanning [$start, $end],
     * including the grouped form (`use A\{B, C as D};`). Only the trailing name
     * segment matters, so the shared prefix needs no special handling.
     *
     * @param array<string, string> $aliases
     */
    private function collectImportAliases(File $phpcsFile, int $start, int $end, array &$aliases): void
    {
        $tokens = $phpcsFile->getTokens();
        $imported = '';
        $alias = '';
        $isAlias = false;

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            if ($tokens[$ptr]['code'] === T_AS) {
                $isAlias = true;

                continue;
            }

            if ($tokens[$ptr]['code'] === T_STRING && $isAlias === true) {
                $alias = $tokens[$ptr]['content'];

                continue;
            }

            if ($tokens[$ptr]['code'] === T_STRING) {
                $imported = $tokens[$ptr]['content'];

                continue;
            }

            if ($tokens[$ptr]['code'] !== T_COMMA) {
                continue;
            }

            $this->recordImportAlias($imported, $alias, $aliases);
            $imported = '';
            $alias = '';
            $isAlias = false;
        }

        $this->recordImportAlias($imported, $alias, $aliases);
    }

    /**
     * @param array<string, string> $aliases
     */
    private function recordImportAlias(string $imported, string $alias, array &$aliases): void
    {
        if ($imported === '') {
            return;
        }

        // Class names are case-insensitive in PHP, so the lookup key is too.
        $aliases[strtolower($alias === '' ? $imported : $alias)] = $imported;
    }

    /**
     * Maps every variable that provably holds a Collection to the function
     * scope it belongs to (0 for file scope), so a name used for an array in
     * one function is not mistaken for the Collection of the same name in
     * another.
     *
     * The map is consulted *position-insensitively*: flagGenericCalls() judges
     * every call site in a scope against the finished map, not against the
     * state of the walk at that line. Two rules follow from that, and together
     * they are what keeps the fixer off code it cannot prove:
     *
     * - **Retirement is the default.** Every construct that binds a name and
     *   cannot be proved to bind a Collection retires the name. An assignment
     *   target the sniff cannot fully parse retires too — it never steps over
     *   one, because stepping over leaves the *previous* binding standing and
     *   the fixer then trusts it.
     * - **Retirement is sticky.** A name is tracked only if *every* binding of
     *   it in the scope proved a Collection. Retiring only from that point on
     *   would leave `$c = [1, 2]; count($c); $c = collect([1, 2]);` flagging —
     *   and rewriting — a call that operates on the array.
     *
     * @return array<int, array<string, bool>>
     */
    private function mapCollectionVariables(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $variables = [];
        $retired = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            // Arrow-function parameters are deliberately absent here: they bind
            // in the arrow function, not in the scope around it, so they live
            // in $this->arrowFunctions instead (see mapArrowFunctions()).
            if (in_array($tokens[$ptr]['code'], [T_CLOSURE, T_FUNCTION], true) === true) {
                $this->addTypeHintedParameters($phpcsFile, $ptr, $variables);
                $this->retireReferenceCaptures($phpcsFile, $ptr, $variables, $retired);

                continue;
            }

            if (in_array($tokens[$ptr]['code'], [T_CATCH, T_FOREACH], true) === true) {
                $this->retireBoundClause($phpcsFile, $ptr, $variables, $retired);

                continue;
            }

            if (in_array($tokens[$ptr]['code'], [T_GLOBAL, T_STATIC], true) === true) {
                $this->retireDeclaredVariables($phpcsFile, $ptr, $variables, $retired);

                continue;
            }

            if (isset(Tokens::$assignmentTokens[$tokens[$ptr]['code']]) === true) {
                $this->recordAssignment($phpcsFile, $ptr, $variables, $retired);
            }
        }

        return $this->withoutRetired($variables, $retired);
    }

    /**
     * Records what the assignment operator at $ptr binds: a Collection the
     * tokens prove, or — in every other case — a retirement.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function recordAssignment(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $target = $phpcsFile->findStartOfStatement($ptr);

        // Destructuring binds several names at once out of an expression whose
        // shape the sniff does not model, so every target it names is retired.
        if (in_array($tokens[$target]['code'], [T_LIST, T_OPEN_SHORT_ARRAY], true) === true) {
            $closer = $tokens[$target]['bracket_closer'] ?? $tokens[$target]['parenthesis_closer'] ?? null;

            if ($closer !== null) {
                $this->retireRange($phpcsFile, $target, $closer, $variables, $retired);
            }

            return;
        }

        // Anything other than a lone local variable on the left — a property
        // write, an index write, a static property — is a target the sniff
        // cannot fully parse. The name the target *starts* with is the one
        // being written through, so that is the one retired.
        if (
            $this->isLocalVariableTarget($phpcsFile, $target) === false
            || $phpcsFile->findNext(Tokens::$emptyTokens, ($target + 1), $ptr, true) !== false
        ) {
            $this->retireVariable($phpcsFile, $target, $variables, $retired);

            return;
        }

        $scope = $this->scopeOf($phpcsFile, $target);
        $name = $tokens[$target]['content'];

        // A compound assignment (`.=`, `+=`, `??=`) combines the name's own
        // prior value with something else; only a plain `=` states outright
        // what the name now holds.
        //
        // A conditional assignment says nothing about what the variable holds
        // at the call site either: the branch may not have run, and the branch
        // next door may assign something else entirely. Rather than let the
        // textually last write win — which would flag, and offer to "fix", code
        // that is correct at runtime — the name is retired.
        if (
            $tokens[$ptr]['code'] !== T_EQUAL
            || $this->isUnconditionalAssignment($phpcsFile, $target, $scope) === false
        ) {
            $this->retire($scope, $name, $variables, $retired);

            return;
        }

        $end = $this->statementEnd($phpcsFile, ($ptr + 1));

        // An unterminated statement is an expression the sniff cannot read, so
        // it retires rather than leaves the previous binding standing.
        if (
            $end === null
            || $this->isCollectionExpression($phpcsFile, ($ptr + 1), ($end - 1), $variables) === false
        ) {
            $this->retire($scope, $name, $variables, $retired);

            return;
        }

        $variables[$scope][$name] = true;
    }

    /**
     * Retires every name a `foreach` or `catch` clause binds.
     *
     * `foreach` binds everything after its `as` — value, key, and destructuring
     * targets alike — and nothing before it, so the collection being iterated
     * keeps its tracking. `catch` binds the one variable it names.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retireBoundClause(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$ptr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return;
        }

        if ($tokens[$ptr]['code'] === T_FOREACH) {
            $opener = $phpcsFile->findNext(T_AS, ($opener + 1), $closer);

            if ($opener === false) {
                return;
            }
        }

        $this->retireRange($phpcsFile, $opener, $closer, $variables, $retired);
    }

    /**
     * Retires every name a `global` or `static` declaration binds. Both rebind
     * an already-assigned local — the name stops referring to whatever was
     * assigned to it and starts referring to the global, or to the function's
     * own static.
     *
     * `static` is only such a declaration when a variable follows it:
     * `static function`, `static::`, `static fn` and a static closure all reuse
     * the keyword and bind nothing.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retireDeclaredVariables(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] !== T_VARIABLE) {
            return;
        }

        $end = $this->statementEnd($phpcsFile, $next);

        if ($end !== null) {
            $this->retireRange($phpcsFile, $next, $end, $variables, $retired);
        }
    }

    /**
     * Retires every name a closure captures by reference, in the scope the
     * closure sits in. The callee is right there in the same file, but *when*
     * it runs is not: a `use (&$rows)` closure invoked later rebinds `$rows` at
     * a point no token records, so the capture alone retires the name.
     *
     * By-value captures bind a copy and leave the outer name alone.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retireReferenceCaptures(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;
        $bodyStart = $tokens[$ptr]['scope_opener'] ?? null;

        if ($closer === null || $bodyStart === null) {
            return;
        }

        $use = $phpcsFile->findNext(T_USE, ($closer + 1), $bodyStart);

        if ($use === false) {
            return;
        }

        $captureOpener = $phpcsFile->findNext(Tokens::$emptyTokens, ($use + 1), $bodyStart, true);
        $captureCloser = $captureOpener === false
            ? null
            : ($tokens[$captureOpener]['parenthesis_closer'] ?? null);

        if ($captureCloser === null) {
            return;
        }

        // The capture list sits outside the closure body, so scopeOf() on a
        // name in it already resolves to the enclosing scope — the one the
        // rebinding will be seen from.
        for ($current = $captureOpener; $current <= $captureCloser; $current++) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($current - 1), $captureOpener, true);

            if (
                $tokens[$current]['code'] === T_VARIABLE
                && $previous !== false
                && $tokens[$previous]['code'] === T_BITWISE_AND
            ) {
                $this->retireVariable($phpcsFile, $current, $variables, $retired);
            }
        }
    }

    /**
     * Retires every variable named in the inclusive token range.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retireRange(File $phpcsFile, int $start, int $end, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            if ($tokens[$ptr]['code'] === T_VARIABLE) {
                $this->retireVariable($phpcsFile, $ptr, $variables, $retired);
            }
        }
    }

    /**
     * Retires the variable at $ptr in the scope it belongs to. A name reached
     * through `::` is a static property, not a local, and retiring it would
     * poison the unrelated local that happens to share its name.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retireVariable(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$ptr]['code'] !== T_VARIABLE || $this->isLocalVariableTarget($phpcsFile, $ptr) === false) {
            return;
        }

        $this->retire($this->scopeOf($phpcsFile, $ptr), $tokens[$ptr]['content'], $variables, $retired);
    }

    /**
     * Retires $name in $scope, both for the rest of this walk and for the
     * finished map. Dropping it from the live map matters as much as recording
     * it: an assignment further down that reads the name (`$copy = $rows;`)
     * must not inherit a binding that a construct above already invalidated.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retire(int $scope, string $name, array &$variables, array &$retired): void
    {
        unset($variables[$scope][$name]);

        $retired[$scope][$name] = true;
    }

    /**
     * Drops every retired name from the finished map, so a name that any
     * binding failed to prove is untracked throughout its scope rather than
     * from the failing binding onwards.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     *
     * @return array<int, array<string, bool>>
     */
    private function withoutRetired(array $variables, array $retired): array
    {
        foreach ($retired as $scope => $names) {
            if (isset($variables[$scope]) === true) {
                $variables[$scope] = array_diff_key($variables[$scope], $names);
            }
        }

        return $variables;
    }

    /**
     * Maps every variable handed bare to a call the sniff cannot prove takes it
     * by value, as scope => name => true. See $escapedVariables for why the
     * result collapses the severity of a call rather than silencing it.
     *
     * @return array<int, array<string, bool>>
     */
    private function mapEscapedVariables(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $escaped = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_OPEN_PARENTHESIS || isset($tokens[$ptr]['parenthesis_closer']) === false) {
                continue;
            }

            if ($this->isByValueCallOpener($phpcsFile, $ptr) === true) {
                continue;
            }

            foreach ($this->argumentRanges($phpcsFile, $ptr) as $argument) {
                $variable = $this->bareVariable($phpcsFile, $argument[0], $argument[1]);

                if ($variable !== null) {
                    $escaped[$this->scopeOf($phpcsFile, $variable)][$tokens[$variable]['content']] = true;
                }
            }
        }

        return $escaped;
    }

    /**
     * Whether the parenthesis at $ptr is one whose arguments provably cannot be
     * rebound: a parameter list rather than a call, or a call to one of the
     * seventeen functions this sniff reports on — all of which take their
     * arguments by value, which is what keeps `count($c)` from escaping its own
     * receiver.
     */
    private function isByValueCallOpener(File $phpcsFile, int $ptr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $callee = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

        if ($callee === false || in_array($tokens[$callee]['code'], [T_STRING, T_VARIABLE], true) === false) {
            return true;
        }

        // A declaration's parameter list, not a call: its variables are the
        // callee's own, and addTypeHintedParameters() has already judged them.
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($callee - 1), null, true);

        if ($before !== false && in_array($tokens[$before]['code'], [T_FN, T_FUNCTION], true) === true) {
            return true;
        }

        return isset(self::GENERIC_FUNCTIONS[strtolower($tokens[$callee]['content'])]);
    }

    /**
     * The variable the inclusive token range consists of, or null when the
     * range is anything other than a single bare variable. Only a bare variable
     * can be passed by reference; `f($c->all())` and `f([$c])` hand over a
     * value, which no callee can rebind.
     */
    private function bareVariable(File $phpcsFile, int $start, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $start, ($end + 1), true);

        if ($first === false || $tokens[$first]['code'] !== T_VARIABLE) {
            return null;
        }

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($first + 1), ($end + 1), true) === false ? $first : null;
    }

    /**
     * Registers a function's or closure's parameters that are type-hinted as a
     * Collection against its own scope.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function addTypeHintedParameters(File $phpcsFile, int $functionPtr, array &$variables): void
    {
        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            if ($this->isCollectionHint($parameter['type_hint']) === true) {
                $variables[$functionPtr][$parameter['name']] = true;
            }
        }
    }

    /**
     * Whether $hint names a Collection. Union and intersection hints are split
     * apart: a parameter that can be a Collection is treated as one.
     */
    private function isCollectionHint(string $hint): bool
    {
        if ($hint === '') {
            return false;
        }

        foreach (explode('|', str_replace('&', '|', $hint)) as $type) {
            $name = ltrim($type, '?');

            if ($this->isCollectionClass($this->shortName($name), str_contains($name, '\\') === false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports every generic-function call that receives a Collection.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function flagGenericCalls(File $phpcsFile, array $variables): void
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_STRING) {
                continue;
            }

            $function = strtolower($tokens[$ptr]['content']);

            if (isset(self::GENERIC_FUNCTIONS[$function]) === false) {
                continue;
            }

            $opener = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if ($opener === false || $tokens[$opener]['code'] !== T_OPEN_PARENTHESIS) {
                continue;
            }

            if ($this->isGlobalFunctionCall($phpcsFile, $ptr) === false) {
                continue;
            }

            $arguments = $this->argumentRanges($phpcsFile, $opener);
            $collectionArgument = $this->firstCollectionArgument($phpcsFile, $arguments, $variables);

            if ($collectionArgument === null) {
                continue;
            }

            $this->report(
                $phpcsFile,
                $ptr,
                $opener,
                $function,
                $collectionArgument,
                $this->isFixable($phpcsFile, $function, $arguments, $collectionArgument, $variables)
            );
        }
    }

    /**
     * Whether the fixer may rewrite this call: a 1:1 swap function, called with
     * the Collection as its only argument, on a receiver the tokens prove
     * outright.
     *
     * That last condition is what keeps TERMINAL_METHODS out of the fixer's
     * path. isCollectionExpression() types a chained receiver by asking whether
     * the chain's last method is on that list, and assumes a Collection when it
     * is not — so every method missing from the list makes a chain look like a
     * Collection. As a *report* that costs a spurious error. As a *fix* it
     * would rewrite `count($c->random())` into `$c->random()->count()`, which
     * fatals.
     *
     * isProvableCollection() severs the two by re-deriving the receiver's type
     * from CHAINABLE_METHODS, which fails closed. A chain is rewritten only
     * when every link is a method whose Collection return is contractual, so a
     * list that has drifted behind the framework can only ever decline a fix —
     * `count($c->random())` is declined because `random` is not on it, not
     * because chains are declined wholesale.
     *
     * @param array<int, array{0: int, 1: int}> $arguments
     * @param array{0: int, 1: int}             $collectionArgument
     * @param array<int, array<string, bool>>   $variables
     */
    private function isFixable(
        File $phpcsFile,
        string $function,
        array $arguments,
        array $collectionArgument,
        array $variables
    ): bool {
        return in_array($function, self::FIXABLE_FUNCTIONS, true) === true
            && count($arguments) === 1
            && $this->isProvableCollection($phpcsFile, $collectionArgument[0], $collectionArgument[1], $variables)
            && $this->isUnescapedReceiver($phpcsFile, $collectionArgument[0], $collectionArgument[1]);
    }

    /**
     * Whether the receiver spanning [$start, $end] is free of the by-reference
     * doubt mapped by mapEscapedVariables(): a variable handed bare to some
     * other call may have been rebound through a `&$parameter` since, so it is
     * proven at its assignment but not at this call site.
     */
    private function isUnescapedReceiver(File $phpcsFile, int $start, int $end): bool
    {
        $variable = $this->bareVariable($phpcsFile, $start, $end);

        if ($variable === null) {
            return true;
        }

        $scope = $this->scopeOf($phpcsFile, $variable);

        return isset($this->escapedVariables[$scope][$phpcsFile->getTokens()[$variable]['content']]) === false;
    }

    /**
     * Whether the expression spanning [$start, $end] is a Collection the tokens
     * prove outright: an origin — a tracked variable, a `collect()` call, a
     * `Collection::make()`/`::wrap()` factory call, or a `new Collection()` —
     * followed by nothing, or by method calls that are every one of them on
     * CHAINABLE_METHODS.
     *
     * The walk mirrors isCollectionExpression()'s, and deliberately does not
     * share code with it: the two ask opposite questions of the same shape, and
     * the whole safety argument for the fixer is that an unrecognised method
     * ends provability here while it sustains suspicion there. Folding them
     * together behind a flag is how one path's fail-open default would reach
     * the other.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function isProvableCollection(File $phpcsFile, int $start, int $end, array $variables): bool
    {
        if ($start > $end) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $start, ($end + 1), true);

        if ($first === false) {
            return false;
        }

        $ptr = $this->collectionOriginEnd($phpcsFile, $first, $end, $variables);

        if ($ptr === null) {
            return false;
        }

        while (true) {
            $operator = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), ($end + 1), true);

            if ($operator === false) {
                return true;
            }

            // A nullsafe link can yield null, so the chain is no longer
            // provably a Collection whatever the method after it returns.
            if ($tokens[$operator]['code'] !== T_OBJECT_OPERATOR) {
                return false;
            }

            $name = $phpcsFile->findNext(Tokens::$emptyTokens, ($operator + 1), ($end + 1), true);

            if ($name === false || $tokens[$name]['code'] !== T_STRING) {
                return false;
            }

            $parenthesis = $phpcsFile->findNext(Tokens::$emptyTokens, ($name + 1), ($end + 1), true);

            if ($parenthesis === false || $tokens[$parenthesis]['code'] !== T_OPEN_PARENTHESIS) {
                return false;
            }

            if (isset(self::CHAINABLE_METHODS[strtolower($tokens[$name]['content'])]) === false) {
                return false;
            }

            $ptr = $tokens[$parenthesis]['parenthesis_closer'];
        }
    }

    /**
     * @param array<int, array{0: int, 1: int}>  $arguments
     * @param array<int, array<string, bool>>    $variables
     *
     * @return array{0: int, 1: int}|null
     */
    private function firstCollectionArgument(File $phpcsFile, array $arguments, array $variables): ?array
    {
        foreach ($arguments as $argument) {
            if ($this->isCollectionExpression($phpcsFile, $argument[0], $argument[1], $variables) === true) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * @param array{0: int, 1: int} $collectionArgument
     */
    private function report(
        File $phpcsFile,
        int $stackPtr,
        int $opener,
        string $function,
        array $collectionArgument,
        bool $isFixable
    ): void {
        $tokens = $phpcsFile->getTokens();
        $method = self::GENERIC_FUNCTIONS[$function];
        $message = 'Use the Collection method %s() instead of the generic PHP function %s() on a Collection';
        $data = [$method, $tokens[$stackPtr]['content']];

        if ($isFixable === false) {
            $phpcsFile->addError($message, $stackPtr, 'Found', $data);

            return;
        }

        if ($phpcsFile->addFixableError($message, $stackPtr, 'Found', $data) === false) {
            return;
        }

        $expression = trim($phpcsFile->getTokensAsString(
            $collectionArgument[0],
            (($collectionArgument[1] - $collectionArgument[0]) + 1)
        ));
        $closer = $tokens[$opener]['parenthesis_closer'];

        // A fully-qualified call (\count(…)) carries a leading separator that
        // has to go with the function name, or the fix leaves a stray "\".
        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $start = $prev !== false && $tokens[$prev]['code'] === T_NS_SEPARATOR ? $prev : $stackPtr;

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($start, $expression . '->' . $method . '()');

        for ($ptr = ($start + 1); $ptr <= $closer; $ptr++) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Whether the expression spanning [$start, $end] provably evaluates to a
     * Collection.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function isCollectionExpression(File $phpcsFile, int $start, int $end, array $variables): bool
    {
        if ($start > $end) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $start, ($end + 1), true);

        if ($first === false) {
            return false;
        }

        $ptr = $this->collectionOriginEnd($phpcsFile, $first, $end, $variables);

        if ($ptr === null) {
            return false;
        }

        $lastMethod = null;

        while (true) {
            $operator = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), ($end + 1), true);

            if ($operator === false) {
                break;
            }

            if (
                in_array(
                    $tokens[$operator]['code'],
                    [T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR],
                    true
                ) === false
            ) {
                // Anything else trailing the expression (an operator, an array
                // access, …) means the value is no longer just the Collection.
                return false;
            }

            $name = $phpcsFile->findNext(Tokens::$emptyTokens, ($operator + 1), ($end + 1), true);

            if ($name === false || $tokens[$name]['code'] !== T_STRING) {
                return false;
            }

            $parenthesis = $phpcsFile->findNext(Tokens::$emptyTokens, ($name + 1), ($end + 1), true);

            if ($parenthesis === false || $tokens[$parenthesis]['code'] !== T_OPEN_PARENTHESIS) {
                // Property access, not a method call — its type is unknown.
                return false;
            }

            $lastMethod = strtolower($tokens[$name]['content']);
            $ptr = $tokens[$parenthesis]['parenthesis_closer'];
        }

        return $lastMethod === null
            || isset(self::TERMINAL_METHODS[$lastMethod]) === false;
    }

    /**
     * The last token of the Collection-producing head of an expression, or null
     * when the expression does not provably start with one.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function collectionOriginEnd(File $phpcsFile, int $ptr, int $end, array $variables): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$ptr]['code'] === T_VARIABLE) {
            return $this->isCollectionVariable($phpcsFile, $ptr, $variables) === true ? $ptr : null;
        }

        if ($tokens[$ptr]['code'] === T_NEW) {
            return $this->constructedCollectionEnd($phpcsFile, $ptr, $end);
        }

        if (in_array($tokens[$ptr]['code'], [T_NS_SEPARATOR, T_STRING], true) === false) {
            return null;
        }

        $name = $this->qualifiedName($phpcsFile, $ptr, $end);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($name['end'] + 1), ($end + 1), true);

        if ($next === false) {
            return null;
        }

        if ($tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
            // The global collect() helper — a namespaced collect() is a
            // different function entirely.
            return $name['short'] === 'collect' && $name['qualified'] === false
                ? $tokens[$next]['parenthesis_closer']
                : null;
        }

        if (
            $tokens[$next]['code'] !== T_DOUBLE_COLON
            || $this->isCollectionClass($name['short'], $name['aliasable']) === false
        ) {
            return null;
        }

        return $this->factoryCallEnd($phpcsFile, $next, $end);
    }

    /**
     * The closing parenthesis of a `new Collection(...)` expression (or the
     * class name itself when constructed without parentheses), or null.
     */
    private function constructedCollectionEnd(File $phpcsFile, int $newPtr, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($newPtr + 1), ($end + 1), true);

        if ($classPtr === false || in_array($tokens[$classPtr]['code'], [T_NS_SEPARATOR, T_STRING], true) === false) {
            return null;
        }

        $name = $this->qualifiedName($phpcsFile, $classPtr, $end);

        if ($this->isCollectionClass($name['short'], $name['aliasable']) === false) {
            return null;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($name['end'] + 1), ($end + 1), true);

        return $next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS
            ? $tokens[$next]['parenthesis_closer']
            : $name['end'];
    }

    /**
     * The closing parenthesis of a `Collection::make(...)`-style factory call
     * whose `::` sits at $doubleColonPtr, or null when it is not one.
     */
    private function factoryCallEnd(File $phpcsFile, int $doubleColonPtr, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $method = $phpcsFile->findNext(Tokens::$emptyTokens, ($doubleColonPtr + 1), ($end + 1), true);

        if ($method === false || $tokens[$method]['code'] !== T_STRING) {
            return null;
        }

        if (in_array(strtolower($tokens[$method]['content']), self::FACTORY_METHODS, true) === false) {
            return null;
        }

        $parenthesis = $phpcsFile->findNext(Tokens::$emptyTokens, ($method + 1), ($end + 1), true);

        return $parenthesis !== false && $tokens[$parenthesis]['code'] === T_OPEN_PARENTHESIS
            ? $tokens[$parenthesis]['parenthesis_closer']
            : null;
    }

    /**
     * Consumes the run of name tokens starting at $ptr.
     *
     * @return array{short: string, end: int, qualified: bool, aliasable: bool}
     *     the trailing name segment, the last token consumed, whether the name
     *     carries a namespace prefix, and whether it is a bare name that a
     *     `use` import could have renamed (`\Collection` is not: the leading
     *     separator pins it to the global namespace)
     */
    private function qualifiedName(File $phpcsFile, int $ptr, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $short = '';
        $last = $ptr;
        $segments = 0;

        for ($current = $ptr; $current <= $end; $current++) {
            if (in_array($tokens[$current]['code'], [T_NS_SEPARATOR, T_STRING], true) === false) {
                break;
            }

            if ($tokens[$current]['code'] === T_STRING) {
                $short = $tokens[$current]['content'];
                $segments++;
            }

            $last = $current;
        }

        return [
            'short' => $short,
            'end' => $last,
            'qualified' => $segments > 1,
            'aliasable' => $segments === 1 && $tokens[$ptr]['code'] !== T_NS_SEPARATOR,
        ];
    }

    /**
     * Splits a call's argument list into inclusive [start, end] token ranges,
     * stepping over nested parentheses, brackets, and closure bodies so only
     * top-level commas separate arguments.
     *
     * An unterminated argument list has no ranges rather than guessed-at ones,
     * matching statementEnd()'s contract below: the tokenizer leaves
     * parenthesis_closer unset on a parenthesis it never sees closed, which is
     * every call still being typed in a half-written file. Reading it anyway
     * aborts the whole file with an Internal.Exception, so a truncated tail
     * would take every violation above it down with it. Failing closed here
     * costs the ranges of one unfinished call and nothing else — no argument
     * ranges means no collection argument, so the call is neither reported nor
     * rewritten.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function argumentRanges(File $phpcsFile, int $opener): array
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['parenthesis_closer'] ?? null;

        if ($closer === null) {
            return [];
        }

        $ranges = [];
        $start = ($opener + 1);

        for ($ptr = ($opener + 1); $ptr < $closer; $ptr++) {
            $skipTo = $this->nestedRegionEnd($tokens[$ptr]);

            if ($skipTo !== null) {
                $ptr = $skipTo;

                continue;
            }

            if ($tokens[$ptr]['code'] !== T_COMMA) {
                continue;
            }

            $ranges[] = [$start, ($ptr - 1)];
            $start = ($ptr + 1);
        }

        if ($phpcsFile->findNext(Tokens::$emptyTokens, $start, $closer, true) !== false) {
            $ranges[] = [$start, ($closer - 1)];
        }

        return $ranges;
    }

    /**
     * The token that closes the nested region $token opens, or null when it
     * opens none.
     *
     * Only true opening brackets are matched. An arrow function's `=>` also
     * carries a scope_closer, but that scope runs to the end of the enclosing
     * expression — skipping it would swallow the comma that separates the
     * arrow function from the arguments after it.
     *
     * @param array<string, mixed> $token
     */
    private function nestedRegionEnd(array $token): ?int
    {
        return match ($token['code']) {
            T_OPEN_PARENTHESIS => $token['parenthesis_closer'] ?? null,
            T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET => $token['bracket_closer'] ?? null,
            T_OPEN_CURLY_BRACKET => $token['scope_closer'] ?? $token['bracket_closer'] ?? null,
            default => null,
        };
    }

    /**
     * The semicolon ending the statement starting at $start, or null when the
     * statement is unterminated — in which case the expression is not analysed
     * at all rather than guessed at.
     */
    private function statementEnd(File $phpcsFile, int $start): ?int
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr < $phpcsFile->numTokens; $ptr++) {
            $skipTo = $this->nestedRegionEnd($tokens[$ptr]);

            if ($skipTo !== null) {
                $ptr = $skipTo;

                continue;
            }

            if ($tokens[$ptr]['code'] === T_SEMICOLON) {
                return $ptr;
            }
        }

        return null;
    }

    /**
     * Whether the variable at $ptr provably holds a Collection at that point.
     *
     * An arrow function auto-captures, so a name it does not declare resolves
     * to the enclosing scope — but a parameter it *does* declare is a new
     * binding that shadows the outer name, and shadows it in both directions:
     * a `Collection` parameter is a Collection only inside the arrow function,
     * and an `array` parameter is an array inside it even when the enclosing
     * scope holds a Collection of that name. The innermost enclosing arrow
     * function that declares the name therefore decides; only when none does is
     * the enclosing function scope consulted.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function isCollectionVariable(File $phpcsFile, int $ptr, array $variables): bool
    {
        $name = $phpcsFile->getTokens()[$ptr]['content'];
        $binding = $this->arrowParameterBinding($ptr, $name);

        if ($binding !== null) {
            return $binding;
        }

        return isset($variables[$this->scopeOf($phpcsFile, $ptr)][$name]) === true;
    }

    /**
     * Whether the innermost arrow function enclosing $ptr that declares $name
     * declares it as a Collection, or null when no enclosing arrow function
     * declares it at all — in which case the name is captured from outside.
     */
    private function arrowParameterBinding(int $ptr, string $name): ?bool
    {
        $binding = null;

        // Built in T_FN order, so a later match is nested inside an earlier one
        // and the last one to declare the name is the innermost.
        foreach ($this->arrowFunctions as $arrowFunction) {
            if ($ptr < $arrowFunction['start'] || $ptr > $arrowFunction['end']) {
                continue;
            }

            $binding = $arrowFunction['parameters'][$name] ?? $binding;
        }

        return $binding;
    }

    /**
     * Whether $ptr sits inside any arrow function's body.
     */
    private function isInsideArrowFunction(int $ptr): bool
    {
        foreach ($this->arrowFunctions as $arrowFunction) {
            if ($ptr >= $arrowFunction['start'] && $ptr <= $arrowFunction['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The innermost function or closure scope containing $ptr, or 0 for file
     * scope.
     *
     * Arrow functions are deliberately not scopes here: they auto-capture the
     * enclosing scope's variables by value, so a Collection in scope outside
     * `fn () => …` is the same Collection inside it. That holds for names an
     * arrow function *uses*; names it *declares* as parameters shadow instead,
     * and are resolved by arrowParameterBinding() before this is reached.
     */
    private function scopeOf(File $phpcsFile, int $ptr): int
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$ptr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $opener => $code) {
            if (in_array($code, [T_CLOSURE, T_FUNCTION], true) === true) {
                return $opener;
            }
        }

        return 0;
    }

    /**
     * Whether the token at $target is a plain local variable being assigned,
     * rather than a property write that merely ends in a T_VARIABLE.
     *
     * `$this->items = …` is excluded for free (its name tokenises as T_STRING),
     * but `self::$items`, `static::$items` and `Example::$items` all end in a
     * T_VARIABLE, and registering those against the *enclosing method's* scope
     * would make a static property poison the local — or the parameter — that
     * happens to share its name.
     */
    private function isLocalVariableTarget(File $phpcsFile, int $target): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$target]['code'] !== T_VARIABLE) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($target - 1), null, true);

        return $prev === false || $tokens[$prev]['code'] !== T_DOUBLE_COLON;
    }

    /**
     * Whether the assignment to $target always runs when its scope runs — i.e.
     * it sits directly in the function/closure/file body rather than inside a
     * branch, loop, `try`, `match` or any other construct whose execution the
     * sniff cannot reason about from the tokens.
     *
     * Every condition enclosing $target that opens *after* the scope opener is
     * such a construct: the scope's own opener, and anything wrapping it (a
     * class, a namespace), sort before it.
     */
    private function isUnconditionalAssignment(File $phpcsFile, int $target, int $scope): bool
    {
        $tokens = $phpcsFile->getTokens();

        // An arrow function's body is a deferred expression with locals of its
        // own, so an assignment inside one proves nothing about the scope
        // around it. PHPCS records no T_FN in `conditions`, so the loop below
        // cannot see it — the range check has to stand in.
        if ($this->isInsideArrowFunction($target) === true) {
            return false;
        }

        foreach (array_keys($tokens[$target]['conditions'] ?? []) as $opener) {
            if ($opener > $scope) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the T_STRING at $stackPtr is a call to a global function rather
     * than a method call, a static call, a declaration, or a function the file
     * imported under that name.
     */
    private function isGlobalFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($prev === false) {
            return $this->isUnshadowedName($phpcsFile, $stackPtr);
        }

        if (in_array($tokens[$prev]['code'], self::NON_FUNCTION_CALL_PRECEDERS, true) === true) {
            return false;
        }

        if ($tokens[$prev]['code'] !== T_NS_SEPARATOR) {
            return $this->isUnshadowedName($phpcsFile, $stackPtr);
        }

        // A leading "\" still resolves to the global function; a preceding name
        // segment (App\count, namespace\count) does not.
        $beforeSeparator = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($prev - 1), null, true);

        return $beforeSeparator === false
            || in_array($tokens[$beforeSeparator]['code'], [T_NAMESPACE, T_STRING], true) === false;
    }

    /**
     * Whether the unqualified name at $stackPtr still refers to the global
     * function of that name. A `use function … as count;` import rebinds the
     * name for the whole file, so a bare `count($c)` calls the import — and
     * rewriting it to `$c->count()` would change the answer without so much as
     * a warning. Only unqualified names can be shadowed this way; the caller
     * has already resolved the fully-qualified form.
     */
    private function isUnshadowedName(File $phpcsFile, int $stackPtr): bool
    {
        return isset($this->functionImports[strtolower($phpcsFile->getTokens()[$stackPtr]['content'])]) === false;
    }

    /**
     * Whether $shortName names a Collection class. Framework and app
     * collections alike end in "Collection" (Collection, EloquentCollection,
     * OrderCollection, …).
     *
     * A bare name may be an alias, in which case the imported class decides —
     * `use …\Collection as Coll` makes `Coll` one, `use …\Arr as RowCollection`
     * makes `RowCollection` not one. A name written with a namespace prefix
     * names its class directly and is never resolved through the imports.
     */
    private function isCollectionClass(string $shortName, bool $isAliasable): bool
    {
        if ($shortName === '') {
            return false;
        }

        $resolved = $isAliasable === true
            ? ($this->importAliases[strtolower($shortName)] ?? $shortName)
            : $shortName;

        return str_ends_with($resolved, 'Collection');
    }

    /**
     * The trailing segment of a possibly namespaced name.
     */
    private function shortName(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, ($position + 1));
    }
}
