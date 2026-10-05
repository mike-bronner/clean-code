<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use MikeBronner\CleanCode\Helpers\Declarations;
use MikeBronner\CleanCode\Helpers\PathPatterns;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoFirstPartyMocksSniff implements Sniff
{
    private const NAME_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    private const SCOPE_KEYWORDS = [
        T_SELF,
        T_STATIC,
        T_PARENT,
    ];

    private const CLASS_LIKE_SCOPES = [
        T_CLASS,
        T_ANON_CLASS,
        T_TRAIT,
        T_INTERFACE,
        T_ENUM,
    ];

    private const HEADER_FILLER = [
        T_OPEN_TAG,
        T_SEMICOLON,
    ];

    private const HEADER_STATEMENTS = [
        T_DECLARE,
        T_NAMESPACE,
        T_USE,
    ];

    public array $testFilePatterns = [
        '*/tests/*',
        '*/Tests/*',
        '*Test.php',
    ];

    public array $firstPartyNamespaces = [];

    public array $mockCreators = [
        'createMock',
        'createPartialMock',
        'getMockBuilder',
        'mock',
        'partialMock',
        'spy',
    ];

    public function __construct(
        private PathPatterns $pathPatterns = new PathPatterns
    ) {
    }

    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
        ];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($this->firstPartyNamespaces === []) {
            return;
        }

        $path = $phpcsFile->getFilename();

        if ($this->pathPatterns->matchesAny($path, $this->testFilePatterns) === false) {
            return;
        }

        $argumentPtr = $this->mockArgumentPointer($phpcsFile, $stackPtr);

        if ($argumentPtr === null) {
            return;
        }

        $written = $this->classReference($phpcsFile, $argumentPtr);

        if ($written === null) {
            return;
        }

        $resolved = $this->resolve($phpcsFile, $written);

        if ($this->isFirstParty($resolved) === false) {
            return;
        }

        $phpcsFile->addWarning(
                'Mocking %s, a first-party class; mock only interfaces you do not control'
                    . ' (see resources/boost/guidelines/testing-guidelines.md)',
                $argumentPtr,
                'Found',
                [$resolved]
            );
    }

    private function mockArgumentPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $memberPtr === false
            || $tokens[$memberPtr]['code'] !== T_STRING
        ) {
            return null;
        }

        if ($this->matches($tokens[$memberPtr]['content'], $this->mockCreators) === false) {
            return null;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if (
            $openPtr === false
            || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        $argumentPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        return $argumentPtr === false ? null : $argumentPtr;
    }

    private function classReference(File $phpcsFile, int $argumentPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$argumentPtr]['code'] === T_CONSTANT_ENCAPSED_STRING) {
            $written = '\\' . ltrim($this->literalValue($tokens[$argumentPtr]['content']), '\\');

            return $this->endsTheArgument($phpcsFile, ($argumentPtr + 1)) === true ? $written : null;
        }

        if (in_array($tokens[$argumentPtr]['code'], self::SCOPE_KEYWORDS, true) === true) {
            return $this->scopeReference($phpcsFile, $argumentPtr);
        }

        [$written, $pointer] = $this->nameRun($tokens, $argumentPtr);

        if ($written === '') {
            return null;
        }

        $pointer = $this->skipClassConstant($phpcsFile, $pointer);

        if ($pointer === null) {
            return null;
        }

        return $this->endsTheArgument($phpcsFile, $pointer) === true ? $written : null;
    }

    private function nameRun(array $tokens, int $pointer): array
    {
        $written = '';

        for (; isset($tokens[$pointer]) === true; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return [$written, $pointer];
    }

    private function scopeReference(File $phpcsFile, int $argumentPtr): ?string
    {
        $afterKeyword = ($argumentPtr + 1);
        $pointer = $this->skipClassConstant($phpcsFile, $afterKeyword);

        if (
            $pointer === null
            || $pointer === $afterKeyword
        ) {
            return null;
        }

        if ($this->endsTheArgument($phpcsFile, $pointer) === false) {
            return null;
        }

        $name = $this->scopeClassName($phpcsFile, $argumentPtr);

        return $name === null ? null : "\\{$name}";
    }

    private function scopeClassName(File $phpcsFile, int $keywordPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $this->enclosingClass($tokens, $keywordPtr);

        if ($classPtr === null) {
            return null;
        }

        if ($tokens[$keywordPtr]['code'] === T_PARENT) {
            return $this->parentName($phpcsFile, $classPtr);
        }

        $declared = (new Declarations)->name($phpcsFile, $classPtr);

        if ($declared === null) {
            return null;
        }

        [$namespace] = $this->fileScope($phpcsFile);

        return $this->join($namespace, $declared);
    }

    private function enclosingClass(array $tokens, int $pointer): ?int
    {
        $conditions = array_reverse($tokens[$pointer]['conditions'], true);

        foreach ($conditions as $conditionPtr => $code) {
            if (in_array($code, self::CLASS_LIKE_SCOPES, true) === false) {
                continue;
            }

            return $code === T_CLASS ? $conditionPtr : null;
        }

        return null;
    }

    private function parentName(File $phpcsFile, int $classPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$classPtr]['scope_opener']) === false) {
            return null;
        }

        $openerPtr = $tokens[$classPtr]['scope_opener'];
        $extendsPtr = $phpcsFile->findNext(T_EXTENDS, ($classPtr + 1), $openerPtr);

        if ($extendsPtr === false) {
            return null;
        }

        $namePtr = $phpcsFile->findNext(self::NAME_TOKENS, ($extendsPtr + 1), $openerPtr);

        if ($namePtr === false) {
            return null;
        }

        [$written] = $this->nameRun($tokens, $namePtr);

        return $this->resolve($phpcsFile, $written);
    }

    private function skipClassConstant(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $doubleColonPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if (
            $doubleColonPtr === false
            || $tokens[$doubleColonPtr]['code'] !== T_DOUBLE_COLON
        ) {
            return $pointer;
        }

        $constantPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($doubleColonPtr + 1), null, true);

        if ($constantPtr === false) {
            return null;
        }

        if (strtolower($tokens[$constantPtr]['content']) !== 'class') {
            return null;
        }

        return ($constantPtr + 1);
    }

    private function endsTheArgument(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundaryPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if ($boundaryPtr === false) {
            return false;
        }

        return in_array($tokens[$boundaryPtr]['code'], [T_COMMA, T_CLOSE_PARENTHESIS], true);
    }

    private function literalValue(string $content): string
    {
        return str_replace('\\\\', '\\', trim($content, "'\""));
    }

    private function resolve(File $phpcsFile, string $written): string
    {
        if (str_starts_with($written, '\\') === true) {
            return ltrim($written, '\\');
        }

        [$namespace, $imports] = $this->fileScope($phpcsFile);
        $segments = explode('\\', $written);
        $first = strtolower($segments[0]);

        if ($first === 'namespace') {
            array_shift($segments);

            return $this->join($namespace, implode('\\', $segments));
        }

        if (array_key_exists($first, $imports) === true) {
            $segments[0] = $imports[$first];

            return implode('\\', $segments);
        }

        return $this->join($namespace, $written);
    }

    private function fileScope(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $namespace = '';
        $imports = [];
        $pointer = 0;

        while (isset($tokens[$pointer]) === true) {
            $code = $tokens[$pointer]['code'];
            $isFiller = in_array($code, Tokens::$emptyTokens, true)
                || in_array($code, self::HEADER_FILLER, true);

            if ($isFiller === true) {
                $pointer++;

                continue;
            }

            if (in_array($code, self::HEADER_STATEMENTS, true) === false) {
                break;
            }

            $end = $this->statementEnd($phpcsFile, $pointer);

            if ($code === T_NAMESPACE) {
                $declared = $this->declaredNamespace($tokens, $pointer, $end);

                if ($declared === null) {
                    break;
                }

                $namespace = $declared;
            }

            if ($code === T_USE) {
                $imports = array_merge($imports, $this->importedAliases($tokens, $pointer, $end));
            }

            $pointer = ($end ?? count($tokens));
        }

        return [$namespace, $imports];
    }

    private function statementEnd(File $phpcsFile, int $pointer): ?int
    {
        $terminator = $phpcsFile->findNext(
                [T_SEMICOLON, T_OPEN_CURLY_BRACKET],
                ($pointer + 1)
            );

        return $terminator === false ? null : ($terminator + 1);
    }

    private function declaredNamespace(array $tokens, int $pointer, ?int $end): ?string
    {
        $written = $this->statementText($tokens, $pointer, $end);

        return str_starts_with($written, '\\') === true ? null : trim($written, '\\');
    }

    private function importedAliases(array $tokens, int $pointer, ?int $end): array
    {
        $written = $this->statementText($tokens, $pointer, $end);

        if ($this->importsNoClass($written) === true) {
            return [];
        }

        $prefix = '';

        if (str_contains($written, '{') === true) {
            [$prefix, $written] = explode('{', $written, 2);
            $written = rtrim(explode('}', $written, 2)[0]);
        }

        $prefix = trim($prefix);
        $imports = [];

        foreach (explode(',', $written) as $clause) {
            $clause = trim($clause);

            if (
                $clause === ''
                || $this->importsNoClass($clause) === true
            ) {
                continue;
            }

            $imports = array_merge($imports, $this->importedAlias($prefix . $clause));
        }

        return $imports;
    }

    private function importsNoClass(string $text): bool
    {
        return preg_match('/^(function|const)\s/i', $text) === 1;
    }

    private function importedAlias(string $clause): array
    {
        $parts = preg_split('/\s+as\s+/i', $clause, 2) ?: [$clause];
        $qualified = trim((string) $parts[0], '\\');
        $segments = explode('\\', $qualified);
        $alias = $parts[1] ?? end($segments);

        return [strtolower(trim((string) $alias)) => $qualified];
    }

    private function statementText(array $tokens, int $pointer, ?int $end): string
    {
        $written = '';
        $last = ($end ?? count($tokens)) - 1;

        for ($current = ($pointer + 1); $current < $last; $current++) {
            $isEmpty = in_array($tokens[$current]['code'], Tokens::$emptyTokens, true);
            $written .= ($isEmpty === true ? ' ' : $tokens[$current]['content']);
        }

        return trim(preg_replace('/\s+/', ' ', $written) ?? $written);
    }

    private function join(string $namespace, string $tail): string
    {
        return $namespace === '' ? $tail : ("{$namespace}\\{$tail}");
    }

    private function isFirstParty(string $resolved): bool
    {
        $lowered = strtolower($resolved);

        foreach ($this->firstPartyNamespaces as $prefix) {
            $root = strtolower(trim($prefix, '\\'));

            if ($root === '') {
                continue;
            }

            if (
                $lowered === $root
                || str_starts_with($lowered, "{$root}\\") === true
            ) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $name, array $candidates): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $candidates), true);
    }
}
