<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Collections;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class OnlyUseCollectionMethodsSniff implements Sniff
{
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

    private const FIXABLE_FUNCTIONS = [
        'array_sum',
        'count',
    ];

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

    private const FACTORY_METHODS = [
        'make',
        'wrap',
    ];

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

    private const CALLABLE_EXPRESSION_ENDERS = [
        T_ANON_CLASS,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_SELF,
        T_STATIC,
    ];

    private array $importAliases = [];

    private array $escapedVariables = [];

    private array $arrowFunctions = [];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls()
    ) {
    }

    public function register(): array
    {
        return [T_OPEN_TAG];
    }

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

        if (
            $next === false
            || $tokens[$next]['code'] === T_OPEN_PARENTHESIS
        ) {
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

            if (
                $tokens[$ptr]['code'] === T_STRING
                && $isAlias === true
            ) {
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

    private function recordImportAlias(string $imported, string $alias, array &$aliases): void
    {
        if ($imported === '') {
            return;
        }

        // Class names are case-insensitive in PHP, so the lookup key is too.
        $aliases[strtolower($alias === '' ? $imported : $alias)] = $imported;
    }

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

    private function retireBoundClause(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$ptr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
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

    private function retireReferenceCaptures(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;
        $bodyStart = $tokens[$ptr]['scope_opener'] ?? null;

        if (
            $closer === null
            || $bodyStart === null
        ) {
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

    private function retireRange(File $phpcsFile, int $start, int $end, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            if ($tokens[$ptr]['code'] === T_VARIABLE) {
                $this->retireVariable($phpcsFile, $ptr, $variables, $retired);
            }
        }
    }

    private function retireVariable(File $phpcsFile, int $ptr, array &$variables, array &$retired): void
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$ptr]['code'] !== T_VARIABLE
            || $this->isLocalVariableTarget($phpcsFile, $ptr) === false
        ) {
            return;
        }

        $this->retire($this->scopeOf($phpcsFile, $ptr), $tokens[$ptr]['content'], $variables, $retired);
    }

    private function retire(int $scope, string $name, array &$variables, array &$retired): void
    {
        unset($variables[$scope][$name]);

        $retired[$scope][$name] = true;
    }

    private function withoutRetired(array $variables, array $retired): array
    {
        foreach ($retired as $scope => $names) {
            if (isset($variables[$scope]) === true) {
                $variables[$scope] = array_diff_key($variables[$scope], $names);
            }
        }

        return $variables;
    }

    private function mapEscapedVariables(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $escaped = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if (
                $tokens[$ptr]['code'] !== T_OPEN_PARENTHESIS
                || isset($tokens[$ptr]['parenthesis_closer']) === false
            ) {
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

    private function isByValueCallOpener(File $phpcsFile, int $ptr): bool
    {
        $functionCalls = $this->functionCalls;

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

        if (
            $before !== false
            && in_array($tokens[$before]['code'], [T_FN, T_FUNCTION], true) === true
        ) {
            return true;
        }

        // Only the *global* function of that name is one of the seventeen. A
        // `use function …\countDistinctTags as count;` import, or a `->count()`
        // method of the same name, is userland code free to declare `&$items`
        // and rebind the caller's variable — so it escapes like any other call.
        return isset(self::GENERIC_FUNCTIONS[strtolower($tokens[$callee]['content'])]) === true
            && $functionCalls->isGlobalFunctionCall($phpcsFile, $callee) === true;
    }

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

    private function bareVariable(File $phpcsFile, int $start, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $start, ($end + 1), true);

        if (
            $first === false
            || $tokens[$first]['code'] !== T_VARIABLE
        ) {
            return null;
        }

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($first + 1), ($end + 1), true) === false ? $first : null;
    }

    private function addTypeHintedParameters(File $phpcsFile, int $functionPtr, array &$variables): void
    {
        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            $binding = $this->hintBinding($parameter);

            if ($binding['reportable'] === true) {
                $variables[$functionPtr][$parameter['name']] = $binding['provable'];
            }
        }
    }

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

    private function isProvableCollectionHint(string $hint): bool
    {
        if (
            $hint === ''
            || str_starts_with($hint, '?') === true
        ) {
            return false;
        }

        $isUnion = str_contains($hint, '|');
        $isIntersection = str_contains($hint, '&');

        if (
            $isUnion === true
            && $isIntersection === true
        ) {
            return false;
        }

        $members = explode($isIntersection === true ? '&' : '|', $hint);

        foreach ($members as $member) {
            $isCollection = $this->isCollectionClass(
                $this->shortName($member),
                str_contains($member, '\\') === false
            );

            // A union needs every member; an intersection needs only one.
            if (
                $isIntersection === true
                && $isCollection === true
            ) {
                return true;
            }

            if (
                $isIntersection === false
                && $isCollection === false
            ) {
                return false;
            }
        }

        return $isIntersection === false;
    }

    private function flagGenericCalls(File $phpcsFile, array $variables): void
    {
        $functionCalls = $this->functionCalls;

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

            if (
                $opener === false
                || $tokens[$opener]['code'] !== T_OPEN_PARENTHESIS
            ) {
                continue;
            }

            if ($functionCalls->isGlobalFunctionCall($phpcsFile, $ptr) === false) {
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

    private function isFixable(
        File $phpcsFile,
        string $function,
        array $arguments,
        array $collectionArgument,
        array $variables
    ): bool {
        return in_array($function, self::FIXABLE_FUNCTIONS, true) === true
            && count($arguments) === 1
            && $this->isCommentFree($phpcsFile, $collectionArgument[0], $collectionArgument[1])
            && $this->isProvableCollection($phpcsFile, $collectionArgument[0], $collectionArgument[1], $variables)
            && $this->isUnescapedReceiver($phpcsFile, $collectionArgument[0], $collectionArgument[1]);
    }

    private function isCommentFree(File $phpcsFile, int $start, int $end): bool
    {
        return $phpcsFile->findNext(Tokens::$commentTokens, $start, ($end + 1)) === false;
    }

    private function isUnescapedReceiver(File $phpcsFile, int $start, int $end): bool
    {
        $variable = $this->bareVariable($phpcsFile, $start, $end);

        if ($variable === null) {
            return true;
        }

        $scope = $this->scopeOf($phpcsFile, $variable);

        return isset($this->escapedVariables[$scope][$phpcsFile->getTokens()[$variable]['content']]) === false;
    }

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

            if (
                $name === false
                || $tokens[$name]['code'] !== T_STRING
            ) {
                return false;
            }

            $parenthesis = $phpcsFile->findNext(Tokens::$emptyTokens, ($name + 1), ($end + 1), true);

            if (
                $parenthesis === false
                || $tokens[$parenthesis]['code'] !== T_OPEN_PARENTHESIS
            ) {
                return false;
            }

            if (isset(self::CHAINABLE_METHODS[strtolower($tokens[$name]['content'])]) === false) {
                return false;
            }

            $ptr = $tokens[$parenthesis]['parenthesis_closer'];
        }
    }

    private function firstCollectionArgument(File $phpcsFile, array $arguments, array $variables): ?array
    {
        foreach ($arguments as $argument) {
            if ($this->isCollectionExpression($phpcsFile, $argument[0], $argument[1], $variables) === true) {
                return $argument;
            }
        }

        return null;
    }

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

        $phpcsFile->fixer
            ->beginChangeset();
        $phpcsFile->fixer
            ->replaceToken($start, $expression . '->' . $method . '()');

        for ($ptr = ($start + 1); $ptr <= $closer; $ptr++) {
            $phpcsFile->fixer
                ->replaceToken($ptr, '');
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

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

            if (in_array($tokens[$operator]['code'], [T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR], true) === false) {
                // Anything else trailing the expression (an operator, an array
                // access, …) means the value is no longer just the Collection.
                return false;
            }

            $name = $phpcsFile->findNext(Tokens::$emptyTokens, ($operator + 1), ($end + 1), true);

            if (
                $name === false
                || $tokens[$name]['code'] !== T_STRING
            ) {
                return false;
            }

            $parenthesis = $phpcsFile->findNext(Tokens::$emptyTokens, ($name + 1), ($end + 1), true);

            if (
                $parenthesis === false
                || $tokens[$parenthesis]['code'] !== T_OPEN_PARENTHESIS
            ) {
                // Property access, not a method call — its type is unknown.
                return false;
            }

            $lastMethod = strtolower($tokens[$name]['content']);
            $ptr = $tokens[$parenthesis]['parenthesis_closer'];
        }

        return $lastMethod === null
            || isset(self::TERMINAL_METHODS[$lastMethod]) === false;
    }

    private function collectionOriginEnd(
        File $phpcsFile,
        int $ptr,
        int $end,
        array $variables,
        bool $provable
    ): ?int {
        $functionCalls = $this->functionCalls;
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
                && $functionCalls->isGlobalFunctionCall($phpcsFile, $name['end']) === true;

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

    private function constructedCollectionEnd(File $phpcsFile, int $newPtr, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($newPtr + 1), ($end + 1), true);

        if (
            $classPtr === false
            || in_array($tokens[$classPtr]['code'], [T_NS_SEPARATOR, T_STRING], true) === false
        ) {
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

    private function factoryCallEnd(File $phpcsFile, int $doubleColonPtr, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $method = $phpcsFile->findNext(Tokens::$emptyTokens, ($doubleColonPtr + 1), ($end + 1), true);

        if (
            $method === false
            || $tokens[$method]['code'] !== T_STRING
        ) {
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

    private function nestedRegionEnd(array $token): ?int
    {
        return match ($token['code']) {
            T_OPEN_PARENTHESIS => $token['parenthesis_closer'] ?? null,
            T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET => $token['bracket_closer'] ?? null,
            T_OPEN_CURLY_BRACKET => $token['scope_closer'] ?? $token['bracket_closer'] ?? null,
            default => null,
        };
    }

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

    private function arrowParameterBinding(int $ptr, string $name, bool $provable): ?bool
    {
        $binding = null;
        $polarity = $provable === true ? 'provable' : 'reportable';

        // Built in T_FN order, so a later match is nested inside an earlier one
        // and the last one to declare the name is the innermost.
        foreach ($this->arrowFunctions as $arrowFunction) {
            if (
                $ptr < $arrowFunction['start']
                || $ptr > $arrowFunction['end']
            ) {
                continue;
            }

            $binding = isset($arrowFunction['parameters'][$name]) === true
                ? $arrowFunction['parameters'][$name][$polarity]
                : $binding;
        }

        return $binding;
    }

    private function isInsideArrowFunction(int $ptr): bool
    {
        foreach ($this->arrowFunctions as $arrowFunction) {
            if (
                $ptr >= $arrowFunction['start']
                && $ptr <= $arrowFunction['end']
            ) {
                return true;
            }
        }

        return false;
    }

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

    private function isLocalVariableTarget(File $phpcsFile, int $target): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$target]['code'] !== T_VARIABLE) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($target - 1), null, true);

        return $prev === false || $tokens[$prev]['code'] !== T_DOUBLE_COLON;
    }

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

    private function pastReferenceMarker(File $phpcsFile, int|false $previous): int|false
    {
        if (
            $previous === false
            || $phpcsFile->getTokens()[$previous]['code'] !== T_BITWISE_AND
        ) {
            return $previous;
        }

        return $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previous - 1), null, true);
    }

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

    private function shortName(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, ($position + 1));
    }
}
