<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Arrays;

use MikeBronner\CleanCode\Helpers\NameTokens;
use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use ReflectionFunction;

// phpcs:ignore CleanCode.Classes.ExcessiveClassLength
class ArrayAccessorsSniff implements Sniff
{
    private const MESSAGES = [
        'DirectArrayAccess' => 'Direct array element access on %s is not allowed;'
            . ' use data_get(%s, ...) so a missing element falls back instead of erroring',
        'DirectPropertyAccess' => 'Direct property access on %s is not allowed;'
            . ' use data_get(%s, ...) so any object shape resolves without type checks',
    ];

    private const VERDICT_TARGET = 'target';

    private const VERDICT_OFFSET = 'offset';

    private const STEP_TRANSPARENT = 'transparent';

    private const STEP_UNDECIDABLE = 'undecidable';

    private const STEP_ROOT_DEPENDENT = 'root-dependent';

    private const OBJECT_OPERATORS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];

    private const CHAIN_ROOT_TOKENS = [T_VARIABLE, T_DOLLAR];

    private const ENCLOSING_OPENERS = [
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_PARENTHESIS,
        T_OPEN_CURLY_BRACKET,
    ];

    private const EXISTENCE_CHECKS = [
        'isset' => true,
        'empty' => true,
        'unset' => true,
        'array_key_exists' => true,
    ];

    private const VARIADIC_REACH = 64;

    private array $byReferenceCache = [];

    private ?string $enclosureMapKey = null;

    private array $cacheCounts = [
        'enclosureMap.builds' => 0,
        'enclosureMap.hits' => 0,
        'enclosureVerdict.walks' => 0,
        'enclosureVerdict.steps' => 0,
        'decidingStep.hops' => 0,
        'decidingStep.hits' => 0,
    ];

    private array $innermostCloser = [];

    private array $parentCloser = [];

    private array $lastUnterminatedOpener = [];

    private array $outwardSteps = [];

    private array $decidingSteps = [];

    private array $foreachClauseAsPtrs = [];

    private array $existenceCheckOpeners = [];

    public function __construct(
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function register(): array
    {
        return [T_VARIABLE];
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['content'] === '$this') {
            return;
        }

        $rootPtr = $this->chainRoot($phpcsFile, $stackPtr);

        if ($this->isChainMember($phpcsFile, $rootPtr) === true) {
            return;
        }

        $accessorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($accessorPtr === false) {
            return;
        }

        $errorCode = $this->readAccessCode($phpcsFile, $accessorPtr);

        if ($errorCode === null) {
            return;
        }

        if ($this->isWriteTarget($phpcsFile, $rootPtr, $stackPtr) === true) {
            return;
        }

        if ($this->isInsideExistenceCheck($phpcsFile, $rootPtr) === true) {
            return;
        }

        $variable = $this->chainRootName($phpcsFile, $rootPtr, $stackPtr);
        $path = $this->readPath($phpcsFile, $accessorPtr);

        if (
            $path === null
            || $this->isByReferenceArgument($phpcsFile, $rootPtr) === true
        ) {
            $phpcsFile->addError(
                self::MESSAGES[$errorCode],
                $rootPtr,
                $errorCode,
                [$variable, $variable]
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            self::MESSAGES[$errorCode],
            $rootPtr,
            $errorCode,
            [$variable, $variable]
        );

        if ($fix === true) {
            $targetStartPtr = $this->targetStart($phpcsFile, $rootPtr);

            $this->replaceWithDataGet(
                $phpcsFile,
                $targetStartPtr,
                $path['end'],
                $this->chainRootName($phpcsFile, $targetStartPtr, $stackPtr),
                $path['segments']
            );
        }
    }

    private function targetStart(File $phpcsFile, int $rootPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $operatorPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($rootPtr - 1), null, true);

        if (
            $operatorPtr === false
            || $tokens[$operatorPtr]['code'] !== T_DOUBLE_COLON
        ) {
            return $rootPtr;
        }

        $startPtr = null;
        $cursorPtr = $operatorPtr;
        $qualifier = [T_STRING, T_SELF, T_STATIC, T_PARENT, ...NameTokens::QUALIFIED];

        while (true) {
            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($cursorPtr - 1), null, true);

            if (
                $previousPtr === false
                || in_array($tokens[$previousPtr]['code'], $qualifier, true) === false
            ) {
                return $startPtr ?? $rootPtr;
            }

            $startPtr = $previousPtr;
            $cursorPtr = $previousPtr;
        }
    }

    private function replaceWithDataGet(
        File $phpcsFile,
        int $rootPtr,
        int $endPtr,
        string $variable,
        array $segments
    ): void {
        $replacement = sprintf('data_get(%s, %s)', $variable, $this->renderPath($segments));

        $phpcsFile->fixer
            ->beginChangeset();
        $phpcsFile->fixer
            ->replaceToken($rootPtr, $replacement);

        for ($ptr = ($rootPtr + 1); $ptr <= $endPtr; $ptr++) {
            $phpcsFile->fixer
                ->replaceToken($ptr, '');
        }

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function renderPath(array $segments): string
    {
        $names = array_column($segments, 'name');

        if (in_array(null, $names, true) === false) {
            return "'" . implode('.', $names) . "'";
        }

        return '[' . implode(', ', array_column($segments, 'source')) . ']';
    }

    private function readPath(File $phpcsFile, int $accessorPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $segments = [];
        $endPtr = null;
        $cursorPtr = $accessorPtr;

        while (true) {
            if ($cursorPtr === false) {
                break;
            }

            $code = $tokens[$cursorPtr]['code'];

            if ($code === T_OPEN_SQUARE_BRACKET) {
                if (isset($tokens[$cursorPtr]['bracket_closer']) === false) {
                    return null;
                }

                $closerPtr = $tokens[$cursorPtr]['bracket_closer'];
                $segment = $this->segmentFromSpan($phpcsFile, ($cursorPtr + 1), ($closerPtr - 1));

                if ($segment === null) {
                    return null;
                }

                $segments[] = $segment;
                $endPtr = $closerPtr;
                $cursorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($closerPtr + 1), null, true);

                continue;
            }

            if (in_array($code, self::OBJECT_OPERATORS, true) === false) {
                break;
            }

            $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($cursorPtr + 1), null, true);

            if ($memberPtr === false) {
                break;
            }

            if ($tokens[$memberPtr]['code'] === T_OPEN_CURLY_BRACKET) {
                if (isset($tokens[$memberPtr]['bracket_closer']) === false) {
                    return null;
                }

                $closerPtr = $tokens[$memberPtr]['bracket_closer'];
                $segment = $this->segmentFromSpan($phpcsFile, ($memberPtr + 1), ($closerPtr - 1));

                if ($segment === null) {
                    return null;
                }

                $segments[] = $segment;
                $endPtr = $closerPtr;
                $cursorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($closerPtr + 1), null, true);

                continue;
            }

            $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

            if (
                $afterMemberPtr !== false
                && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS
            ) {
                break;
            }

            $content = $tokens[$memberPtr]['content'];

            $segments[] = $tokens[$memberPtr]['code'] === T_VARIABLE
                ? ['source' => $content, 'name' => null]
                : [
                    'source' => "'{$content}'",
                    'name' => $this->isIdentifierName($content) === true ? $content : null,
                ];
            $endPtr = $memberPtr;
            $cursorPtr = $afterMemberPtr;
        }

        if (
            $segments === []
            || $endPtr === null
        ) {
            return null;
        }

        return ['segments' => $segments, 'end' => $endPtr];
    }

    private function segmentFromSpan(File $phpcsFile, int $startPtr, int $endPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if ($endPtr < $startPtr) {
            return null;
        }

        $source = trim($phpcsFile->getTokensAsString($startPtr, (($endPtr - $startPtr) + 1)));

        if ($source === '') {
            return null;
        }

        $firstPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $startPtr, ($endPtr + 1), true);
        $isLoneString = $firstPtr !== false
            && $tokens[$firstPtr]['code'] === T_CONSTANT_ENCAPSED_STRING
            && $phpcsFile->findNext(Tokens::$emptyTokens, ($firstPtr + 1), ($endPtr + 1), true) === false;

        $name = null;

        if ($isLoneString === true) {
            $literal = substr($tokens[$firstPtr]['content'], 1, -1);
            $name = $this->isIdentifierName($literal) === true ? $literal : null;
        }

        return ['source' => $source, 'name' => $name];
    }

    private function isIdentifierName(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
    }

    private function isByReferenceArgument(File $phpcsFile, int $rootPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$rootPtr]['nested_parenthesis']) === false) {
            return false;
        }

        foreach (array_keys($tokens[$rootPtr]['nested_parenthesis']) as $openerPtr) {
            $name = $this->calledFunctionName($phpcsFile, (int) $openerPtr);

            if ($name === null) {
                continue;
            }

            $positions = $this->byReferenceParameters($name);

            if ($positions === []) {
                continue;
            }

            $position = $this->argumentPosition($phpcsFile, (int) $openerPtr, $rootPtr);

            if (in_array($position, $positions, true) === true) {
                return true;
            }
        }

        return false;
    }

    private function calledFunctionName(File $phpcsFile, int $openerPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

        if (
            $namePtr === false
            || $tokens[$namePtr]['code'] !== T_STRING
        ) {
            return null;
        }

        $beforePtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($namePtr - 1), null, true);

        if ($beforePtr === false) {
            return $tokens[$namePtr]['content'];
        }

        $disqualifying = [T_FUNCTION, T_NEW, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];

        if (in_array($tokens[$beforePtr]['code'], $disqualifying, true) === true) {
            return null;
        }

        return $tokens[$namePtr]['content'];
    }

    private function byReferenceParameters(string $name): array
    {
        if (isset($this->byReferenceCache[$name]) === true) {
            return $this->byReferenceCache[$name];
        }

        $positions = [];

        if (function_exists($name) === true) {
            $reflection = new ReflectionFunction($name);

            if ($reflection->isInternal() === true) {
                foreach ($reflection->getParameters() as $index => $parameter) {
                    if ($parameter->isPassedByReference() === false) {
                        continue;
                    }

                    if ($parameter->isVariadic() === true) {
                        $positions = array_merge($positions, range($index, ($index + self::VARIADIC_REACH)));

                        continue;
                    }

                    $positions[] = $index;
                }
            }
        }

        $this->byReferenceCache[$name] = $positions;

        return $positions;
    }

    private function argumentPosition(File $phpcsFile, int $openerPtr, int $rootPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $position = 0;
        $ptr = ($openerPtr + 1);

        while ($ptr < $rootPtr) {
            $closerPtr = $this->pairCloser($tokens, $ptr);

            if ($closerPtr !== null) {
                $ptr = ($closerPtr + 1);

                continue;
            }

            if ($tokens[$ptr]['code'] === T_COMMA) {
                $position++;
            }

            $ptr++;
        }

        return $position;
    }

    private function pairCloser(array $tokens, int $ptr): ?int
    {
        $keys = [
            T_OPEN_PARENTHESIS => 'parenthesis_closer',
            T_OPEN_SQUARE_BRACKET => 'bracket_closer',
            T_OPEN_SHORT_ARRAY => 'bracket_closer',
            T_OPEN_CURLY_BRACKET => 'bracket_closer',
        ];

        $key = $keys[$tokens[$ptr]['code']] ?? null;

        if ($key === null) {
            return null;
        }

        $closerPtr = $tokens[$ptr][$key] ?? null;

        return is_int($closerPtr) === true ? $closerPtr : null;
    }

    private function chainRoot(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $rootPtr = $stackPtr;

        while (true) {
            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($rootPtr - 1), null, true);

            if (
                $previousPtr === false
                || $tokens[$previousPtr]['code'] !== T_DOLLAR
            ) {
                return $rootPtr;
            }

            $rootPtr = $previousPtr;
        }
    }

    private function chainRootName(File $phpcsFile, int $rootPtr, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($ptr = $rootPtr; $ptr <= $stackPtr; $ptr++) {
            if (isset(Tokens::$emptyTokens[$tokens[$ptr]['code']]) === false) {
                $name .= $tokens[$ptr]['content'];
            }
        }

        return $name;
    }

    private function isChainMember(File $phpcsFile, int $rootPtr): bool
    {
        return $this->followsObjectOperator($phpcsFile, $rootPtr);
    }

    private function followsObjectOperator(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previousPtr === false) {
            return false;
        }

        return in_array($tokens[$previousPtr]['code'], self::OBJECT_OPERATORS, true);
    }

    private function readAccessCode(File $phpcsFile, int $accessorPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$accessorPtr]['code'];

        if ($code === T_OPEN_SQUARE_BRACKET) {
            return 'DirectArrayAccess';
        }

        if (in_array($code, self::OBJECT_OPERATORS, true) === false) {
            return null;
        }

        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($accessorPtr + 1), null, true);

        if ($memberPtr === false) {
            return 'DirectPropertyAccess';
        }

        $memberEndPtr = $memberPtr;

        if ($tokens[$memberPtr]['code'] === T_OPEN_CURLY_BRACKET) {
            if (isset($tokens[$memberPtr]['bracket_closer']) === false) {
                return 'DirectPropertyAccess';
            }

            $memberEndPtr = $tokens[$memberPtr]['bracket_closer'];
        }

        $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberEndPtr + 1), null, true);

        if (
            $afterMemberPtr !== false
            && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        return 'DirectPropertyAccess';
    }

    private function isWriteTarget(File $phpcsFile, int $rootPtr, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($rootPtr - 1), null, true);

        if ($previousPtr !== false) {
            if (in_array($tokens[$previousPtr]['code'], [T_INC, T_DEC], true) === true) {
                return true;
            }

            if (
                $tokens[$previousPtr]['code'] === T_BITWISE_AND
                && $phpcsFile->isReference($previousPtr) === true
            ) {
                return true;
            }
        }

        $endPtr = $this->findChainEnd($phpcsFile, $stackPtr);
        $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true);

        if ($nextPtr !== false) {
            $code = $tokens[$nextPtr]['code'];

            if (in_array($code, [T_INC, T_DEC], true) === true) {
                return true;
            }

            if (
                $code !== T_DOUBLE_ARROW
                && isset(Tokens::$assignmentTokens[$code]) === true
            ) {
                return true;
            }
        }

        return $this->enclosureVerdict($phpcsFile, $rootPtr) === self::VERDICT_TARGET;
    }

    private function enclosureVerdict(File $phpcsFile, int $rootPtr): ?string
    {
        $this->buildEnclosureMap($phpcsFile);
        $this->cacheCounts['enclosureVerdict.walks']++;

        $searchPtr = $rootPtr;
        $closerPtr = $this->innermostCloser[$rootPtr] ?? null;

        while ($closerPtr !== null) {
            $this->cacheCounts['enclosureVerdict.steps']++;

            if (($this->lastUnterminatedOpener[$closerPtr] ?? -1) > $searchPtr) {
                return null;
            }

            $verdict = $this->classifyEnclosure($phpcsFile, $rootPtr, $closerPtr);

            if ($verdict !== null) {
                return $verdict;
            }

            $steppedPtr = $this->decidingStep($phpcsFile, $closerPtr);

            if ($steppedPtr === null) {
                return null;
            }

            $step = $this->outwardSteps[$steppedPtr];

            if ($step === self::STEP_UNDECIDABLE) {
                return null;
            }

            if ($step !== self::STEP_ROOT_DEPENDENT) {
                return $step;
            }

            $searchPtr = $steppedPtr;
            $closerPtr = $this->parentCloser[$steppedPtr];
        }

        return null;
    }

    private function decidingStep(File $phpcsFile, int $closerPtr): ?int
    {
        $walkedPtrs = [];
        $ptr = $closerPtr;
        $steppedPtr = null;

        while (true) {
            if (array_key_exists($ptr, $this->decidingSteps) === true) {
                $this->cacheCounts['decidingStep.hits']++;
                $steppedPtr = $this->decidingSteps[$ptr];

                break;
            }

            $this->cacheCounts['decidingStep.hops']++;
            $walkedPtrs[] = $ptr;
            $parentPtr = $this->parentCloser[$ptr] ?? null;

            if ($parentPtr === null) {
                break;
            }

            if ($this->outwardStep($phpcsFile, $ptr, $parentPtr) !== self::STEP_TRANSPARENT) {
                $steppedPtr = $ptr;

                break;
            }

            $ptr = $parentPtr;
        }

        foreach ($walkedPtrs as $walkedPtr) {
            $this->decidingSteps[$walkedPtr] = $steppedPtr;
        }

        return $steppedPtr;
    }

    private function outwardStep(File $phpcsFile, int $closerPtr, int $parentPtr): string
    {
        return $this->outwardSteps[$closerPtr]
            ??= $this->classifyOutwardStep($phpcsFile, $closerPtr, $parentPtr);
    }

    private function classifyOutwardStep(File $phpcsFile, int $closerPtr, int $parentPtr): string
    {
        $tokens = $phpcsFile->getTokens();

        if (($this->lastUnterminatedOpener[$parentPtr] ?? -1) > $closerPtr) {
            return self::STEP_UNDECIDABLE;
        }

        $code = $tokens[$parentPtr]['code'];

        if ($code === T_CLOSE_SQUARE_BRACKET) {
            return self::VERDICT_OFFSET;
        }

        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $this->isDynamicMemberBrace($phpcsFile, $tokens[$parentPtr]['bracket_opener']) === true
                ? self::VERDICT_OFFSET
                : self::STEP_TRANSPARENT;
        }

        $asPtr = $this->foreachClauseAs($phpcsFile, $parentPtr);

        if ($asPtr !== null) {
            $openerPtr = $tokens[$closerPtr]['bracket_opener'] ?? $tokens[$closerPtr]['parenthesis_opener'];

            if ($asPtr < $openerPtr) {
                return self::VERDICT_TARGET;
            }

            return $asPtr > $closerPtr ? self::STEP_TRANSPARENT : self::STEP_ROOT_DEPENDENT;
        }

        return $this->isAssignedPattern($phpcsFile, $parentPtr) === true
            ? self::VERDICT_TARGET
            : self::STEP_TRANSPARENT;
    }

    private function classifyEnclosure(File $phpcsFile, int $rootPtr, int $closerPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$closerPtr]['code'];

        if ($code === T_CLOSE_SQUARE_BRACKET) {
            return self::VERDICT_OFFSET;
        }

        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $this->isDynamicMemberBrace($phpcsFile, $tokens[$closerPtr]['bracket_opener']) === true
                ? self::VERDICT_OFFSET
                : null;
        }

        if ($this->isForeachTargetClause($phpcsFile, $rootPtr, $closerPtr) === true) {
            return self::VERDICT_TARGET;
        }

        if ($this->isAssignedPattern($phpcsFile, $closerPtr) === true) {
            return self::VERDICT_TARGET;
        }

        return null;
    }

    private function isForeachTargetClause(File $phpcsFile, int $rootPtr, int $closerPtr): bool
    {
        $asPtr = $this->foreachClauseAs($phpcsFile, $closerPtr);

        return $asPtr !== null && $asPtr < $rootPtr;
    }

    private function foreachClauseAs(File $phpcsFile, int $closerPtr): ?int
    {
        if (array_key_exists($closerPtr, $this->foreachClauseAsPtrs) === true) {
            return $this->foreachClauseAsPtrs[$closerPtr];
        }

        $tokens = $phpcsFile->getTokens();
        $ownerPtr = $tokens[$closerPtr]['parenthesis_owner'] ?? null;
        $asPtr = null;

        if (
            $ownerPtr !== null
            && $tokens[$ownerPtr]['code'] === T_FOREACH
        ) {
            $openerPtr = $tokens[$closerPtr]['parenthesis_opener'];
            $searchPtr = ($openerPtr + 1);

            while (($foundPtr = $phpcsFile->findNext(T_AS, $searchPtr, $closerPtr)) !== false) {
                $nestedPtrs = $tokens[$foundPtr]['nested_parenthesis'] ?? [];

                if (array_key_last($nestedPtrs) === $openerPtr) {
                    $asPtr = $foundPtr;

                    break;
                }

                $searchPtr = ($foundPtr + 1);
            }
        }

        $this->foreachClauseAsPtrs[$closerPtr] = $asPtr;

        return $asPtr;
    }

    private function isAssignedPattern(File $phpcsFile, int $closerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($this->isPatternCloser($phpcsFile, $closerPtr) === false) {
            return false;
        }

        $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($closerPtr + 1), null, true);

        return $nextPtr !== false && $tokens[$nextPtr]['code'] === T_EQUAL;
    }

    private function isDynamicMemberBrace(File $phpcsFile, int $openerPtr): bool
    {
        return $this->followsObjectOperator($phpcsFile, $openerPtr);
    }

    private function isPatternCloser(File $phpcsFile, int $closerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$closerPtr]['code'];

        if ($code === T_CLOSE_SHORT_ARRAY) {
            return true;
        }

        if ($code !== T_CLOSE_PARENTHESIS) {
            return false;
        }

        $ownerPtr = $tokens[$closerPtr]['parenthesis_owner'] ?? null;

        return $ownerPtr !== null && $tokens[$ownerPtr]['code'] === T_LIST;
    }

    private function buildEnclosureMap(File $phpcsFile): void
    {
        $tokenStreams = $this->tokenStreams;

        $tokens = $phpcsFile->getTokens();
        $key = $tokenStreams->key($phpcsFile);

        if ($this->enclosureMapKey === $key) {
            $this->cacheCounts['enclosureMap.hits']++;

            return;
        }

        $this->cacheCounts['enclosureMap.builds']++;
        $this->enclosureMapKey = $key;
        $this->innermostCloser = [];
        $this->parentCloser = [];
        $this->lastUnterminatedOpener = [];
        $this->outwardSteps = [];
        $this->decidingSteps = [];
        $this->foreachClauseAsPtrs = [];
        $this->existenceCheckOpeners = [];

        $openCloserPtrs = [null];
        $innermostPtr = null;

        foreach ($tokens as $ptr => $token) {
            if ($innermostPtr === $ptr) {
                array_pop($openCloserPtrs);
                $innermostPtr = $openCloserPtrs[array_key_last($openCloserPtrs)];
            }

            $code = $token['code'];

            if (in_array($code, self::CHAIN_ROOT_TOKENS, true) === true) {
                if ($innermostPtr !== null) {
                    $this->innermostCloser[$ptr] = $innermostPtr;
                }

                continue;
            }

            if (in_array($code, self::ENCLOSING_OPENERS, true) === false) {
                continue;
            }

            $closerPtr = $token['bracket_closer'] ?? $token['parenthesis_closer'] ?? null;

            if ($closerPtr === null) {
                if ($innermostPtr !== null) {
                    $this->lastUnterminatedOpener[$innermostPtr] = $ptr;
                }

                continue;
            }

            $this->parentCloser[$closerPtr] = $innermostPtr;
            $openCloserPtrs[] = $closerPtr;
            $innermostPtr = $closerPtr;
        }
    }

    private function findChainEnd(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $endPtr = $stackPtr;

        while (true) {
            $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true);

            if ($nextPtr === false) {
                return $endPtr;
            }

            $code = $tokens[$nextPtr]['code'];

            if (
                $code === T_OPEN_SQUARE_BRACKET
                || $code === T_OPEN_PARENTHESIS
            ) {
                $closer = $code === T_OPEN_SQUARE_BRACKET ? 'bracket_closer' : 'parenthesis_closer';

                if (isset($tokens[$nextPtr][$closer]) === false) {
                    return $nextPtr;
                }

                $endPtr = $tokens[$nextPtr][$closer];

                continue;
            }

            if (in_array($code, self::OBJECT_OPERATORS, true) === false) {
                return $endPtr;
            }

            $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($nextPtr + 1), null, true);

            if ($memberPtr === false) {
                return $endPtr;
            }

            if ($tokens[$memberPtr]['code'] === T_OPEN_CURLY_BRACKET) {
                if (isset($tokens[$memberPtr]['bracket_closer']) === false) {
                    return $memberPtr;
                }

                $endPtr = $tokens[$memberPtr]['bracket_closer'];

                continue;
            }

            $endPtr = $memberPtr;
        }
    }

    private function isInsideExistenceCheck(File $phpcsFile, int $rootPtr): bool
    {
        $this->buildEnclosureMap($phpcsFile);

        $tokens = $phpcsFile->getTokens();
        $enclosingPtrs = $tokens[$rootPtr]['nested_parenthesis'] ?? [];

        if ($enclosingPtrs === []) {
            return false;
        }

        return $this->isEnclosedByExistenceCheck($phpcsFile, array_key_last($enclosingPtrs));
    }

    private function isEnclosedByExistenceCheck(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $walkedPtrs = [];
        $ptr = $openerPtr;
        $enclosed = false;

        while (true) {
            if (array_key_exists($ptr, $this->existenceCheckOpeners) === true) {
                $enclosed = $this->existenceCheckOpeners[$ptr];

                break;
            }

            $walkedPtrs[] = $ptr;

            if ($this->isExistenceCheckOpener($phpcsFile, $ptr) === true) {
                $enclosed = true;

                break;
            }

            $enclosingPtrs = $tokens[$ptr]['nested_parenthesis'] ?? [];

            if ($enclosingPtrs === []) {
                break;
            }

            $ptr = array_key_last($enclosingPtrs);
        }

        foreach ($walkedPtrs as $walkedPtr) {
            $this->existenceCheckOpeners[$walkedPtr] = $enclosed;
        }

        return $enclosed;
    }

    private function isExistenceCheckOpener(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ownerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

        if ($ownerPtr === false) {
            return false;
        }

        return isset(self::EXISTENCE_CHECKS[strtolower($tokens[$ownerPtr]['content'])]) === true;
    }
}
