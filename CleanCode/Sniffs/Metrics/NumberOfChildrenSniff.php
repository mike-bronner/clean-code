<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Sniffs\Sniff;

// A sniff is one rule, and its class name is the sniff code consumers write
// in their rulesets — so the unit is fixed from outside and splitting the
// class into collaborators would distribute the work without reducing it.
// phpcs:ignore CleanCode.CodeSize.TooManyMethods -- see above
class NumberOfChildrenSniff implements Sniff
{
    private const UNKNOWN_PATH = 'STDIN';

    private const ORDINAL_DIAGNOSTIC = 'cleancode_ordinal_index_diagnostic';

    private const SKIPPED_TOKENS = [
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
    ];

    private const NAME_TOKENS = [
        T_STRING => true,
        T_NS_SEPARATOR => true,
        T_NAME_QUALIFIED => true,
        T_NAME_FULLY_QUALIFIED => true,
        T_NAME_RELATIVE => true,
    ];

    private const CLASS_LIKE_TOKENS = [
        T_CLASS => true,
        T_INTERFACE => true,
        T_TRAIT => true,
        T_ENUM => true,
    ];

    private const INTERPOLATION_OPEN_TOKENS = [
        T_CURLY_OPEN => true,
        T_DOLLAR_OPEN_CURLY_BRACES => true,
    ];

    public $minimum = 15;

    private array $childCounts = [];

    private array $declarations = [];

    private array $edges = [];

    private array $digests = [];

    private ?string $scannedRun = null;

    private ?string $refreshedStream = null;

    private ?string $ordinalsKey = null;

    private array $ordinals = [];

    private array $ordinalCounts = [
        'builds' => 0,
        'reads' => 0,
    ];

    public function register(): array
    {
        // T_CLASS alone. PHP_CodeSniffer tokenises an anonymous class as
        // T_ANON_CLASS and interfaces, traits, and enums as tokens of their
        // own, so the four PHPMD never reports on are excluded by not being
        // named here.
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $path = $phpcsFile->getFilename();

        if ($path === self::UNKNOWN_PATH) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null) {
            return;
        }

        $this->scanRun($phpcsFile, $path);
        $this->refreshFile($phpcsFile, $path);

        $line = $phpcsFile->getTokens()[$stackPtr]['line'];
        $candidates = $this->declarations[$this->realPath($path)][$line][strtolower($name)] ?? [];
        $ordinal = $this->declarationOrdinal($phpcsFile, $stackPtr);
        $this->reportOrdinalIndex($phpcsFile, $stackPtr);
        $fullyQualified = $candidates[$ordinal] ?? null;

        if ($fullyQualified === null) {
            return;
        }

        $minimum = (int) $this->minimum;
        $children = $this->childCounts[$fullyQualified] ?? 0;

        if ($children < $minimum) {
            return;
        }

        $phpcsFile->addError(
            'The class %s has %s children.'
                . ' Consider to rebalance this class hierarchy to keep number of children under'
                . ' %s.',
            $stackPtr,
            'Found',
            [$name, $children, $minimum]
        );
    }

    private function reportOrdinalIndex(File $phpcsFile, int $stackPtr): void
    {
        if (Config::getConfigData(self::ORDINAL_DIAGNOSTIC) === null) {
            return;
        }

        if ($stackPtr !== array_key_last($this->ordinals)) {
            return;
        }

        $phpcsFile->addWarning(
            'Ordinal index: %s builds, %s reads over this file.',
            $stackPtr,
            'OrdinalIndex',
            [$this->ordinalCounts['builds'], $this->ordinalCounts['reads']]
        );

        $this->ordinalCounts = ['builds' => 0, 'reads' => 0];
    }

    private function declarationOrdinal(File $phpcsFile, int $stackPtr): int
    {
        $this->buildOrdinals($phpcsFile);

        return $this->ordinals[$stackPtr] ?? 0;
    }

    private function buildOrdinals(File $phpcsFile): void
    {
        $tokens = $phpcsFile->getTokens();
        $key = $phpcsFile->getFilename()
            . '|' . count($tokens)
            . '|' . ($phpcsFile->fixer
                // phpcs:ignore CleanCode.Models.DisallowChainedPropertyFetch -- $phpcsFile->fixer is a PHPCS object, not an Eloquent model
                ->loops ?? 0);

        $this->ordinalCounts['reads']++;

        if ($this->ordinalsKey === $key) {
            return;
        }

        $this->ordinalCounts['builds']++;
        $this->ordinalsKey = $key;
        $this->ordinals = [];
        $counts = [];
        $pointer = $phpcsFile->findNext(T_CLASS, 0);

        while ($pointer !== false) {
            $name = $phpcsFile->getDeclarationName($pointer);

            if ($name !== null) {
                $slot = $tokens[$pointer]['line'] . '|' . strtolower($name);
                $ordinal = ($counts[$slot] ?? 0);
                $this->ordinals[$pointer] = $ordinal;
                $counts[$slot] = ($ordinal + 1);
            }

            $pointer = $phpcsFile->findNext(T_CLASS, ($pointer + 1));
        }
    }

    private function scanRun(File $phpcsFile, string $path): void
    {
        $roots = $this->runRoots($phpcsFile->config);
        $run = $roots === [] ? $this->realPath($path) : implode("\0", $roots);

        if ($this->scannedRun === $run) {
            return;
        }

        $this->childCounts = [];
        $this->declarations = [];
        $this->edges = [];
        $this->digests = [];
        $this->refreshedStream = null;
        $this->scannedRun = $run;

        foreach ($this->runFiles($phpcsFile, $roots, $path) as $file) {
            $this->scanFile($file);
        }
    }

    private function refreshFile(File $phpcsFile, string $path): void
    {
        $tokens = $phpcsFile->getTokens();
        $key = $path . '|' . count($tokens) . '|' . ($phpcsFile->fixer
            // phpcs:ignore CleanCode.Models.DisallowChainedPropertyFetch -- $phpcsFile->fixer is a PHPCS object, not an Eloquent model
            ->loops ?? 0);

        if ($this->refreshedStream === $key) {
            return;
        }

        $this->refreshedStream = $key;
        $resolved = $this->realPath($path);
        $source = $this->streamSource($tokens);
        $digest = md5($source);

        if (($this->digests[$resolved] ?? null) === $digest) {
            return;
        }

        $this->forget($resolved);
        $this->declarations[$resolved] = [];
        $this->digests[$resolved] = $digest;

        $this->parse($source, $resolved);
    }

    private function streamSource(array $tokens): string
    {
        $source = '';

        foreach ($tokens as $token) {
            $source .= (string) ($token['orig_content'] ?? $token['content']);
        }

        return $source;
    }

    private function forget(string $path): void
    {
        foreach (($this->edges[$path] ?? []) as $parent => $count) {
            $remaining = (($this->childCounts[$parent] ?? 0) - $count);

            if ($remaining > 0) {
                $this->childCounts[$parent] = $remaining;

                continue;
            }

            unset($this->childCounts[$parent]);
        }

        unset($this->edges[$path], $this->declarations[$path], $this->digests[$path]);
    }

    private function runRoots(?Config $config): array
    {
        if ($config === null) {
            return [];
        }

        $files = $config->files;

        return is_array($files) === true ? array_values($files) : [];
    }

    private function runFiles(File $phpcsFile, array $roots, string $path): array
    {
        $files = [$path];

        if ($roots === []) {
            return $files;
        }

        return array_merge(
            $files,
            $this->listedPaths(new FileList($phpcsFile->config, $phpcsFile->ruleset))
        );
    }

    private function listedPaths(FileList $listed): array
    {
        $paths = [];

        for ($listed->rewind(); $listed->valid() === true; $listed->next()) {
            $paths[] = (string) $listed->key();
        }

        return $paths;
    }

    private function scanFile(string $path): void
    {
        $resolved = $this->realPath($path);

        if (isset($this->declarations[$resolved]) === true) {
            return;
        }

        $source = $this->readQuietly($resolved);

        if ($source === false) {
            return;
        }

        $this->declarations[$resolved] = [];
        $this->digests[$resolved] = md5($source);

        $this->parse($source, $resolved);
    }

    private function parse(string $source, string $path): void
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $aliases = [];
        $bodies = [];
        $awaiting = [];
        $depth = 0;
        $parentheses = 0;
        $total = count($tokens);

        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];

            if (is_array($token) === false) {
                $depth = $this->trackBrace($token, $depth, $bodies, $awaiting, $parentheses);

                continue;
            }

            // The opening brace of a string interpolation, which is the one
            // opening brace the tokenizer does not hand over as a bare `{`. Its
            // closer is bare, so it is counted here to keep the pair balanced.
            if (isset(self::INTERPOLATION_OPEN_TOKENS[$token[0]]) === true) {
                $depth++;

                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $index);
                $aliases = [];

                continue;
            }

            if (
                $token[0] === T_USE
                && $bodies === []
            ) {
                $aliases += $this->readImports($tokens, $index);

                continue;
            }

            if (isset(self::CLASS_LIKE_TOKENS[$token[0]]) === false) {
                continue;
            }

            // All four keywords are spelled in places that declare nothing —
            // `Type::class`, a method or constant named `trait`, a named
            // argument written `class:`. Recording a body for one of those
            // would swallow every import that followed, because a `use` inside
            // a class-like body is a trait's and not an import.
            if ($this->declaresBody($tokens, $index) === false) {
                continue;
            }

            // The body is recorded when the brace that opens it is reached, not
            // here: everything between this keyword and that brace — an
            // anonymous class's constructor arguments above all — can hold
            // braces of its own. Recording the body now would let one of those
            // close it before it opened. See trackBrace().
            $awaiting[] = $parentheses;

            // Interfaces, traits, enums, and anonymous classes have bodies that
            // have to be tracked, but none of the four declares a name this
            // rule counts children for.
            if (
                $token[0] !== T_CLASS
                || $this->isAnonymous($tokens, $index) === true
            ) {
                continue;
            }

            $this->readClass($tokens, $index, $namespace, $aliases, $path);
        }
    }

    private function trackBrace(
        string $brace,
        int $depth,
        array &$bodies,
        array &$awaiting,
        int &$parentheses
    ): int {
        if ($brace === '(') {
            $parentheses++;

            return $depth;
        }

        if ($brace === ')') {
            $parentheses--;

            return $depth;
        }

        if ($brace === '{') {
            if (
                $awaiting !== []
                && end($awaiting) === $parentheses
            ) {
                array_pop($awaiting);
                $bodies[] = $depth;
            }

            return ($depth + 1);
        }

        if ($brace !== '}') {
            return $depth;
        }

        $depth--;

        while (
            $bodies !== []
            && end($bodies) >= $depth
        ) {
            array_pop($bodies);
        }

        return $depth;
    }

    private function declaresBody(array $tokens, int $index): bool
    {
        if ($this->isAnonymous($tokens, $index) === true) {
            return true;
        }

        $name = $this->significantAfter($tokens, $index);

        return is_array($name) === true && $name[0] === T_STRING;
    }

    private function isAnonymous(array $tokens, int $index): bool
    {
        for ($cursor = ($index - 1); $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if ($token === ']') {
                $opener = $this->attributeStart($tokens, $cursor);

                if ($opener === null) {
                    return false;
                }

                $cursor = $opener;

                continue;
            }

            if (is_array($token) === false) {
                return false;
            }

            if (
                isset(self::SKIPPED_TOKENS[$token[0]]) === true
                || $token[0] === T_READONLY
            ) {
                continue;
            }

            return $token[0] === T_NEW;
        }

        return false;
    }

    private function attributeStart(array $tokens, int $index): ?int
    {
        for ($cursor = $index; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (
                is_array($token) === true
                && $token[0] === T_ATTRIBUTE
            ) {
                return $cursor;
            }
        }

        return null;
    }

    private function readClass(array $tokens, int $index, string $namespace, array $aliases, string $path): void
    {
        $cursor = $index;
        $name = $this->readName($tokens, $cursor);

        if ($name === '') {
            return;
        }

        $qualified = $namespace === '' ? $name : "{$namespace}\\{$name}";
        $this->declarations[$path][$tokens[$index][2]][strtolower($name)][] = strtolower($qualified);

        $next = $this->significantAfter($tokens, $cursor);

        if (
            $next === null
            || is_array($next) === false
            || $next[0] !== T_EXTENDS
        ) {
            return;
        }

        // Onto the `extends` itself, so the name read next is the parent's and
        // not the keyword the cursor is still sitting before.
        $this->significantIndexAfter($tokens, $cursor);

        $parent = $this->readName($tokens, $cursor);

        if ($parent === '') {
            return;
        }

        $resolved = $this->resolve($parent, $namespace, $aliases);
        $this->childCounts[$resolved] = (($this->childCounts[$resolved] ?? 0) + 1);
        $this->edges[$path][$resolved] = (($this->edges[$path][$resolved] ?? 0) + 1);
    }

    private function readNamespace(array $tokens, int $index): string
    {
        $cursor = $index;

        return trim($this->readName($tokens, $cursor), '\\');
    }

    private function readImports(array $tokens, int &$index): array
    {
        $next = $this->significantAfter($tokens, $index);

        if ($next === null) {
            return [];
        }

        // A closure's `use (…)` binds variables and imports nothing. Its
        // statement is not skipped either, because the body that follows it can
        // declare a class of its own.
        if (is_array($next) === false) {
            return [];
        }

        if (in_array($next[0], [T_FUNCTION, T_CONST], true) === true) {
            return [];
        }

        $imports = $this->readImportList($tokens, $index);
        $this->skipStatement($tokens, $index);

        return $imports;
    }

    private function readImportList(array $tokens, int &$index): array
    {
        $imports = [];
        $prefix = '';
        $total = count($tokens);

        while ($index < $total) {
            $name = $this->readName($tokens, $index);

            if ($name === '') {
                return $imports;
            }

            if (
                $prefix === ''
                && $this->significantAfter($tokens, $index) === '{'
            ) {
                $this->significantIndexAfter($tokens, $index);
                $prefix = $name;

                continue;
            }

            $imports += $this->readImport($tokens, $index, $prefix . $name);

            if ($this->significantAfter($tokens, $index) !== ',') {
                return $imports;
            }

            $this->significantIndexAfter($tokens, $index);
        }

        return $imports;
    }

    // PHPCS lists a file it can see; between that listing and this read the file
    // can vanish or lose its permissions, and either raises a warning that says
    // nothing about the source under analysis. Suppressed with a handler rather
    // than `@`, which Generic.PHP.NoSilencedErrors forbids because it hides every
    // diagnostic in the expression instead of the one being answered for. The
    // false return is still checked by the caller.
    private function readQuietly(string $path): string|false
    {
        set_error_handler(static fn (): bool => true);

        try {
            return file_get_contents($path);
        } finally {
            restore_error_handler();
        }
    }

    private function skipStatement(array $tokens, int &$index): void
    {
        $total = count($tokens);

        for ($cursor = $index; $cursor < $total; $cursor++) {
            if ($tokens[$cursor] === ';') {
                $index = $cursor;

                return;
            }
        }
    }

    private function readImport(array $tokens, int &$index, string $name): array
    {
        $target = trim($name, '\\');
        $next = $this->significantAfter($tokens, $index);
        $alias = $target;

        if (
            is_array($next) === true
            && $next[0] === T_AS
        ) {
            $this->significantIndexAfter($tokens, $index);
            $alias = $this->readName($tokens, $index);
        }

        if ($alias === '') {
            return [];
        }

        $segments = explode('\\', $alias);

        return [strtolower((string) end($segments)) => strtolower($target)];
    }

    private function resolve(string $name, string $namespace, array $aliases): string
    {
        if (str_starts_with($name, '\\') === true) {
            return strtolower(ltrim($name, '\\'));
        }

        $segments = explode('\\', $name);
        $first = strtolower((string) reset($segments));

        if ($first === 'namespace') {
            array_shift($segments);

            return strtolower(trim($namespace . '\\' . implode('\\', $segments), '\\'));
        }

        if (isset($aliases[$first]) === true) {
            array_shift($segments);

            return strtolower(trim($aliases[$first] . '\\' . implode('\\', $segments), '\\'));
        }

        return strtolower(trim("{$namespace}\\{$name}", '\\'));
    }

    private function readName(array $tokens, int &$index): string
    {
        $name = '';
        $cursor = $index;

        while (true) {
            $next = $this->significantIndexAfter($tokens, $cursor, false);

            if ($next === null) {
                break;
            }

            $token = $tokens[$next];

            if (
                is_array($token) === false
                || isset(self::NAME_TOKENS[$token[0]]) === false
            ) {
                break;
            }

            $name .= $token[1];
            $cursor = $next;
        }

        $index = $cursor;

        return $name;
    }

    // array|string, not array: these are token_get_all() tokens, and a
    // single-character token such as `{` arrives as a bare string.
    private function significantAfter(array $tokens, int $index): array|string|null
    {
        $next = $this->significantIndexAfter($tokens, $index, false);

        return $next === null ? null : $tokens[$next];
    }

    // phpcs:ignore CleanCode.Functions.DisallowBooleanArgumentFlag -- $advance picks peek or consume over one scan
    private function significantIndexAfter(array $tokens, int &$index, bool $advance = true): ?int
    {
        $total = count($tokens);

        for ($cursor = ($index + 1); $cursor < $total; $cursor++) {
            $token = $tokens[$cursor];

            if (
                is_array($token) === true
                && isset(self::SKIPPED_TOKENS[$token[0]]) === true
            ) {
                continue;
            }

            if ($advance === true) {
                $index = $cursor;
            }

            return $cursor;
        }

        return null;
    }

    private function realPath(string $path): string
    {
        $resolved = realpath($path);

        return $resolved === false ? $path : $resolved;
    }
}
