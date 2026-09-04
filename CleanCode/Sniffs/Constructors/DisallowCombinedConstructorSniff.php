<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowCombinedConstructorSniff implements Sniff
{
    private const CLASS_LIKE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT];

    private const NESTED_DECLARATIONS = [T_FUNCTION, T_CLOSURE, T_FN];

    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_SWITCH, T_MATCH];

    private const TYPE_PREDICATES = [
        'get_class',
        'get_debug_type',
        'gettype',
        'is_a',
        'is_array',
        'is_bool',
        'is_callable',
        'is_countable',
        'is_double',
        'is_float',
        'is_int',
        'is_integer',
        'is_iterable',
        'is_long',
        'is_numeric',
        'is_object',
        'is_resource',
        'is_scalar',
        'is_string',
        'is_subclass_of',
    ];

    private const EXPRESSION_TERMINATORS = [
        T_DOUBLE_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_SEMICOLON,
    ];

    private const ARGUMENT_READERS = ['func_get_args', 'func_num_args'];

    private const INDIRECTION_PRECEDERS = [
        T_DOLLAR,
        T_DOUBLE_COLON,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
        T_OPEN_CURLY_BRACKET,
        T_OPEN_SQUARE_BRACKET,
    ];

    private const INDIRECTION_FOLLOWERS = [
        T_DOUBLE_COLON,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
        T_OPEN_SQUARE_BRACKET,
    ];

    private const NESTING_OPENERS = [
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
    ];

    private const NESTING_CLOSERS = [
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_SQUARE_BRACKET,
    ];

    private const GROUP_OPENERS = [
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
    ];

    private const GROUPING_PRECEDERS = [
        T_BOOLEAN_NOT,
        T_CASE,
        T_CLOSE_CURLY_BRACKET,
        T_COLON,
        T_COMMA,
        T_FN_ARROW,
        T_INLINE_ELSE,
        T_INLINE_THEN,
        T_MATCH_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
        T_SEMICOLON,
    ];

    private array $commaTargets = [];

    private array $selectorCache = [];

    private array $branchVerdicts = [];

    private array $chainHeadCache = [];

    private array $ternaryElse = [];

    private array $cacheCounts = [
        'ternarySides.walks' => 0,
        'ternarySides.hits' => 0,
        'selectorCache.steps' => 0,
        'selectorCache.hits' => 0,
        'branchVerdicts.walks' => 0,
        'branchVerdicts.hits' => 0,
        'chainHead.steps' => 0,
        'chainHead.hits' => 0,
    ];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls()
    ) {
    }

    // The import-scan counters this sniff's FunctionCalls holds, exposed the way
    // MultiLineStatementIndentSniff exposes scanCounts(): the cache moved from a
    // class-wide static to an instance when statics were removed, so a test can
    // no longer read it off the helper class and reads it off the very sniff
    // instance buildRuleset() memoised instead.
    public function analysisCounts(): array
    {
        $functionCalls = $this->functionCalls;

        return $functionCalls->analysisCounts();
    }

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || strtolower($name) !== '__construct'
        ) {
            return;
        }

        $enclosing = $tokens[$stackPtr]['conditions'];

        if (! in_array(end($enclosing), self::CLASS_LIKE_SCOPES, true)) {
            return;
        }

        // An abstract or interface constructor has no body to walk. A
        // promotion-only constructor has one, and simply holds no statements.
        if (! isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $parameters = $this->parameterTypes($phpcsFile, $stackPtr);
        $closer = $tokens[$stackPtr]['scope_closer'];

        // Every map below describes this constructor's body alone, and the
        // scan's answers depend on where that body ends, so none of them
        // survives into the next constructor.
        $this->selectorCache = [];
        $this->branchVerdicts = [];
        $this->chainHeadCache = [];
        $this->ternaryElse = [];
        $this->buildCommaMap($phpcsFile, $tokens[$stackPtr]['scope_opener'], $closer);

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            $skip = $this->declarationSkip($phpcsFile, $pointer);

            if ($skip !== null) {
                $pointer = $skip;

                continue;
            }

            $rebinding = $this->rebindingEnd($phpcsFile, $pointer, $closer);

            if ($rebinding !== null) {
                $parameters = array_diff_key($parameters, $this->boundNames($phpcsFile, $pointer, $rebinding));

                // A `static` local's initializer is an arbitrary expression, so
                // it is the constructor's own code and is walked on into. The
                // other three regions hold targets alone and are stepped past
                // whole ({@see self::rebindingEnd()}).
                if ($code !== T_STATIC) {
                    $pointer = $rebinding;
                }

                continue;
            }

            if (
                $code === T_STRING
                && $this->isArgumentReader($phpcsFile, $pointer)
            ) {
                $this->reportArgumentReader($phpcsFile, $pointer, $closer);

                continue;
            }

            if (
                $code === T_VARIABLE
                && array_key_exists($tokens[$pointer]['content'], $parameters)
            ) {
                $this->reportModeSwitch($phpcsFile, $pointer, $closer, $parameters);
            }
        }
    }

    private function declarationSkip(File $phpcsFile, int $pointer): ?int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        if (! isset($token['scope_closer'])) {
            return null;
        }

        $owner = $token['scope_condition'] ?? null;
        $anonymousClassBody = $token['code'] === T_OPEN_CURLY_BRACKET
            && $owner !== null
            && $phpcsFile->getTokens()[$owner]['code'] === T_ANON_CLASS;

        return in_array($token['code'], self::NESTED_DECLARATIONS, true) || $anonymousClassBody
            ? (int) $token['scope_closer']
            : null;
    }

    private function rebindingEnd(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$pointer]['code'];

        if ($code === T_AS) {
            return $this->foreachHeaderEnd($phpcsFile, $pointer);
        }

        if ($code === T_CATCH) {
            return isset($tokens[$pointer]['parenthesis_closer'])
                ? (int) $tokens[$pointer]['parenthesis_closer']
                : null;
        }

        if (
            $code !== T_GLOBAL
            && ! ($code === T_STATIC && $this->declaresLocals($phpcsFile, $pointer))
        ) {
            return null;
        }

        $semicolon = $phpcsFile->findNext(T_SEMICOLON, $pointer + 1, $closer);

        return $semicolon === false ? null : (int) $semicolon;
    }

    private function foreachHeaderEnd(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $openers = array_keys($tokens[$pointer]['nested_parenthesis'] ?? []);

        foreach (array_reverse($openers) as $opener) {
            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if (
                $owner !== null
                && $tokens[$owner]['code'] === T_FOREACH
            ) {
                return (int) $tokens[$owner]['parenthesis_closer'];
            }
        }

        return null;
    }

    private function declaresLocals(File $phpcsFile, int $pointer): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        return $next !== false && $phpcsFile->getTokens()[$next]['code'] === T_VARIABLE;
    }

    private function boundNames(File $phpcsFile, int $from, int $to): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];
        $depth = 0;
        $initializing = false;

        for ($pointer = $from + 1; $pointer < $to; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::NESTING_OPENERS, true)) {
                $depth++;

                continue;
            }

            if (in_array($code, self::NESTING_CLOSERS, true)) {
                $depth--;

                continue;
            }

            if (
                $depth === 0
                && ($code === T_EQUAL || $code === T_COMMA)
            ) {
                $initializing = $code === T_EQUAL;

                continue;
            }

            if (
                ! $initializing
                && $code === T_VARIABLE
                && $this->bindsName($phpcsFile, $pointer, $depth)
            ) {
                $names[$tokens[$pointer]['content']] = true;
            }
        }

        return $names;
    }

    private function bindsName(File $phpcsFile, int $pointer, int $depth): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        if (
            $before !== false
            && in_array($tokens[$before]['code'], self::INDIRECTION_PRECEDERS, true)
        ) {
            return false;
        }

        if ($after === false) {
            return true;
        }

        if (in_array($tokens[$after]['code'], self::INDIRECTION_FOLLOWERS, true)) {
            return false;
        }

        return $tokens[$after]['code'] !== T_DOUBLE_ARROW || $depth === 0;
    }

    private function parameterTypes(File $phpcsFile, int $stackPtr): array
    {
        $parameters = [];

        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            if ($parameter['variable_length'] === true) {
                continue;
            }

            $written = (string) $parameter['type_hint'];

            // The written hint rather than '' on a failed read: '' resolves to
            // no members at all, which reads exactly like a hint that is not
            // boolean, so the failure would silently drop the parameter's mode
            // signal. `/\s+/` is one auto-possessified quantifier with no `/u`
            // modifier, so preg_replace() cannot fail.
            $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $written) ?? $written), '?');
            $types = array_values(array_diff(explode('|', $normalized), ['null', '']));
            $default = strtolower(trim((string) ($parameter['default'] ?? '')));

            $parameters[$parameter['name']] = $types === ['bool']
                || in_array($default, ['true', 'false'], true);
        }

        return $parameters;
    }

    private function reportArgumentReader(File $phpcsFile, int $pointer, int $closer): void
    {
        $branch = $this->branchOwner($phpcsFile, $pointer, $closer);

        if (
            $branch !== null
            && $this->isGuardClause($phpcsFile, $branch, $closer)
        ) {
            return;
        }

        $phpcsFile->addWarning(
            'Reading the constructor\'s own argument list with %s() overloads __construct()'
                . ' into several constructors; give each construction scenario its own named'
                . ' constructor delegating to one primary constructor'
                . ' (see docs/standards/constructors-primary-named-constructors.md)',
            $pointer,
            'ArgumentCount',
            [$phpcsFile->getTokens()[$pointer]['content']]
        );
    }

    private function reportModeSwitch(File $phpcsFile, int $pointer, int $closer, array $parameters): void
    {
        $typeTested = $this->isTypeTested($phpcsFile, $pointer);

        if (
            ! $typeTested
            && $parameters[$phpcsFile->getTokens()[$pointer]['content']] === false
        ) {
            return;
        }

        $branch = $this->branchOwner($phpcsFile, $pointer, $closer);

        if (
            $branch === null
            || $this->isGuardClause($phpcsFile, $branch, $closer)
        ) {
            return;
        }

        $name = $phpcsFile->getTokens()[$pointer]['content'];

        if ($typeTested) {
            $phpcsFile->addWarning(
                'Branching on the runtime type of %s combines several constructors into'
                    . ' __construct(); give each accepted type its own named constructor'
                    . ' delegating to one primary constructor'
                    . ' (see docs/standards/constructors-primary-named-constructors.md)',
                $pointer,
                'TypeSwitch',
                [$name]
            );

            return;
        }

        $phpcsFile->addWarning(
            'Branching on the mode flag %s combines several constructors into __construct();'
                . ' give each mode its own named constructor delegating to one primary'
                . ' constructor'
                . ' (see docs/standards/constructors-primary-named-constructors.md)',
            $pointer,
            'ModeFlag',
            [$name]
        );
    }

    private function isTypeTested(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $start = $pointer;
        $end = $pointer;

        if ($this->isInstanceofSubject($phpcsFile, $end)) {
            return true;
        }

        foreach (array_reverse($tokens[$pointer]['nested_parenthesis'] ?? [], true) as $opener => $closer) {
            if (
                $this->isTypePredicate($phpcsFile, (int) $opener)
                && $this->isBareFirstArgument($phpcsFile, (int) $opener, (int) $closer, $start, $end)
            ) {
                return true;
            }

            if (
                ! $this->opensGrouping($phpcsFile, (int) $opener)
                || ! $this->wrapsNothingElse($phpcsFile, (int) $opener, (int) $closer, $start, $end)
            ) {
                return false;
            }

            $start = (int) $opener;
            $end = (int) $closer;

            if ($this->isInstanceofSubject($phpcsFile, $end)) {
                return true;
            }
        }

        return false;
    }

    private function isInstanceofSubject(File $phpcsFile, int $end): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);

        return $next !== false && $phpcsFile->getTokens()[$next]['code'] === T_INSTANCEOF;
    }

    private function opensGrouping(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);

        if (
            $before === false
            || ! isset($this->groupingPreceders()[$tokens[$before]['code']])
        ) {
            return false;
        }

        return $tokens[$before]['code'] !== T_CLOSE_CURLY_BRACKET
            || $this->closesBlock($phpcsFile, $before);
    }

    private function closesBlock(File $phpcsFile, int $pointer): bool
    {
        return isset($phpcsFile->getTokens()[$pointer]['scope_condition']);
    }

    private function groupingPreceders(): array
    {
        static $preceders = null;

        return $preceders ??= Tokens::$operators
            + Tokens::$booleanOperators
            + Tokens::$comparisonTokens
            + Tokens::$assignmentTokens
            + Tokens::$castTokens
            + array_combine(self::GROUPING_PRECEDERS, self::GROUPING_PRECEDERS);
    }

    private function isTypePredicate(File $phpcsFile, int $opener): bool
    {
        $functionCalls = $this->functionCalls;

        $tokens = $phpcsFile->getTokens();
        $callee = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);

        return $callee !== false
            && $tokens[$callee]['code'] === T_STRING
            && in_array(strtolower($tokens[$callee]['content']), self::TYPE_PREDICATES, true)
            && $functionCalls->isGlobalFunctionCall($phpcsFile, $callee);
    }

    private function wrapsNothingElse(File $phpcsFile, int $opener, int $closer, int $start, int $end): bool
    {
        return $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, null, true) === $start
            && $phpcsFile->findPrevious(Tokens::$emptyTokens, $closer - 1, null, true) === $end;
    }

    private function isBareFirstArgument(File $phpcsFile, int $opener, int $closer, int $start, int $end): bool
    {
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $start - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);

        return $before === $opener
            && $after !== false
            && ($after === $closer || $phpcsFile->getTokens()[$after]['code'] === T_COMMA);
    }

    private function isArgumentReader(File $phpcsFile, int $pointer): bool
    {
        $functionCalls = $this->functionCalls;

        if (! in_array(strtolower($phpcsFile->getTokens()[$pointer]['content']), self::ARGUMENT_READERS, true)) {
            return false;
        }

        if (! $functionCalls->isGlobalFunctionCall($phpcsFile, $pointer)) {
            return false;
        }

        // The helper has already established that this is the call's own
        // opening parenthesis.
        $opener = (int) $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        return ! $this->isFirstClassCallable($phpcsFile, $opener);
    }

    private function isFirstClassCallable(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, null, true);

        if (
            $ellipsis === false
            || $tokens[$ellipsis]['code'] !== T_ELLIPSIS
        ) {
            return false;
        }

        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $ellipsis + 1, null, true);

        return $after !== false && $tokens[$after]['code'] === T_CLOSE_PARENTHESIS;
    }

    private function branchOwner(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();

        $openers = array_keys($tokens[$pointer]['nested_parenthesis'] ?? []);

        foreach (array_reverse($openers) as $opener) {
            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if (
                $owner !== null
                && in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true)
            ) {
                return (int) $owner;
            }
        }

        return $this->followingSelector($phpcsFile, $pointer, $closer);
    }

    private function followingSelector(File $phpcsFile, int $pointer, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $visited = [];

        for ($next = $pointer + 1; $next < $closer; $next++) {
            if (array_key_exists($next, $this->selectorCache)) {
                $this->cacheCounts['selectorCache.hits']++;

                return $this->remember($visited, $this->selectorCache[$next]);
            }

            $visited[] = $next;
            $code = $tokens[$next]['code'];

            if ($code === T_MATCH_ARROW) {
                return $this->remember($visited, $next);
            }

            // `?:` supplies a default for one expression rather than selecting
            // between two, so it is not a branch.
            if ($code === T_INLINE_THEN) {
                $following = $phpcsFile->findNext(Tokens::$emptyTokens, $next + 1, null, true);
                $elvis = $following !== false && $tokens[$following]['code'] === T_INLINE_ELSE;

                return $this->remember($visited, $elvis ? null : $next);
            }

            if (
                $code === T_COLON
                && $this->isCaseColon($phpcsFile, $next)
            ) {
                return $this->remember($visited, $tokens[$next]['scope_condition']);
            }

            if ($code === T_COMMA) {
                if (! isset($this->commaTargets[$next])) {
                    return $this->remember($visited, null);
                }

                $next = $this->commaTargets[$next];

                continue;
            }

            if (in_array($code, self::EXPRESSION_TERMINATORS, true)) {
                return $this->remember($visited, null);
            }

            // A group opening here belongs to the expression — jump it whole so
            // its contents cannot be mistaken for the expression's own tokens.
            $next = $this->groupEnd($phpcsFile, $next) ?? $next;
        }

        return $this->remember($visited, null);
    }

    private function remember(array $visited, ?int $selector): ?int
    {
        // Every position this scan stepped on, whether it ran to a selector or
        // stopped on one already recorded. Summed over a constructor, this is
        // the whole of what $selectorCache buys: one step per position of the
        // body rather than one full-length scan per parameter use.
        $this->cacheCounts['selectorCache.steps'] += count($visited);

        foreach ($visited as $position) {
            $this->selectorCache[$position] = $selector;
        }

        return $selector;
    }

    private function buildCommaMap(File $phpcsFile, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $this->commaTargets = [];

        $groups = [];

        for ($pointer = $opener + 1; $pointer < $closer; $pointer++) {
            $depth = count($groups) - 1;

            if (
                $depth >= 0
                && $pointer === $groups[$depth]['closer']
            ) {
                array_pop($groups);

                continue;
            }

            $end = $this->openedGroupEnd($phpcsFile, $pointer);

            if ($end !== null) {
                $arms = $this->opensMatchArms($phpcsFile, $pointer);
                $groups[] = [
                    'closer' => $end,
                    'arms' => $arms,
                    'block' => ! $arms && $tokens[$pointer]['code'] === T_OPEN_CURLY_BRACKET,
                    'armBody' => false,
                ];

                continue;
            }

            if (
                $depth < 0
                || $groups[$depth]['block'] === true
            ) {
                continue;
            }

            $code = $tokens[$pointer]['code'];

            if (
                $code === T_MATCH_ARROW
                && $groups[$depth]['arms']
            ) {
                $groups[$depth]['armBody'] = true;

                continue;
            }

            if ($code !== T_COMMA) {
                continue;
            }

            if ($groups[$depth]['arms'] === false) {
                $this->commaTargets[$pointer] = $groups[$depth]['closer'];

                continue;
            }

            if ($groups[$depth]['armBody'] === true) {
                // The arm before this comma ends here; the arms after it are
                // their own expressions.
                $groups[$depth]['armBody'] = false;

                continue;
            }

            $this->commaTargets[$pointer] = $pointer;
        }
    }

    private function openedGroupEnd(File $phpcsFile, int $pointer): ?int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        if (! in_array($token['code'], self::GROUP_OPENERS, true)) {
            return null;
        }

        $end = $token['parenthesis_closer'] ?? $token['bracket_closer'] ?? null;

        return $end !== null && $end > $pointer ? (int) $end : null;
    }

    private function opensMatchArms(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $owner = $tokens[$pointer]['scope_condition'] ?? null;

        return $owner !== null && $tokens[$owner]['code'] === T_MATCH;
    }

    private function groupEnd(File $phpcsFile, int $pointer): ?int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        foreach (['scope_closer', 'parenthesis_closer', 'bracket_closer'] as $key) {
            if (
                isset($token[$key])
                && $token[$key] > $pointer
            ) {
                return (int) $token[$key];
            }
        }

        return null;
    }

    private function isCaseColon(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $owner = $tokens[$pointer]['scope_condition'] ?? null;

        return $owner !== null && in_array($tokens[$owner]['code'], [T_CASE, T_DEFAULT], true);
    }

    private function isGuardClause(File $phpcsFile, int $branch, int $closer): bool
    {
        [$construct, $own] = $this->constructOf($phpcsFile, $branch);

        if ($construct === null) {
            return false;
        }

        $verdicts = $this->branchVerdicts($phpcsFile, $construct, $closer);
        $ownThrows = $own !== null && ($verdicts['throws'][$own] ?? false);

        return $verdicts['throwing'] > 0 && ($ownThrows || $verdicts['surviving'] <= 1);
    }

    private function branchVerdicts(File $phpcsFile, int $construct, int $closer): array
    {
        if (isset($this->branchVerdicts[$construct])) {
            $this->cacheCounts['branchVerdicts.hits']++;

            return $this->branchVerdicts[$construct];
        }

        $this->cacheCounts['branchVerdicts.walks']++;
        $verdicts = ['throws' => [], 'throwing' => 0, 'surviving' => 0];

        foreach ($this->branchStarts($phpcsFile, $construct, $closer) as $pointer => $start) {
            $throws = $this->firstStatementThrows($phpcsFile, $start);
            $verdicts['throws'][$pointer] = $throws;

            $throws ? $verdicts['throwing']++ : $verdicts['surviving']++;
        }

        $this->branchVerdicts[$construct] = $verdicts;

        return $verdicts;
    }

    private function constructOf(File $phpcsFile, int $branch): array
    {
        $code = $phpcsFile->getTokens()[$branch]['code'];

        if (
            $code === T_IF
            || $code === T_ELSEIF
        ) {
            return [$this->chainHead($phpcsFile, $branch), $branch];
        }

        if (
            $code === T_CASE
            || $code === T_DEFAULT
        ) {
            return [$this->enclosingConstruct($phpcsFile, $branch, T_SWITCH), $branch];
        }

        if ($code === T_MATCH_ARROW) {
            return [$this->enclosingConstruct($phpcsFile, $branch, T_MATCH), $branch];
        }

        // A `switch`/`match` subject, or a ternary's condition: one condition
        // stands in front of every branch, so none of them is its own.
        return [$branch, null];
    }

    private function branchStarts(File $phpcsFile, int $construct, int $closer): array
    {
        $code = $phpcsFile->getTokens()[$construct]['code'];

        if ($code === T_SWITCH) {
            return $this->caseStarts($phpcsFile, $construct);
        }

        if ($code === T_MATCH) {
            return $this->armStarts($phpcsFile, $construct);
        }

        if ($code === T_INLINE_THEN) {
            return $this->ternarySides($phpcsFile, $construct, $closer);
        }

        return $this->chainStarts($phpcsFile, $construct);
    }

    private function chainHead(File $phpcsFile, int $branch): int
    {
        $tokens = $phpcsFile->getTokens();
        $head = $branch;
        $visited = [];

        while (true) {
            if (isset($this->chainHeadCache[$head])) {
                $this->cacheCounts['chainHead.hits']++;

                return $this->rememberChainHead($visited, $this->chainHeadCache[$head]);
            }

            // One link stepped over. Summed across a chain, this is the whole
            // of what $chainHeadCache buys: one step per link rather than one
            // walk of the chain per link in it.
            $this->cacheCounts['chainHead.steps']++;
            $visited[] = $head;
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $head - 1, null, true);

            // A spaced `else if` is a T_ELSE and a T_IF: the `if` owns the
            // condition, and the chain carries on in front of the `else`.
            if (
                $previous !== false
                && $tokens[$previous]['code'] === T_ELSE
            ) {
                $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);
            } elseif ($tokens[$head]['code'] !== T_ELSEIF) {
                return $this->rememberChainHead($visited, $head);
            }

            if (
                $previous === false
                || $tokens[$previous]['code'] !== T_CLOSE_CURLY_BRACKET
            ) {
                return $this->rememberChainHead($visited, $head);
            }

            $owner = $tokens[$previous]['scope_condition'] ?? null;

            if (
                $owner === null
                || ! in_array($tokens[$owner]['code'], [T_IF, T_ELSEIF], true)
            ) {
                return $this->rememberChainHead($visited, $head);
            }

            $head = (int) $owner;
        }
    }

    private function rememberChainHead(array $visited, int $head): int
    {
        foreach ($visited as $link) {
            $this->chainHeadCache[$link] = $head;
        }

        return $head;
    }

    private function chainStarts(File $phpcsFile, int $head): array
    {
        $tokens = $phpcsFile->getTokens();
        $starts = [];
        $link = $head;

        while (true) {
            $starts[$link] = $this->branchStart($phpcsFile, $link);
            $end = $this->branchEnd($phpcsFile, $link);
            $next = $end === null ? false : $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);

            if (
                $next === false
                || ! in_array($tokens[$next]['code'], [T_ELSE, T_ELSEIF], true)
            ) {
                return $starts;
            }

            $following = $phpcsFile->findNext(Tokens::$emptyTokens, $next + 1, null, true);
            $spacedElseIf = $tokens[$next]['code'] === T_ELSE
                && $following !== false
                && $tokens[$following]['code'] === T_IF;

            $link = $spacedElseIf ? (int) $following : (int) $next;

            if (isset($starts[$link])) {
                return $starts;
            }
        }
    }

    private function branchStart(File $phpcsFile, int $branch): int
    {
        $token = $phpcsFile->getTokens()[$branch];

        return (int) ($token['scope_opener'] ?? $token['parenthesis_closer'] ?? $branch);
    }

    private function branchEnd(File $phpcsFile, int $branch): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$branch]['scope_closer'])) {
            return (int) $tokens[$branch]['scope_closer'];
        }

        $start = $this->branchStart($phpcsFile, $branch);
        $statement = $phpcsFile->findNext(Tokens::$emptyTokens, $start + 1, null, true);

        return $statement === false ? null : (int) $phpcsFile->findEndOfStatement($statement);
    }

    private function caseStarts(File $phpcsFile, int $switch): array
    {
        $tokens = $phpcsFile->getTokens();

        if (! isset($tokens[$switch]['scope_opener'], $tokens[$switch]['scope_closer'])) {
            return [];
        }

        $end = (int) $tokens[$switch]['scope_closer'];
        $starts = [];

        for ($pointer = $tokens[$switch]['scope_opener'] + 1; $pointer < $end; $pointer++) {
            if (! in_array($tokens[$pointer]['code'], [T_CASE, T_DEFAULT], true)) {
                continue;
            }

            if (
                ! isset($tokens[$pointer]['scope_opener'])
                || ! $this->isDirectBranchOf($phpcsFile, $pointer, $switch)
            ) {
                continue;
            }

            $opener = (int) $tokens[$pointer]['scope_opener'];
            $first = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, $end, true);

            // An empty fall-through case has no body of its own to judge.
            if (
                $first === false
                || in_array($tokens[$first]['code'], [T_CASE, T_DEFAULT], true)
            ) {
                continue;
            }

            $starts[$pointer] = $opener;
        }

        return $starts;
    }

    private function armStarts(File $phpcsFile, int $match): array
    {
        $tokens = $phpcsFile->getTokens();

        if (! isset($tokens[$match]['scope_opener'], $tokens[$match]['scope_closer'])) {
            return [];
        }

        $end = (int) $tokens[$match]['scope_closer'];
        $starts = [];

        for ($pointer = $tokens[$match]['scope_opener'] + 1; $pointer < $end; $pointer++) {
            if (
                $tokens[$pointer]['code'] !== T_MATCH_ARROW
                || ! $this->isDirectBranchOf($phpcsFile, $pointer, $match)
            ) {
                continue;
            }

            $starts[$pointer] = $pointer;
        }

        return $starts;
    }

    private function ternarySides(File $phpcsFile, int $then, int $closer): array
    {
        if (array_key_exists($then, $this->ternaryElse)) {
            $this->cacheCounts['ternarySides.hits']++;
            $settled = $this->ternaryElse[$then];

            return $settled === null ? [$then => $then] : [$then => $then, $settled => $settled];
        }

        $this->cacheCounts['ternarySides.walks']++;
        $tokens = $phpcsFile->getTokens();
        $opened = [$then];

        for ($pointer = $then + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_SEMICOLON) {
                break;
            }

            if ($code === T_INLINE_THEN) {
                $opened[] = $pointer;

                continue;
            }

            if ($code === T_INLINE_ELSE) {
                $matched = array_pop($opened);
                $this->ternaryElse[$matched] = $pointer;

                if ($opened === []) {
                    return [$then => $then, $pointer => $pointer];
                }

                continue;
            }

            $pointer = $this->groupEnd($phpcsFile, $pointer) ?? $pointer;
        }

        foreach ($opened as $unmatched) {
            $this->ternaryElse[$unmatched] = null;
        }

        return [$then => $then];
    }

    private function enclosingConstruct(File $phpcsFile, int $pointer, int|string $type): ?int
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_reverse($tokens[$pointer]['conditions'] ?? [], true) as $owner => $code) {
            if ($code === $type) {
                return (int) $owner;
            }
        }

        return null;
    }

    private function firstStatementThrows(File $phpcsFile, int $from): bool
    {
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $from + 1, null, true);

        return $first !== false && $phpcsFile->getTokens()[$first]['code'] === T_THROW;
    }

    private function isDirectBranchOf(File $phpcsFile, int $pointer, int $owner): bool
    {
        $conditions = array_keys($phpcsFile->getTokens()[$pointer]['conditions'] ?? []);

        return end($conditions) === $owner;
    }
}
