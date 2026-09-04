<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use WeakReference;

class DepthOfInheritanceSniff implements Sniff
{
    public int $minimum = 6;

    private const DECLARATION_MODIFIERS = [
        T_ABSTRACT,
        T_FINAL,
        T_READONLY,
    ];

    private const INTERPOLATION_OPENERS = [
        T_CURLY_OPEN,
        T_DOLLAR_OPEN_CURLY_BRACES,
    ];

    private ?array $filesetIndexCache = null;

    private ?array $currentFileCache = null;

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $declaration = $this->declarationOf($phpcsFile, $stackPtr);

        // A class the tokenizer could not give a name, or one this sniff's own
        // reader did not find at the same line — malformed source either way.
        // There is no honest chain to walk, so say nothing.
        if ($declaration === null) {
            return;
        }

        // No `extends` clause is depth 0, and depth 0 can never reach a
        // threshold of 1 or more. Returning here is what keeps a project
        // without inheritance from ever building the fileset index.
        if ($declaration['parent'] === null) {
            return;
        }

        $depth = $this->depthOf(
            $declaration,
            $this->currentFile($phpcsFile)['index'],
            $this->filesetIndex($phpcsFile)
        );

        if (
            $depth === null
            || $depth < $this->minimum
        ) {
            return;
        }

        $phpcsFile->addError(
            'The class %s has %s parents. Current threshold is %s. Reduce the depth of this class hierarchy.',
            $this->declarationStart($phpcsFile, $stackPtr),
            'TooDeep',
            [
                $phpcsFile->getDeclarationName($stackPtr),
                $depth,
                $this->minimum,
            ]
        );
    }

    private function depthOf(array $declaration, array $file, array $fileset): ?int
    {
        $depth = 0;
        $parent = $declaration['parent'];
        $seen = [$declaration['fqcn'] => true];

        while ($parent !== null) {
            $inFile = array_key_exists($parent, $file);

            if (
                $inFile === false
                && array_key_exists($parent, $fileset) === false
            ) {
                return $depth + $this->unseenParentWeight();
            }

            // array_key_exists, not ??: an index entry holds a declaration's
            // parent, and a class with no parent stores null. Coalescing would
            // read that as absent and charge it the unseen-parent weight.
            $next = $inFile === true ? $file[$parent] : $fileset[$parent];

            if (isset($seen[$parent]) === true) {
                return null;
            }

            $seen[$parent] = true;
            ++$depth;
            $parent = $next;
        }

        return $depth;
    }

    private function unseenParentWeight(): int
    {
        return 2;
    }

    private function declarationOf(File $phpcsFile, int $stackPtr): ?array
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || $name === ''
        ) {
            return null;
        }

        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$stackPtr]['line'];
        $name = strtolower($name);

        foreach ($this->currentFile($phpcsFile)['declarations'] as $declaration) {
            if (
                $declaration['line'] === $line
                && $declaration['name'] === $name
            ) {
                return $declaration;
            }
        }

        return null;
    }

    private function currentFile(File $phpcsFile): array
    {
        if (
            $this->currentFileCache !== null
            && $this->currentFileCache['file']
                ->get() === $phpcsFile
        ) {
            return $this->currentFileCache;
        }

        $source = '';

        foreach ($phpcsFile->getTokens() as $token) {
            $source .= $token['content'];
        }

        $declarations = $this->declarationsIn($source);
        $index = [];

        foreach ($declarations as $declaration) {
            $index[$declaration['fqcn']] = $declaration['parent'];
        }

        $this->currentFileCache = [
            'file' => WeakReference::create($phpcsFile),
            'declarations' => $declarations,
            'index' => $index,
        ];

        return $this->currentFileCache;
    }

    private function filesetIndex(File $phpcsFile): array
    {
        $config = $phpcsFile->config;

        if (
            $this->filesetIndexCache !== null
            && $this->filesetIndexCache['run']
                ->get() === $config
        ) {
            return $this->filesetIndexCache['index'];
        }

        $index = [];

        foreach ($this->filesetPaths($config, $phpcsFile) as $path) {
            $source = $this->readQuietly($path);

            // A file PHPCS listed but this sniff cannot read is left out of the
            // index, which makes anything extending it *unseen* rather than
            // silently depth-0. The conservative direction: a shorter chain is
            // never reported, an over-long one is not invented.
            if ($source === false) {
                continue;
            }

            foreach ($this->declarationsIn($source) as $declaration) {
                if (array_key_exists($declaration['fqcn'], $index) === true) {
                    continue;
                }

                $index[$declaration['fqcn']] = $declaration['parent'];
            }
        }

        $this->filesetIndexCache = [
            'run' => WeakReference::create($config),
            'index' => $index,
        ];

        return $index;
    }

    private function filesetPaths(Config $config, File $phpcsFile): array
    {
        if ($config->files === []) {
            return [];
        }

        $list = new FileList($config, $phpcsFile->ruleset);
        $paths = [];

        for ($list->rewind(); $list->valid() === true; $list->next()) {
            $path = $list->key();

            // STDIN has no readable path, and nothing is lost by dropping it:
            // the file under analysis is read by currentFile() into a map of
            // its own, which depthOf() consults before this index.
            if (
                $path === null
                || $path === 'STDIN'
            ) {
                continue;
            }

            $paths[] = $path;
        }

        sort($paths);

        return $paths;
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

    // Source PHPCS handed over can still be a file this sniff was pointed at by
    // a glob and that PHP cannot tokenize cleanly. A tokenizer warning about it
    // is noise in the report, and the malformed result is handled below.
    private function declarationsIn(string $source): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $tokens = token_get_all($source);
        } finally {
            restore_error_handler();
        }

        $declarations = [];
        $namespace = '';
        $namespaceDepth = 0;
        $imports = [];
        $depth = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) === false) {
                if ($token === '{') {
                    ++$depth;
                }

                if ($token === '}') {
                    --$depth;
                }

                continue;
            }

            if (in_array($token[0], self::INTERPOLATION_OPENERS, true) === true) {
                ++$depth;

                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$namespace, $namespaceDepth, $i] = $this->readNamespace($tokens, $i, $count);
                $imports = [];

                continue;
            }

            $isImport = $token[0] === T_USE
                && $depth === $namespaceDepth
                && $this->opensClosureUse($tokens, $i, $count) === false;

            if ($isImport === true) {
                [$imports, $i] = $this->readImports($tokens, $i, $count, $imports);

                continue;
            }

            if (
                $token[0] !== T_CLASS
                || $this->isClassDeclaration($tokens, $i) === false
            ) {
                continue;
            }

            [$declaration, $i] = $this->readClass($tokens, $i, $count, $namespace, $imports);

            if ($declaration !== null) {
                $declarations[] = $declaration;
            }
        }

        return $declarations;
    }

    private function readNamespace(array $tokens, int $i, int $count): array
    {
        $name = '';

        for ($j = ($i + 1); $j < $count; $j++) {
            $token = $tokens[$j];

            // One short of the brace, not past it: the caller's own depth
            // counter has to see the `{` this namespace opens, or every
            // declaration inside it reads one level too shallow and its
            // imports are mistaken for trait `use` statements.
            if ($token === '{') {
                return [$name, 1, ($j - 1)];
            }

            if ($token === ';') {
                return [$name, 0, $j];
            }

            if (
                is_array($token) === true
                && $this->isNameToken($token[0]) === true
            ) {
                $name = $token[1];
            }
        }

        return [$name, 0, $count];
    }

    private function opensClosureUse(array $tokens, int $i, int $count): bool
    {
        for ($j = ($i + 1); $j < $count; $j++) {
            if ($this->isSkippableToken($tokens[$j]) === true) {
                continue;
            }

            return $tokens[$j] === '(';
        }

        return false;
    }

    private function readImports(array $tokens, int $i, int $count, array $imports): array
    {
        $prefix = '';
        $name = '';
        $alias = null;
        $isAlias = false;
        $skip = false;

        for ($j = ($i + 1); $j < $count; $j++) {
            $token = $tokens[$j];

            if ($token === ';') {
                $this->addImport($imports, $prefix, $name, $alias, $skip);

                return [$imports, $j];
            }

            if ($token === '{') {
                $prefix = $name;
                $name = '';

                continue;
            }

            if ($token === '}') {
                $this->addImport($imports, $prefix, $name, $alias, $skip);
                $prefix = '';
                $name = '';
                $alias = null;
                $isAlias = false;
                $skip = false;

                continue;
            }

            if ($token === ',') {
                $this->addImport($imports, $prefix, $name, $alias, $skip);
                $name = '';
                $alias = null;
                $isAlias = false;
                $skip = false;

                continue;
            }

            if (is_array($token) === false) {
                continue;
            }

            if (
                $token[0] === T_FUNCTION
                || $token[0] === T_CONST
            ) {
                $skip = true;

                continue;
            }

            if ($token[0] === T_AS) {
                $isAlias = true;

                continue;
            }

            if ($this->isNameToken($token[0]) === false) {
                continue;
            }

            if ($isAlias === true) {
                $alias = $token[1];

                continue;
            }

            $name = $token[1];
        }

        return [$imports, $count];
    }

    // phpcs:ignore CleanCode.Functions.DisallowBooleanArgumentFlag -- one term of a disjunctive guard, not a mode
    private function addImport(array &$imports, string $prefix, string $name, ?string $alias, bool $skip): void
    {
        if (
            $skip === true
            || $name === ''
        ) {
            return;
        }

        $qualified = trim("{$prefix}\\{$name}", '\\');
        $segments = explode('\\', $qualified);
        $key = $alias ?? end($segments);

        $imports[strtolower($key)] = strtolower($qualified);
    }

    private function isClassDeclaration(array $tokens, int $i): bool
    {
        for ($j = ($i - 1); $j >= 0; $j--) {
            $token = $tokens[$j];

            if (is_array($token) === false) {
                return true;
            }

            if ($this->isSkippableToken($token) === true) {
                continue;
            }

            return in_array($token[0], [T_NEW, T_DOUBLE_COLON], true) === false;
        }

        return true;
    }

    private function readClass(array $tokens, int $i, int $count, string $namespace, array $imports): array
    {
        $line = $tokens[$i][2];
        $name = null;
        $parent = null;
        $inExtends = false;

        for ($j = ($i + 1); $j < $count; $j++) {
            $token = $tokens[$j];

            if (
                $token === '{'
                || $token === ';'
            ) {
                break;
            }

            if (is_array($token) === false) {
                continue;
            }

            if ($token[0] === T_EXTENDS) {
                $inExtends = true;

                continue;
            }

            if ($token[0] === T_IMPLEMENTS) {
                break;
            }

            if ($this->isNameToken($token[0]) === false) {
                continue;
            }

            if ($name === null) {
                $name = $token[1];

                continue;
            }

            if (
                $inExtends === true
                && $parent === null
            ) {
                $parent = $this->resolve($token[1], $namespace, $imports);
            }
        }

        if ($name === null) {
            return [null, $i];
        }

        $fqcn = $namespace === '' ? $name : "{$namespace}\\{$name}";

        return [
            [
                'name' => strtolower($name),
                'line' => $line,
                'fqcn' => strtolower($fqcn),
                'parent' => $parent,
            ],
            $i,
        ];
    }

    private function resolve(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\') === true) {
            return strtolower(ltrim($name, '\\'));
        }

        if (
            strtolower($name) === 'namespace'
            || str_starts_with(strtolower($name), 'namespace\\') === true
        ) {
            $relative = substr($name, strlen('namespace'));

            return strtolower(trim($namespace . $relative, '\\'));
        }

        $segments = explode('\\', $name);
        $head = strtolower($segments[0]);

        if (isset($imports[$head]) === true) {
            $segments[0] = $imports[$head];

            return strtolower(implode('\\', $segments));
        }

        return strtolower($namespace === '' ? $name : "{$namespace}\\{$name}");
    }

    private function isSkippableToken(array|string $token): bool
    {
        return is_array($token) === true
            && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true) === true;
    }

    private function isNameToken(int $code): bool
    {
        return in_array(
            $code,
            [
                T_STRING,
                T_NAME_QUALIFIED,
                T_NAME_FULLY_QUALIFIED,
                T_NAME_RELATIVE,
            ],
            true
        );
    }

    private function declarationStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $stackPtr;

        while (true) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

            if (
                $previous === false
                || in_array($tokens[$previous]['code'], self::DECLARATION_MODIFIERS, true) === false
            ) {
                return $start;
            }

            $start = $previous;
        }
    }
}
