<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowTypeIntrospectionSniff implements Sniff
{
    private const INTROSPECTION_FUNCTIONS = [
        'get_class',
        'get_debug_type',
        'gettype',
        'is_a',
        'is_subclass_of',
    ];

    private const CONDITION_OWNERS = [
        T_ELSEIF,
        T_IF,
        T_MATCH,
        T_SWITCH,
        T_WHILE,
    ];

    private const EXPRESSION_BODY_OWNERS = [
        T_ANON_CLASS,
        T_CLOSURE,
        T_MATCH,
    ];

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

    private const FUNCTION_LIKE = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    private const OO_SCOPES = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    private ?string $indexKey = null;

    private array $cacheCounts = [
        'indexes.builds' => 0,
        'indexes.hits' => 0,
        'ternaryDecisions.builds' => 0,
        'ternaryDecisions.hits' => 0,
    ];

    private ?array $shadowedNames = null;

    private ?array $functionBodies = null;

    private ?array $enclosingBodies = null;

    private ?array $ternaryDecisions = null;

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls(),
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function register(): array
    {
        return [T_INSTANCEOF, T_STRING];
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function process(File $phpcsFile, $stackPtr): void
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
            ["{$tokens[$stackPtr]['content']}()"]
        );
    }

    private function isGlobalCallAccountingForShadowing(File $phpcsFile, int $stackPtr): bool
    {
        $functionCalls = $this->functionCalls;

        if ($functionCalls->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
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
        if (
            $prev !== false
            && $tokens[$prev]['code'] === T_NS_SEPARATOR
        ) {
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

    private function decidesWhereItSits(File $phpcsFile, int $probe, ?array $scope): bool
    {
        return $this->isInsideAConditionParenthesis($phpcsFile, $probe, $scope)
            || $this->isATernaryCondition($phpcsFile, $probe, $scope);
    }

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

    private function index(File $phpcsFile): void
    {
        $tokenStreams = $this->tokenStreams;

        $key = $tokenStreams->key($phpcsFile);

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

    private function buildFunctionBodies(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $bodies = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if (in_array($tokens[$i]['code'], self::FUNCTION_LIKE, true)) {
                $opener = $tokens[$i]['scope_opener'] ?? null;
                $closer = $tokens[$i]['scope_closer'] ?? null;

                if (
                    $opener !== null
                    && $closer !== null
                ) {
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

    private function isHookList(File $phpcsFile, int $stackPtr): bool
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] !== T_OPEN_CURLY_BRACKET) {
            return false;
        }

        if (
            isset($token['bracket_closer']) === false
            || isset($token['scope_opener'])
        ) {
            return false;
        }

        $conditions = $token['conditions'];

        return $conditions !== [] && in_array(end($conditions), self::OO_SCOPES, true);
    }

    private function hookBodies(File $phpcsFile, int $listPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$listPtr]['bracket_closer'];
        $bodies = [];
        $i = ($listPtr + 1);

        while ($i < $closer) {
            $code = $tokens[$i]['code'];

            if (
                $code === T_DOUBLE_ARROW
                || $code === T_OPEN_CURLY_BRACKET
            ) {
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

    private function endOfArrowHook(array $tokens, int $start, int $closer): int|false
    {
        for ($i = $start; $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_SEMICOLON) {
                return $i;
            }

            $i = $this->skipGroupForward($tokens, $i);
        }

        return false;
    }

    private function buildEnclosingBodies(File $phpcsFile, array $bodies): array
    {
        $enclosing = [];
        $open = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            while (
                $open !== []
                && $bodies[end($open)] <= $i
            ) {
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

            if (
                $owner !== null
                && in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function isATernaryCondition(File $phpcsFile, int $stackPtr, ?array $scope): bool
    {
        $tokens = $phpcsFile->getTokens();
        $limit = $scope === null ? $phpcsFile->numTokens : $scope['end'];
        $this->index($phpcsFile);

        // The same guard the `??=` this replaces expressed, written out so the
        // build and the read that answers from it can be counted apart. The
        // scale test in tests/Standards/DisallowTypeIntrospectionTest.php reads
        // that pair: one backward pass per file against one per check is the
        // difference between linear and quadratic here, and it has no observable
        // other than these counts or the elapsed time they replace (#354).
        $this->ensureTernaryDecisions($phpcsFile);

        $decision = $this->ternaryDecisions[$stackPtr + 1] ?? $phpcsFile->numTokens;

        return $decision < $limit
            && $tokens[$decision]['code'] === T_INLINE_THEN;
    }

    // Builds the map once per file and counts the build apart from every read
    // that answers from it. The scale test reads that pair: one backward pass
    // per file against one per check is the difference between linear and
    // quadratic here (#354).
    private function ensureTernaryDecisions(File $phpcsFile): void
    {
        if ($this->ternaryDecisions !== null) {
            $this->cacheCounts['ternaryDecisions.hits']++;

            return;
        }

        $this->cacheCounts['ternaryDecisions.builds']++;
        $this->ternaryDecisions = $this->buildTernaryDecisions($phpcsFile);
    }

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

    private function skipGroupForward(array $tokens, int $stackPtr): int
    {
        $code = $tokens[$stackPtr]['code'];

        if (
            $code === T_OPEN_PARENTHESIS
            && isset($tokens[$stackPtr]['parenthesis_closer'])
        ) {
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

    private function skipGroupBackward(array $tokens, int $stackPtr): int
    {
        $code = $tokens[$stackPtr]['code'];

        if (
            $code === T_CLOSE_PARENTHESIS
            && isset($tokens[$stackPtr]['parenthesis_opener'])
        ) {
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
