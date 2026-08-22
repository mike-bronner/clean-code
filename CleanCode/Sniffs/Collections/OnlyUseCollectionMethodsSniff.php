<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Collections;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
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
 *   through TERMINAL_METHODS, across a call that may take the variable by
 *   reference, or from a declaration that only *permits* a Collection
 *   (`Collection|array`, `?Collection`) — the finding is reported and left
 *   alone. An inference good enough for a warning is not good enough to
 *   rewrite source.
 *
 * The second rule is the one that needs guarding at every step, because a
 * variable is otherwise a laundering step between them: whatever the first rule
 * was willing to assume becomes, once it has a name, something the fixer treats
 * as proven. So a tracked name carries the strength of its binding as well as
 * the fact of it, and every reader says which of the two questions it is
 * asking. A variadic parameter is outside both rules — `Collection ...$items`
 * binds an array, so it is not a Collection to report on in the first place.
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
     * The tokens that can end a callable expression or name a class being
     * instantiated, and so mean the parenthesis after them opens an argument
     * list whose callee this sniff cannot read.
     *
     * A parenthesis is a call exactly when what precedes it produces a value or
     * references a class; everything else in front of one — an operator, a
     * separator, a control-structure or declaration keyword, a language
     * construct — opens a grouping, a condition, or a parameter list, none of
     * which can rebind a caller's variable. T_STRING and T_VARIABLE are absent
     * deliberately: those two the caller *can* read, and it identifies them
     * itself rather than giving up here.
     *
     * Constructors count. `new class($c)`, `new static($c)` and `new self($c)`
     * all reach a `__construct()` free to declare `&$items`, and none of them
     * carries a class name this sniff could resolve. A plain `new Foo($c)` needs
     * no entry — its name is a T_STRING the caller already reads.
     *
     * Membership here is necessary but not sufficient for the two closing
     * brackets: a `}` or `)` also closes a block or a condition, which ends a
     * statement rather than producing a value. isCallableExpressionEnd() is what
     * tells the two apart, and every read of this list goes through it.
     */
    private const CALLABLE_EXPRESSION_ENDERS = [
        T_ANON_CLASS,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_SELF,
        T_STATIC,
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
     * @var array<int, array{
     *     start: int,
     *     end: int,
     *     parameters: array<string, array{reportable: bool, provable: bool}>
     * }>
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
        $this->importAliases = $this->mapImports($phpcsFile);
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
     * in a closure. Both directions are recorded here — a parameter that is not
     * a Collection is present with both polarities false, not absent — because a
     * parameter shadowing an enclosing Collection has to stop the outward
     * lookup rather than fall through it. A variadic parameter is exactly that
     * case: it shadows the outer name and binds an array.
     *
     * @return array<int, array{
     *     start: int,
     *     end: int,
     *     parameters: array<string, array{reportable: bool, provable: bool}>
     * }>
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
                $parameters[$parameter['name']] = $this->hintBinding($parameter);
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
     * Only class imports are collected. A `use function` import decides what
     * counts as a *builtin* instead, and that question belongs to
     * FunctionCalls::isGlobalFunctionCall(), which resolves it per namespace
     * block rather than per file. Binding a function import into the class map
     * here would make its name answer the Collection question too.
     *
     * @return array<string, string> the class aliases, keyed by lower-cased
     *     local name
     */
    private function mapImports(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $classes = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_USE) {
                continue;
            }

            $kind = $this->importKind($phpcsFile, $ptr);
            $end = $kind === null ? null : $this->statementEnd($phpcsFile, ($ptr + 1));

            if ($end === null) {
                continue;
            }

            if ($kind === 'class') {
                $names = [];
                $this->collectImportAliases($phpcsFile, ($ptr + 1), ($end - 1), $names);
                $classes = array_merge($classes, $names);
            }

            $ptr = $end;
        }

        return $classes;
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
     * The map is a set with a strength attached, and both parts are read:
     * **presence** means the name holds a Collection as far as reporting is
     * concerned, and the **value** says whether it holds one for certain. A
     * name tracked `false` — a `Collection|array` parameter, or one assigned
     * from an expression only the fail-open walk accepts — is reported and
     * never rewritten. Without that second bit a variable is a laundering
     * step: whatever reporting was willing to assume becomes something the
     * fixer treats as proven.
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

        // The name inherits the *strength* of the expression that assigned it,
        // not merely the fact that one did. isCollectionExpression() above
        // fails open, so it admits `$rows = $c->someMacro();` — right for
        // reporting, and no proof at all for the fixer. Recording only the
        // fail-open answer here is what let a variable launder it: the same
        // `count($c->someMacro())` the fixer declines inline was rewritten once
        // it went through a variable.
        $variables[$scope][$name] = $this->isProvableCollection($phpcsFile, ($ptr + 1), ($end - 1), $variables);
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
     * A variable following it is still not enough. An *untyped* static property
     * (`private static $items;`, `public static $items = [];`) is spelled the
     * same way as a function-local `static $items;`, and scopeOf() resolves a
     * class body to scope 0 — the same bucket a file-scope Collection occupies.
     * Left unguarded, declaring a property silently retires the unrelated local
     * that shares its name, anywhere in the file. A property declares no local
     * binding at all, so position decides: inside a function body this rebinds a
     * local, inside a class body it does not.
     *
     * @param array<int, array<string, bool>> $variables
     * @param array<int, array<string, bool>> $retired
     */
    private function retireDeclaredVariables(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        if (
            $next === false
            || $tokens[$next]['code'] !== T_VARIABLE
            || $this->isInClassBody($phpcsFile, $ptr) === true
        ) {
            return;
        }

        $end = $this->statementEnd($phpcsFile, $next);

        if ($end !== null) {
            $this->retireRange($phpcsFile, $next, $end, $variables, $retired);
        }
    }

    /**
     * Whether $ptr sits directly in a class, interface, trait or enum body
     * rather than inside a function or closure within one. Whichever of the two
     * encloses $ptr more tightly decides: a method's body is a function body,
     * even though a class encloses it too.
     */
    private function isInClassBody(File $phpcsFile, int $ptr): bool
    {
        foreach (array_reverse($phpcsFile->getTokens()[$ptr]['conditions'] ?? [], true) as $code) {
            if (in_array($code, [T_CLOSURE, T_FUNCTION], true) === true) {
                return false;
            }

            if (isset(Tokens::$ooScopeTokens[$code]) === true) {
                return true;
            }
        }

        return false;
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
     * seventeen *global* functions this sniff reports on — all of which take
     * their arguments by value, which is what keeps `count($c)` from escaping
     * its own receiver. A shadowed or method call merely spelled like one of
     * the seventeen is userland code, and escapes like any other call.
     *
     * "Provably" is the whole of it, so an unreadable callee answers false. A
     * call made through a callable *expression* — an IIFE `(function (&$x) {…})
     * ($c)`, an indexed callable `$callbacks['key']($c)`, a returned closure
     * `($factory->getMutator())($c)`, a dynamic method `$o->{$name}($c)` — has
     * no name to look up, so nothing here can show its parameters are by value.
     * Answering true for those on the grounds that they matched no known shape
     * let the fixer rewrite a receiver a reference parameter had already
     * rebound, turning working code into a runtime fatal.
     */
    private function isByValueCallOpener(File $phpcsFile, int $ptr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $callee = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

        if ($callee === false) {
            return true;
        }

        if (in_array($tokens[$callee]['code'], [T_STRING, T_VARIABLE], true) === false) {
            // Neither a name nor a variable in front of the parenthesis. Either
            // it opens no call at all — a grouping, a condition, a declaration's
            // parameter list, a language construct — or it calls a callable this
            // sniff cannot name. Only the first is provably by value, so the
            // answer is the admission set, never the fallthrough: a callee whose
            // parameters cannot be read is exactly the one that may declare
            // `&$items`.
            return $this->isCallableExpressionEnd($phpcsFile, $callee) === false;
        }

        // A declaration's parameter list, not a call: its variables are the
        // callee's own, and addTypeHintedParameters() has already judged them.
        $before = $this->pastReferenceMarker(
            $phpcsFile,
            $phpcsFile->findPrevious(Tokens::$emptyTokens, ($callee - 1), null, true)
        );

        if ($before !== false && in_array($tokens[$before]['code'], [T_FN, T_FUNCTION], true) === true) {
            return true;
        }

        // Only the *global* function of that name is one of the seventeen. A
        // `use function …\countDistinctTags as count;` import, or a `->count()`
        // method of the same name, is userland code free to declare `&$items`
        // and rebind the caller's variable — so it escapes like any other call.
        return isset(self::GENERIC_FUNCTIONS[strtolower($tokens[$callee]['content'])]) === true
            && FunctionCalls::isGlobalFunctionCall($phpcsFile, $callee) === true;
    }

    /**
     * Whether the token at $ptr ends a callable expression, so that the
     * parenthesis after it opens an argument list rather than a new statement.
     *
     * Being in CALLABLE_EXPRESSION_ENDERS is not the whole answer for the two
     * closing brackets. The same `}` that closes `$o->{$name}` also closes an
     * `if`/`foreach`/`while`/`try` body, and the same `)` that closes
     * `($factory->getMutator())` also closes such a block's condition — and PHP
     * needs no semicolon after a block, so either can sit directly in front of
     * an unrelated parenthesis opening the next statement. `if (…) { … }`
     * followed by `($c)->count();` is two statements, not a call on the brace.
     *
     * PHPCS records the construct a bracket belongs to, which separates them:
     * `scope_condition` on a `}` closing a body, `parenthesis_owner` on a `)`
     * closing a condition or parameter list. A callable expression is owned by
     * no construct, so an *unowned* bracket is the one that hides a callee. No
     * owned bracket can be a callee either: the owning constructs that do
     * produce a value — `array()`, `match () {}`, a closure literal — are none
     * of them directly invocable, PHP's grammar requiring `(function () {})($c)`
     * to wrap the expression first, and that wrapping parenthesis is unowned.
     *
     * Only the two brackets are asked. T_ANON_CLASS carries both keys itself —
     * it *is* a construct that owns a scope — so testing it the same way would
     * read `new class($c)` as owned, hence not a callable expression, hence
     * provably by value: a constructor free to declare `&$items` would become
     * fixable. T_SELF, T_STATIC and T_CLOSE_SQUARE_BRACKET carry neither key and
     * have no such ambiguity to resolve.
     *
     * Unowned is also the safe answer to be wrong about: a bracket whose owner
     * PHPCS has not recorded reads as a callable expression, and the caller
     * declines the fix.
     */
    private function isCallableExpressionEnd(File $phpcsFile, int $ptr): bool
    {
        $token = $phpcsFile->getTokens()[$ptr];

        return match (true) {
            in_array($token['code'], self::CALLABLE_EXPRESSION_ENDERS, true) === false => false,
            $token['code'] === T_CLOSE_CURLY_BRACKET => isset($token['scope_condition']) === false,
            $token['code'] === T_CLOSE_PARENTHESIS => isset($token['parenthesis_owner']) === false,
            default => true,
        };
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
            $binding = $this->hintBinding($parameter);

            if ($binding['reportable'] === true) {
                $variables[$functionPtr][$parameter['name']] = $binding['provable'];
            }
        }
    }

    /**
     * What a parameter's declaration proves about the value it binds, in the
     * two polarities the sniff reads separately.
     *
     * `reportable` fails open: a hint the value *can* satisfy as a Collection
     * is enough, because an over-eager suspicion costs a spurious error.
     * `provable` fails closed: only a hint the value must satisfy as a
     * Collection qualifies, because an over-eager proof costs a rewrite into a
     * runtime fatal. `Collection|array $c` is the shape that separates them —
     * `count($c)` is worth reporting and must never be rewritten, since
     * `$c->count()` fatals the moment an array is passed.
     *
     * A **variadic** parameter satisfies neither. `Collection ...$items` binds
     * an *array of* Collections, never a Collection, so `count($items)` is
     * correct code: reporting it is a false positive and rewriting it is a
     * fatal. PHPCS reports this in `variable_length`, which the hint string
     * alone cannot show.
     *
     * @param array<string, mixed> $parameter one of getMethodParameters()' entries
     *
     * @return array{reportable: bool, provable: bool}
     */
    private function hintBinding(array $parameter): array
    {
        $notACollection = ['reportable' => false, 'provable' => false];

        // Both keys are set for every parameter getMethodParameters() returns,
        // so they are read outright. A `?? false` here would be a safety guard
        // with a fail-open default: were the key ever to go missing, a variadic
        // parameter would silently become a rewritable Collection again.
        if ($parameter['variable_length'] === true) {
            return $notACollection;
        }

        $hint = $parameter['type_hint'];

        if ($hint === '') {
            return $notACollection;
        }

        return [
            'reportable' => $this->isCollectionHint($hint),
            'provable' => $this->isProvableCollectionHint($hint),
        ];
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
     * Whether $hint proves the value *is* a Collection, whatever branch of the
     * declaration it took — the fail-closed counterpart of isCollectionHint().
     *
     * The two connectors are read as what they mean, not folded together as
     * isCollectionHint() may fold them:
     *
     * - A **union** is a choice, so every member has to be a Collection.
     *   `Collection|EloquentCollection` proves one; `Collection|array` does not.
     * - An **intersection** is a conjunction, so one Collection member is
     *   enough — `Collection&Countable` is a Collection that is also Countable.
     * - **Nullable** — `?Collection`, or a `null` member — proves nothing, the
     *   value may be null.
     *
     * A hint mixing both connectors is not read at all. PHP only permits them
     * together as DNF (`(A&B)|C`), whose parentheses this flat split cannot
     * honour, and guessing at one is how the fail-closed side would fail open.
     */
    private function isProvableCollectionHint(string $hint): bool
    {
        if ($hint === '' || str_starts_with($hint, '?') === true) {
            return false;
        }

        $isUnion = str_contains($hint, '|');
        $isIntersection = str_contains($hint, '&');

        if ($isUnion === true && $isIntersection === true) {
            return false;
        }

        $members = explode($isIntersection === true ? '&' : '|', $hint);

        foreach ($members as $member) {
            $isCollection = $this->isCollectionClass(
                $this->shortName($member),
                str_contains($member, '\\') === false
            );

            // A union needs every member; an intersection needs only one.
            if ($isIntersection === true && $isCollection === true) {
                return true;
            }

            if ($isIntersection === false && $isCollection === false) {
                return false;
            }
        }

        return $isIntersection === false;
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

            if (FunctionCalls::isGlobalFunctionCall($phpcsFile, $ptr) === false) {
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

        $ptr = $this->collectionOriginEnd($phpcsFile, $first, $end, $variables, true);

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

        $ptr = $this->collectionOriginEnd($phpcsFile, $first, $end, $variables, false);

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
     * Both walks share this resolver, so it carries the polarity they differ
     * on: only a variable's binding is read two ways, and $provable says which
     * way. It has no default on purpose — a call site that forgets it does not
     * compile, rather than quietly taking the fail-open reading into the
     * fixer. Every other origin here (`collect()`, a factory call, `new`)
     * proves a Collection outright and reads the same under both.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function collectionOriginEnd(
        File $phpcsFile,
        int $ptr,
        int $end,
        array $variables,
        bool $provable
    ): ?int {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$ptr]['code'] === T_VARIABLE) {
            return $this->isCollectionVariable($phpcsFile, $ptr, $variables, $provable) === true ? $ptr : null;
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
            // different function entirely, and so is one the file imported
            // under that name. FunctionCalls::isGlobalFunctionCall() settles
            // both, and settles them the same way for every other bare name
            // this sniff reads, which is the point of routing through it: a
            // `use function …\makeArray as collect;` import makes a bare
            // collect() return whatever that function returns, which is not a
            // Collection this sniff may report on, let alone rewrite.
            $isHelper = $name['short'] === 'collect'
                && $name['qualified'] === false
                && FunctionCalls::isGlobalFunctionCall($phpcsFile, $name['end']) === true;

            return $isHelper === true ? $tokens[$next]['parenthesis_closer'] : null;
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
    private function isCollectionVariable(File $phpcsFile, int $ptr, array $variables, bool $provable): bool
    {
        $name = $phpcsFile->getTokens()[$ptr]['content'];
        $binding = $this->arrowParameterBinding($ptr, $name, $provable);

        if ($binding !== null) {
            return $binding;
        }

        // Presence says the name is a Collection as far as reporting is
        // concerned; the value says whether it is one for certain. A name
        // tracked only on the fail-open reading is reported and never fixed.
        $tracked = $variables[$this->scopeOf($phpcsFile, $ptr)][$name] ?? null;

        return $tracked !== null && ($provable === false || $tracked === true);
    }

    /**
     * Whether the innermost arrow function enclosing $ptr that declares $name
     * declares it as a Collection, under the polarity $provable selects, or
     * null when no enclosing arrow function declares it at all — in which case
     * the name is captured from outside.
     */
    private function arrowParameterBinding(int $ptr, string $name, bool $provable): ?bool
    {
        $binding = null;
        $polarity = $provable === true ? 'provable' : 'reportable';

        // Built in T_FN order, so a later match is nested inside an earlier one
        // and the last one to declare the name is the innermost.
        foreach ($this->arrowFunctions as $arrowFunction) {
            if ($ptr < $arrowFunction['start'] || $ptr > $arrowFunction['end']) {
                continue;
            }

            $binding = isset($arrowFunction['parameters'][$name]) === true
                ? $arrowFunction['parameters'][$name][$polarity]
                : $binding;
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
     * The token governing a name, given the one directly before it: the token
     * whose type says whether the name is a call, a declaration, an
     * instantiation or a method.
     *
     * Returning by reference puts an `&` between the keyword and the name, so
     * `function &count()` is a declaration all the same and the keyword sits one
     * token further back than it looks. Stepping over a bitwise `&` instead
     * (`$mask & count()`) is harmless: what precedes an operator there is an
     * operand, never one of the keywords the callers test for.
     *
     * isByValueCallOpener() was missing it, and read a by-reference
     * declaration's parameter list as call arguments, marking the parameters
     * escaped. The same gap over the call question — `function &count($items)`
     * read as a call, which let `phpcbf` rewrite the declaration into
     * `function &$items->count()` — is now FunctionCalls's to answer, and its
     * own isReturnByReferenceMarker() answers it.
     */
    private function pastReferenceMarker(File $phpcsFile, int|false $previous): int|false
    {
        if ($previous === false || $phpcsFile->getTokens()[$previous]['code'] !== T_BITWISE_AND) {
            return $previous;
        }

        return $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previous - 1), null, true);
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
