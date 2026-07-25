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
     */
    private const FIXABLE_FUNCTIONS = [
        'array_sum',
        'count',
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
     *   treat its result as a Collection — a false positive, and on a fixable
     *   function a rewrite that fatals (`count($c->random())` becoming
     *   `$c->random()->count()`). Never acceptable, which is why this list is
     *   audited against the whole Collection API rather than grown one report
     *   at a time.
     * - **Listing** a method that returns a non-Collection only for *some*
     *   arguments (`pop()`, `shift()`, `random()` and `find()` all hand back a
     *   Collection when given a count or a list of keys) makes the sniff stay
     *   silent on the Collection form — a false negative, which is the
     *   direction this sniff fails in by design. Those entries are marked
     *   "argument-dependent" below.
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
        'percentage' => 'float|null',
        'pipe' => 'mixed — the callback\'s return; argument-dependent',
        'pipeinto' => 'object',
        'pipethrough' => 'mixed — the last callback\'s return; argument-dependent',
        'pop' => 'mixed, or a Collection when given $count — argument-dependent',
        'pull' => 'mixed',
        'random' => 'mixed, or a Collection when given $number — argument-dependent',
        'reduce' => 'mixed — the callback\'s return; argument-dependent',
        'reducespread' => 'array',
        'search' => 'int|string|false',
        'shift' => 'mixed, or a Collection when given $count — argument-dependent',
        'sole' => 'mixed',
        'some' => 'bool',
        'sum' => 'int|float',
        'toarray' => 'array',
        'tojson' => 'string',
        'toquery' => 'Builder (Eloquent)',
        'unless' => 'mixed — the callback\'s return, else $this; argument-dependent',
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
        $this->importAliases = $this->mapImportAliases($phpcsFile);

        $this->flagGenericCalls($phpcsFile, $this->mapCollectionVariables($phpcsFile));

        return $phpcsFile->numTokens;
    }

    /**
     * Maps the file's class imports by the name the code actually uses, so an
     * aliased import is judged by the class it names rather than by its alias:
     * `use …\Collection as Coll` makes `Coll::make()` a Collection, and
     * `use …\Arr as RowCollection` stops `RowCollection::wrap()` looking like
     * one.
     *
     * @return array<string, string>
     */
    private function mapImportAliases(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $aliases = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_USE || $this->isClassImport($phpcsFile, $ptr) === false) {
                continue;
            }

            $end = $this->statementEnd($phpcsFile, ($ptr + 1));

            if ($end === null) {
                continue;
            }

            $this->collectImportAliases($phpcsFile, ($ptr + 1), ($end - 1), $aliases);
            $ptr = $end;
        }

        return $aliases;
    }

    /**
     * Whether the T_USE at $usePtr imports classes, as opposed to pulling in a
     * trait, capturing a closure's variables, or importing functions/constants.
     */
    private function isClassImport(File $phpcsFile, int $usePtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        // A trait `use` lives inside a class body. A braced namespace is the
        // only enclosing construct an import can legitimately sit in.
        foreach ($tokens[$usePtr]['conditions'] ?? [] as $code) {
            if ($code !== T_NAMESPACE) {
                return false;
            }
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($usePtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
            return false;
        }

        // Matched on content: `function`/`const` after `use` tokenise
        // differently across PHPCS versions, but never read otherwise.
        return in_array(strtolower($tokens[$next]['content']), ['const', 'function'], true) === false;
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
     * @return array<int, array<string, bool>>
     */
    private function mapCollectionVariables(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $variables = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if (in_array($tokens[$ptr]['code'], [T_CLOSURE, T_FN, T_FUNCTION], true) === true) {
                $this->addTypeHintedParameters($phpcsFile, $ptr, $variables);

                continue;
            }

            if ($tokens[$ptr]['code'] !== T_EQUAL) {
                continue;
            }

            $target = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if ($target === false || $this->isLocalVariableTarget($phpcsFile, $target) === false) {
                continue;
            }

            $scope = $this->scopeOf($phpcsFile, $target);
            $name = $tokens[$target]['content'];

            // A conditional assignment says nothing about what the variable
            // holds at the call site: the branch may not have run, and the
            // branch next door may assign something else entirely. Rather than
            // let the textually last write win — which would flag, and offer to
            // "fix", code that is correct at runtime — the name is retired.
            if ($this->isUnconditionalAssignment($phpcsFile, $target, $scope) === false) {
                unset($variables[$scope][$name]);

                continue;
            }

            $end = $this->statementEnd($phpcsFile, ($ptr + 1));

            if ($end === null) {
                continue;
            }

            if ($this->isCollectionExpression($phpcsFile, ($ptr + 1), ($end - 1), $variables) === true) {
                $variables[$scope][$name] = true;

                continue;
            }

            // Reassignment to a non-Collection retires the tracking, so later
            // calls on the same name are not misreported.
            unset($variables[$scope][$name]);
        }

        return $variables;
    }

    /**
     * Registers parameters type-hinted as a Collection against their function's
     * scope.
     *
     * @param array<int, array<string, bool>> $variables
     */
    private function addTypeHintedParameters(File $phpcsFile, int $functionPtr, array &$variables): void
    {
        $tokens = $phpcsFile->getTokens();

        // An arrow function shares its enclosing scope (see scopeOf()), so its
        // parameters have to be registered against that same scope to be found.
        $scope = $tokens[$functionPtr]['code'] === T_FN
            ? $this->scopeOf($phpcsFile, $functionPtr)
            : $functionPtr;

        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            $hint = $parameter['type_hint'];

            if ($hint === '') {
                continue;
            }

            // Union and intersection hints are split apart: a parameter that
            // can be a Collection is treated as one.
            foreach (explode('|', str_replace('&', '|', $hint)) as $type) {
                $name = ltrim($type, '?');

                if ($this->isCollectionClass($this->shortName($name), str_contains($name, '\\') === false) === false) {
                    continue;
                }

                $variables[$scope][$parameter['name']] = true;

                break;
            }
        }
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

            $this->report($phpcsFile, $ptr, $opener, $function, $arguments, $collectionArgument);
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
     * @param array<int, array{0: int, 1: int}> $arguments
     * @param array{0: int, 1: int}             $collectionArgument
     */
    private function report(
        File $phpcsFile,
        int $stackPtr,
        int $opener,
        string $function,
        array $arguments,
        array $collectionArgument
    ): void {
        $tokens = $phpcsFile->getTokens();
        $method = self::GENERIC_FUNCTIONS[$function];
        $message = 'Use the Collection method %s() instead of the generic PHP function %s() on a Collection';
        $data = [$method, $tokens[$stackPtr]['content']];

        $isFixable = in_array($function, self::FIXABLE_FUNCTIONS, true) === true
            && count($arguments) === 1;

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
            $scope = $this->scopeOf($phpcsFile, $ptr);

            return isset($variables[$scope][$tokens[$ptr]['content']]) === true ? $ptr : null;
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
     * @return array<int, array{0: int, 1: int}>
     */
    private function argumentRanges(File $phpcsFile, int $opener): array
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['parenthesis_closer'];
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
     * The innermost variable scope containing $ptr, or 0 for file scope.
     *
     * Arrow functions are deliberately not scopes here: they auto-capture the
     * enclosing scope's variables by value, so a Collection in scope outside
     * `fn () => …` is the same Collection inside it.
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

        foreach (array_keys($tokens[$target]['conditions'] ?? []) as $opener) {
            if ($opener > $scope) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the T_STRING at $stackPtr is a call to a global function rather
     * than a method call, a static call, or a declaration.
     */
    private function isGlobalFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($prev === false) {
            return true;
        }

        if (in_array($tokens[$prev]['code'], self::NON_FUNCTION_CALL_PRECEDERS, true) === true) {
            return false;
        }

        if ($tokens[$prev]['code'] !== T_NS_SEPARATOR) {
            return true;
        }

        // A leading "\" still resolves to the global function; a preceding name
        // segment (App\count, namespace\count) does not.
        $beforeSeparator = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($prev - 1), null, true);

        return $beforeSeparator === false
            || in_array($tokens[$beforeSeparator]['code'], [T_NAMESPACE, T_STRING], true) === false;
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
