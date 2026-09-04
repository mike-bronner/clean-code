<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\DeadCode;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class UnusedFormalParameterSniff implements Sniff
{
    private const FIXED_SIGNATURE_METHODS = [
        '__call',
        '__callstatic',
        '__get',
        '__isset',
        '__set',
        '__set_state',
        '__unset',
    ];

    private const CLASS_LIKE = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    private const INTERPOLATING_TEXT = [
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    private const INTERPOLATION_PATTERN =
        '/(?<!\\\\)(?:\\\\\\\\)*\K\$\{?(?P<name>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/';

    private ?string $declarationsKey = null;

    private array $declarations = [];

    private array $declarationNamespace = [];

    private array $methodNames = [];

    private array $traitNames = [];

    private array $cacheCounts = [
        'declarations.builds' => 0,
        'declarations.hits' => 0,
        'methodNames.builds' => 0,
        'methodNames.hits' => 0,
        'traitNames.builds' => 0,
        'traitNames.hits' => 0,
    ];

    private array $cacheCountsByClass = [
        'methodNames' => [],
        'traitNames' => [],
    ];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls(),
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function register(): array
    {
        return [T_CLOSURE, T_FN, T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $start = $tokens[$stackPtr]['scope_opener'] + 1;
        $end = $tokens[$stackPtr]['scope_closer'] - 1;

        if ($this->isExempt($phpcsFile, $stackPtr, $start, $end) === true) {
            return;
        }

        $reads = $this->namesRead($phpcsFile, $start, $end);

        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            $this->checkParameter($phpcsFile, $stackPtr, $parameter, $reads);
        }
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function cacheCountsByClass(): array
    {
        return $this->cacheCountsByClass;
    }

    private function isExempt(File $phpcsFile, int $stackPtr, int $start, int $end): bool
    {
        if ($this->hasFixedSignature($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->hasInheritanceAnnotation($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->overridesSameFileMethod($phpcsFile, $stackPtr) === true) {
            return true;
        }

        return $this->callsFuncGetArgs($phpcsFile, $start, $end);
    }

    private function checkParameter(
        File $phpcsFile,
        int $stackPtr,
        array $parameter,
        array $reads
    ): void {
        if ($this->isPromotedProperty($parameter) === true) {
            return;
        }

        $name = ltrim($parameter['name'], '$');

        if (isset($reads[$name]) === true) {
            return;
        }

        $phpcsFile->addError(
            'The %s never reads its parameter %s; remove it from the signature, or mark the '
                . 'method as an override with #[\\Override] or @inheritdoc if the signature is '
                . 'imposed from outside (see docs/phpmd/unusedcode-unusedformalparameter.md)',
            $parameter['token'],
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $parameter['name']]
        );
    }

    private function isPromotedProperty(array $parameter): bool
    {
        if (isset($parameter['property_visibility']) === true) {
            return true;
        }

        return ($parameter['property_readonly'] ?? false) === true;
    }

    private function namesRead(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_VARIABLE) {
                $names[ltrim($tokens[$pointer]['content'], '$')] = true;

                continue;
            }

            if ($code === T_STRING_VARNAME) {
                $names[$tokens[$pointer]['content']] = true;

                continue;
            }

            if (in_array($code, self::INTERPOLATING_TEXT, true) === true) {
                $names += $this->interpolatedNames($tokens[$pointer]['content']);

                continue;
            }

            if ($this->isCallTo($phpcsFile, $pointer, 'compact') === true) {
                $names += $this->compactedNames($phpcsFile, $pointer);
            }
        }

        return $names;
    }

    private function interpolatedNames(string $text): array
    {
        $matched = preg_match_all(self::INTERPOLATION_PATTERN, $text, $matches);

        // This read can genuinely fail: INTERPOLATION_PATTERN's leading
        // `(?:\\\\)*` is a quantified group, so a long enough run of
        // backslashes in the string exhausts a PCRE limit. A runtime failure
        // leaves $matches with an empty `name` key; a compile failure leaves it
        // unwritten, and array_fill_keys() is then handed an offset read off
        // null against a parameter it declared as an array. The empty list is
        // the exit for it: names this read did not collect are names the
        // parameter is not proven to use, so the failure reports a parameter
        // that may be used rather than crashing the sniff on the file.
        if ($matched === false) {
            return [];
        }

        return array_fill_keys($matches['name'], true);
    }

    private function isCallTo(File $phpcsFile, int $pointer, string $name): bool
    {
        $functionCalls = $this->functionCalls;

        if (strtolower($phpcsFile->getTokens()[$pointer]['content']) !== $name) {
            return false;
        }

        if ($functionCalls->isGlobalFunctionCall($phpcsFile, $pointer) === false) {
            return false;
        }

        return $this->isFirstClassCallable($phpcsFile, $pointer) === false;
    }

    private function isFirstClassCallable(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($opener === false) {
            return false;
        }

        $argument = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, null, true);

        if (
            $argument === false
            || $tokens[$argument]['code'] !== T_ELLIPSIS
        ) {
            return false;
        }

        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $argument + 1, null, true);

        return $after !== false && $tokens[$after]['code'] === T_CLOSE_PARENTHESIS;
    }

    private function compactedNames(File $phpcsFile, int $pointer): array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = (int) $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);
        $closer = $tokens[$opener]['parenthesis_closer'] ?? null;
        $names = [];

        if ($closer === null) {
            return $names;
        }

        for ($argument = $opener + 1; $argument < $closer; $argument++) {
            if ($tokens[$argument]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                $names[trim($tokens[$argument]['content'], '\'"')] = true;
            }
        }

        return $names;
    }

    private function callsFuncGetArgs(File $phpcsFile, int $start, int $end): bool
    {
        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if ($this->isCallTo($phpcsFile, $pointer, 'func_get_args') === true) {
                return true;
            }
        }

        return false;
    }

    private function hasFixedSignature(File $phpcsFile, int $stackPtr): bool
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] !== T_FUNCTION) {
            return false;
        }

        if ($this->enclosingClass($phpcsFile, $stackPtr) === null) {
            return false;
        }

        $name = strtolower((string) $phpcsFile->getDeclarationName($stackPtr));

        return in_array($name, self::FIXED_SIGNATURE_METHODS, true);
    }

    private function hasInheritanceAnnotation(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $skippable = Tokens::$methodPrefixes + Tokens::$emptyTokens;
        $undocumented = Tokens::$phpcsCommentTokens + [T_COMMENT => T_COMMENT];
        $docText = '';
        $pointer = $stackPtr - 1;

        while ($pointer >= 0) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ATTRIBUTE_END) {
                $opener = $tokens[$pointer]['attribute_opener'] ?? $pointer;

                if ($this->isOverrideAttribute($phpcsFile, $opener, $pointer) === true) {
                    return true;
                }

                $pointer = $opener - 1;

                continue;
            }

            if (isset($skippable[$code]) === false) {
                break;
            }

            if (isset($undocumented[$code]) === false) {
                $docText = $tokens[$pointer]['content'] . $docText;
            }

            $pointer--;
        }

        return preg_match('/\{?@inheritdoc\b/i', $docText) === 1;
    }

    private function isOverrideAttribute(File $phpcsFile, int $opener, int $closer): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($pointer = $opener + 1; $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] === T_OPEN_PARENTHESIS) {
                $pointer = $tokens[$pointer]['parenthesis_closer'] ?? $closer;

                continue;
            }

            if (
                $tokens[$pointer]['code'] !== T_STRING
                || strtolower($tokens[$pointer]['content']) !== 'override'
            ) {
                continue;
            }

            if ($this->isAttributeName($phpcsFile, $pointer) === true) {
                return true;
            }
        }

        return false;
    }

    private function isAttributeName(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);

        if (
            $previous !== false
            && $tokens[$previous]['code'] === T_NS_SEPARATOR
        ) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);
        }

        return $previous !== false
            && in_array($tokens[$previous]['code'], [T_ATTRIBUTE, T_COMMA], true) === true;
    }

    private function overridesSameFileMethod(File $phpcsFile, int $stackPtr): bool
    {
        $classPtr = $this->enclosingClass($phpcsFile, $stackPtr);

        if ($classPtr === null) {
            return false;
        }

        $name = strtolower((string) $phpcsFile->getDeclarationName($stackPtr));
        $declarations = $this->declarationsByName($phpcsFile);
        $seen = [];
        $queue = $this->inheritedNames($phpcsFile, $classPtr);

        while ($queue !== []) {
            $ancestor = array_shift($queue);

            if (
                isset($seen[$ancestor]) === true
                || isset($declarations[$ancestor]) === false
            ) {
                continue;
            }

            $seen[$ancestor] = true;
            $pointer = $declarations[$ancestor];

            if (in_array($name, $this->methodNames($phpcsFile, $pointer), true) === true) {
                return true;
            }

            $queue = array_merge(
                $queue,
                $this->inheritedNames($phpcsFile, $pointer),
                $this->traitNames($phpcsFile, $pointer)
            );
        }

        return false;
    }

    private function inheritedNames(File $phpcsFile, int $classPtr): array
    {
        return $this->qualifiedNames(
            $phpcsFile,
            $classPtr,
            $this->declaredAncestorNames($phpcsFile, $classPtr)
        );
    }

    private function declaredAncestorNames(File $phpcsFile, int $classPtr): array
    {
        $opener = $phpcsFile->getTokens()[$classPtr]['scope_opener'] ?? null;
        $clause = $opener === null
            ? false
            : $phpcsFile->findNext([T_EXTENDS, T_IMPLEMENTS], $classPtr + 1, $opener);

        if ($clause === false) {
            return [];
        }

        return $this->segmentNames($phpcsFile, $clause, $opener, [T_COMMA, T_EXTENDS, T_IMPLEMENTS]);
    }

    private function traitNames(File $phpcsFile, int $classPtr): array
    {
        $this->buildDeclarations($phpcsFile);

        if (isset($this->traitNames[$classPtr]) === true) {
            $this->countCacheRead('traitNames', $classPtr, 'hits');

            return $this->traitNames[$classPtr];
        }

        $this->countCacheRead('traitNames', $classPtr, 'builds');

        $this->traitNames[$classPtr] = $this->qualifiedNames(
            $phpcsFile,
            $classPtr,
            $this->usedTraitNames($phpcsFile, $classPtr)
        );

        return $this->traitNames[$classPtr];
    }

    private function qualifiedNames(File $phpcsFile, int $classPtr, array $names): array
    {
        $this->buildDeclarations($phpcsFile);
        $namespace = $this->declarationNamespace[$classPtr] ?? '';

        return array_map(
            static fn (string $name): string => $namespace . '\\'
                . strtolower(substr((string) strrchr("\\{$name}", '\\'), 1)),
            $names
        );
    }

    private function usedTraitNames(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'] ?? null;
        $closer = $tokens[$classPtr]['scope_closer'] ?? null;
        $names = [];

        if (
            $opener === null
            || $closer === null
        ) {
            return $names;
        }

        $pointer = $phpcsFile->findNext(T_USE, $opener + 1, $closer);

        while ($pointer !== false) {
            $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $pointer + 1, $closer);

            if (
                $end !== false
                && $this->enclosingClass($phpcsFile, $pointer) === $classPtr
            ) {
                $names = array_merge(
                    $names,
                    $this->segmentNames($phpcsFile, $pointer + 1, $end, [T_COMMA])
                );
            }

            $pointer = $phpcsFile->findNext(T_USE, ($end === false ? $pointer : $end) + 1, $closer);
        }

        return $names;
    }

    private function segmentNames(File $phpcsFile, int $start, int $end, array $separators): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];
        $segment = null;

        for ($pointer = $start; $pointer < $end; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_STRING) {
                $segment = $tokens[$pointer]['content'];

                continue;
            }

            if (
                $segment !== null
                && in_array($code, $separators, true) === true
            ) {
                $names[] = $segment;
                $segment = null;
            }
        }

        if ($segment !== null) {
            $names[] = $segment;
        }

        return $names;
    }

    private function buildDeclarations(File $phpcsFile): void
    {
        $tokenStreams = $this->tokenStreams;
        $key = $tokenStreams->key($phpcsFile);

        if ($this->declarationsKey === $key) {
            $this->cacheCounts['declarations.hits']++;

            return;
        }

        $this->cacheCounts['declarations.builds']++;
        $this->declarationsKey = $key;
        $this->declarations = [];
        $this->declarationNamespace = [];
        $this->methodNames = [];
        $this->traitNames = [];
        $this->cacheCountsByClass = ['methodNames' => [], 'traitNames' => []];

        $targets = array_merge([T_NAMESPACE], self::CLASS_LIKE);
        $namespace = '';
        $pointer = $phpcsFile->findNext($targets, 0);

        while ($pointer !== false) {
            $namespace = $this->indexDeclaration($phpcsFile, $pointer, $namespace);
            $pointer = $phpcsFile->findNext($targets, $pointer + 1);
        }
    }

    // Records one declaration against the namespace in force, and answers the
    // namespace the next declaration sits in — unchanged, except where this
    // pointer was itself a namespace statement.
    private function indexDeclaration(File $phpcsFile, int $pointer, string $namespace): string
    {
        if ($phpcsFile->getTokens()[$pointer]['code'] === T_NAMESPACE) {
            return $this->namespaceName($phpcsFile, $pointer) ?? $namespace;
        }

        $name = $phpcsFile->getDeclarationName($pointer);
        $this->declarationNamespace[$pointer] = $namespace;

        if (
            $name !== null
            && $name !== ''
        ) {
            $this->declarations[$namespace . '\\' . strtolower($name)] = $pointer;
        }

        return $namespace;
    }

    private function countCacheRead(string $index, int $classPtr, string $outcome): void
    {
        $this->cacheCounts["{$index}.{$outcome}"]++;

        $counts = $this->cacheCountsByClass[$index][$classPtr] ?? ['builds' => 0, 'hits' => 0];
        $counts[$outcome]++;
        $this->cacheCountsByClass[$index][$classPtr] = $counts;
    }

    private function namespaceName(File $phpcsFile, int $pointer): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        if (
            $next === false
            || $tokens[$next]['code'] === T_NS_SEPARATOR
        ) {
            return null;
        }

        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $next);
        $name = '';

        for (
            $segment = $next; $end !== false
            && $segment < $end; $segment++
        ) {
            if (in_array($tokens[$segment]['code'], [T_NS_SEPARATOR, T_STRING], true) === true) {
                $name .= $tokens[$segment]['content'];
            }
        }

        return strtolower($name);
    }

    private function declarationsByName(File $phpcsFile): array
    {
        $this->buildDeclarations($phpcsFile);

        return $this->declarations;
    }

    private function methodNames(File $phpcsFile, int $classPtr): array
    {
        $this->buildDeclarations($phpcsFile);

        if (isset($this->methodNames[$classPtr]) === true) {
            $this->countCacheRead('methodNames', $classPtr, 'hits');

            return $this->methodNames[$classPtr];
        }

        $this->countCacheRead('methodNames', $classPtr, 'builds');
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'] ?? null;
        $closer = $tokens[$classPtr]['scope_closer'] ?? null;
        $names = [];

        if (
            $opener === null
            || $closer === null
        ) {
            $this->methodNames[$classPtr] = $names;

            return $names;
        }

        $pointer = $phpcsFile->findNext(T_FUNCTION, $opener + 1, $closer);

        while ($pointer !== false) {
            $name = $phpcsFile->getDeclarationName($pointer);

            if (
                $name !== null
                && $this->enclosingClass($phpcsFile, $pointer) === $classPtr
            ) {
                $names[] = strtolower($name);
            }

            $pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $closer);
        }

        $this->methodNames[$classPtr] = $names;

        return $names;
    }

    private function enclosingClass(File $phpcsFile, int $stackPtr): ?int
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $pointer => $code) {
            if (in_array($code, self::CLASS_LIKE, true) === true) {
                return $pointer;
            }

            if (in_array($code, [T_CLOSURE, T_FN, T_FUNCTION], true) === true) {
                return null;
            }
        }

        return null;
    }

    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        if ($code === T_CLOSURE) {
            return 'closure';
        }

        if ($code === T_FN) {
            return 'arrow function';
        }

        $subject = $this->enclosingClass($phpcsFile, $stackPtr) === null ? 'function' : 'method';

        return "{$subject} {$phpcsFile->getDeclarationName($stackPtr)}()";
    }
}
