<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's Design/DepthOfInheritance rule (issue #112).
 *
 * A class reached through a long chain of parents is hard to read and hard to
 * change: understanding it means reading every ancestor first. The sniff counts
 * the parents above a class and reports the declaration once that count reaches
 * $minimum — PHPMD's `minimum` property, same default of 6.
 *
 * Every behaviour below was read off PHPMD 2.15.0 + PDepend 2.16.2 and then
 * confirmed against live runs; the numbers are quoted in
 * docs/phpmd/design-depthofinheritance.md.
 *
 * ## The threshold is inclusive, and `minimum` is a floor, not a ceiling
 *
 * PHPMD\Rule\Design\DepthOfInheritance::apply() reads `maximum` first and falls
 * back to `minimum`, and the two use different comparisons:
 *
 * ```php
 * if (($comparison === 1 && $dit > $threshold) ||
 *     ($comparison === 2 && $dit >= $threshold)
 * ) {
 * ```
 *
 * `$comparison` is 2 on the `minimum` path, which is the one the shipped
 * ruleset takes. So a class with *exactly* 6 parents is already a violation,
 * and phpmd.org's "maximum acceptable parent classes" — echoed by #112's
 * acceptance criteria — describes the opposite of what the tool does. A live
 * PHPMD 2.15.0 run over tests/fixtures/DepthOfInheritanceSniff/boundaries.php
 * agrees with the code and not with the prose, and the tool is what this
 * package replaces.
 *
 * ## An unseen parent counts twice
 *
 * The metric is PDepend's `dit`, and PDepend does not simply count links:
 *
 * ```php
 * foreach ($class->getParentClasses() as $parent) {
 *     if (!$parent->isUserDefined()) {
 *         ++$dit;
 *     }
 *     ++$dit;
 * }
 * ```
 *
 * A parent PDepend never saw *declared* is not user-defined — it is a stub
 * synthesised from the name in the `extends` clause — so it adds 2 rather
 * than 1, and the walk stops there because a stub has no parent of its own.
 * That is why `class Kid extends Vendor\Base {}` measures 2 and not 1, and why
 * four in-project ancestors above an unseen base already reach the threshold
 * of 6. This sniff reproduces the doubling; unseenParentWeight() is the one
 * line that carries it.
 *
 * ## What "seen" means here — the cross-file model
 *
 * PDepend resolves parents across every file in the analysed set, which no
 * per-file PHPCS sniff can do on its own. So this sniff indexes the same set
 * PHPCS itself is about to process — PHP_CodeSniffer\Files\FileList over
 * $config->files, the identical expansion, filters and ignore patterns
 * included — and resolves the chain against that index.
 *
 * The consequences, all of them deliberate:
 *
 * - **It matches PHPMD's measured semantics.** PHPMD counts the parents inside
 *   the analysed fileset and no others: run `phpcs src/` and a parent in
 *   `vendor/` is unseen, exactly as `phpmd src/` sees it.
 * - **It is order-independent.** The index is built from the file list, not
 *   accumulated as files are processed, so a child analysed before its parent
 *   measures the same as the reverse. Under `--parallel` each fork builds the
 *   same index from the same list, so the worker count cannot change a result.
 * - **The file under analysis is always resolvable against itself**, whether or
 *   not the file list holds it: its own declarations are consulted before the
 *   fileset index. A single file passed on stdin therefore behaves like `phpmd`
 *   given that one file: same-file ancestors resolve, everything above them is
 *   unseen.
 * - **The index is built at most once per run, and only when it is needed.** A
 *   class with no `extends` clause has depth 0 and returns before the index is
 *   ever touched, so a project without inheritance pays nothing.
 * - **Its limitation is the fileset boundary.** An ancestor excluded from the
 *   run — vendor code, an `<exclude-pattern>`, a narrower `phpcs` argument —
 *   is unseen and terminates the walk at +2. That is a property of PHPMD's own
 *   model, reproduced rather than corrected.
 *
 * The Composer autoloader is deliberately *not* consulted. It would resolve
 * vendor parents that PHPMD stays silent about, reporting depths PHPMD never
 * reports on ordinary framework code, and it is unavailable when PHPCS runs
 * from a global or PHAR install.
 *
 * ## Classes only
 *
 * PHPMD's rule is ClassAware, so an interface, a trait and an enum are never
 * reported however deep they sit, and an anonymous class is not reported in
 * its own right. Registering T_CLASS gives all four for free: PHP_CodeSniffer
 * tokenises them as T_INTERFACE, T_TRAIT, T_ENUM and T_ANON_CLASS. An
 * `implements` clause and a `use` of a trait contribute nothing to the count —
 * only `extends` does. All measured, all fixtured.
 */
class DepthOfInheritanceSniff implements Sniff
{
    /**
     * The number of parents at (or above) which a class is reported. PHPMD's
     * own default for the `minimum` property, spelled with PHPMD's name.
     */
    public int $minimum = 6;

    /**
     * Class modifiers that may precede the `class` keyword. PDepend takes the
     * class's start line from the first of these, not from `class` itself, so
     * the report follows it up.
     */
    private const DECLARATION_MODIFIERS = [
        T_ABSTRACT,
        T_FINAL,
        T_READONLY,
    ];

    /**
     * The two braces PHP's lexer hands over as a token rather than as a bare
     * `{`, both of them openers whose matching `}` arrives bare.
     *
     * `"{$expr}"` opens on T_CURLY_OPEN and `"${expr}"` on
     * T_DOLLAR_OPEN_CURLY_BRACES — in a double-quoted string and in a heredoc
     * alike. Counting only the bare braces would therefore drop a level on
     * every interpolation and close the enclosing namespace early. These two
     * are the whole of the asymmetry: every other brace-bearing construct —
     * `$o->{$n}`, `${$n}`, `match`, an enum, a property hook, an attribute, a
     * closure — is bare on both sides, and a literal brace *inside* a string
     * never reaches this counter at all, because the lexer keeps it in the
     * surrounding T_ENCAPSED_AND_WHITESPACE or T_CONSTANT_ENCAPSED_STRING.
     * Verified by dumping every brace-carrying token across all of them.
     */
    private const INTERPOLATION_OPENERS = [
        T_CURLY_OPEN,
        T_DOLLAR_OPEN_CURLY_BRACES,
    ];

    /**
     * The index of the analysed set, against the run it was built for: one
     * entry, replaced whenever a different Config arrives.
     *
     * The run is held by WeakReference and compared by identity rather than by
     * spl_object_id(), because an id is reused once its object is collected —
     * a plain id would eventually hand one run's index to another.
     *
     * @var array{run: \WeakReference<Config>, index: array<string, string|null>}|null
     */
    private static ?array $filesetIndex = null;

    /**
     * The declarations of the file being processed, as a single-entry cache.
     * PHPCS hands a file's T_CLASS tokens to a sniff consecutively, so one
     * entry serves every class in it and the cache cannot grow with the run.
     *
     * Held by WeakReference for the same reason as above, and with the same
     * benefit: a file already processed is free to be collected, so this never
     * keeps a previous file's tokens alive.
     *
     * @var array{file: \WeakReference<File>, declarations: array<int,
     *     array{name: string, line: int, fqcn: string, parent: string|null}>,
     *     index: array<string, string|null>}|null
     */
    private static ?array $currentFile = null;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
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

        if ($depth === null || $depth < $this->minimum) {
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

    /**
     * The number of parents above $declaration, counted PDepend's way, or null
     * when the chain cannot be measured.
     *
     * A resolved ancestor adds 1 and the walk continues above it. An ancestor
     * named but not declared anywhere in the analysed set adds 2 and ends the
     * walk, because PDepend's stub for it has no parent of its own.
     *
     * A cycle — `class A extends B` with `class B extends A`, which PHP itself
     * refuses to load — has no depth to report. PHPMD is silent on it, and so
     * is this: null abandons the measurement rather than counting round the
     * loop.
     *
     * The file being processed is consulted before the fileset index, which is
     * what makes it visible to itself: a file supplied on stdin, or one the run
     * narrowed past, still resolves its own ancestors. Two maps rather than one
     * merged map is not a detail — merging them would copy the whole index once
     * per class, which is a project's class count squared over a whole run.
     *
     * @param array{fqcn: string, parent: string|null} $declaration
     * @param array<string, string|null>               $file
     * @param array<string, string|null>               $fileset
     */
    private function depthOf(array $declaration, array $file, array $fileset): ?int
    {
        $depth = 0;
        $parent = $declaration['parent'];
        $seen = [$declaration['fqcn'] => true];

        while ($parent !== null) {
            if (array_key_exists($parent, $file) === true) {
                $next = $file[$parent];
            } elseif (array_key_exists($parent, $fileset) === true) {
                $next = $fileset[$parent];
            } else {
                return $depth + $this->unseenParentWeight();
            }

            if (isset($seen[$parent]) === true) {
                return null;
            }

            $seen[$parent] = true;
            ++$depth;
            $parent = $next;
        }

        return $depth;
    }

    /**
     * What a parent PDepend never saw declared contributes: 2, from the
     * unguarded second `++$dit` in PDepend's
     * InheritanceAnalyzer::calculateDepthOfInheritanceTree().
     */
    private function unseenParentWeight(): int
    {
        return 2;
    }

    /**
     * The class declaration at $stackPtr as this sniff's own reader sees it,
     * or null when the two readings disagree.
     *
     * The name and the line come from PHP_CodeSniffer, the namespace and the
     * resolved parent from declarationsIn(). Pairing them on *both* the short
     * name and the line is what keeps `class A {} class B extends A {}` on one
     * physical line from resolving to the wrong entry.
     *
     * @return array{name: string, line: int, fqcn: string, parent: string|null}|null
     */
    private function declarationOf(File $phpcsFile, int $stackPtr): ?array
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || $name === '') {
            return null;
        }

        $tokens = $phpcsFile->getTokens();
        $line = $tokens[$stackPtr]['line'];
        $name = strtolower($name);

        foreach ($this->currentFile($phpcsFile)['declarations'] as $declaration) {
            if ($declaration['line'] === $line && $declaration['name'] === $name) {
                return $declaration;
            }
        }

        return null;
    }

    /**
     * The file being processed, read from the source PHP_CodeSniffer tokenised
     * rather than from disk, so a file supplied on stdin reads the same as one
     * with a path.
     *
     * Both readings are built together and cached together: `declarations` in
     * source order, for pairing a T_CLASS token with its entry, and `index`
     * keyed by name, for resolving a parent. Building the second here rather
     * than per class is what keeps the cost of a file proportional to the
     * classes in it.
     *
     * @return array{file: \WeakReference<File>, declarations: array<int,
     *     array{name: string, line: int, fqcn: string, parent: string|null}>,
     *     index: array<string, string|null>}
     */
    private function currentFile(File $phpcsFile): array
    {
        if (self::$currentFile !== null && self::$currentFile['file']->get() === $phpcsFile) {
            return self::$currentFile;
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

        self::$currentFile = [
            'file' => \WeakReference::create($phpcsFile),
            'declarations' => $declarations,
            'index' => $index,
        ];

        return self::$currentFile;
    }

    /**
     * Every class declared in the files PHPCS is processing this run, built
     * once per Config and memoised.
     *
     * Paths are sorted before they are read, so that two runs over the same
     * tree resolve a duplicated class name — the same FQCN declared in two
     * files — to the same declaration, whatever order the filesystem hands
     * them back in. The first declaration of a name wins.
     *
     * @return array<string, string|null>
     */
    private function filesetIndex(File $phpcsFile): array
    {
        $config = $phpcsFile->config;

        if (self::$filesetIndex !== null && self::$filesetIndex['run']->get() === $config) {
            return self::$filesetIndex['index'];
        }

        $index = [];

        foreach ($this->filesetPaths($config, $phpcsFile) as $path) {
            $source = @file_get_contents($path);

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

        self::$filesetIndex = [
            'run' => \WeakReference::create($config),
            'index' => $index,
        ];

        return $index;
    }

    /**
     * The paths PHPCS is about to process, in sorted order.
     *
     * FileList is PHPCS's own expansion of $config->files, so this is the
     * analysed set exactly: the same recursion, the same `--extensions`, the
     * same ignore patterns. Iterating it by key never calls FileList::current(),
     * which is what would construct a LocalFile for every path.
     *
     * @return array<int, string>
     */
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
            if ($path === null || $path === 'STDIN') {
                continue;
            }

            $paths[] = $path;
        }

        sort($paths);

        return $paths;
    }

    /**
     * Every class declared in one PHP source, with its parent resolved to a
     * fully qualified, lower-cased name.
     *
     * This is the sniff's single reader of inheritance: the file under
     * analysis and every other file in the set go through it, so a namespace,
     * an import or an alias cannot be read one way here and another way there.
     * It uses PHP's own lexer rather than PHP_CodeSniffer's, because indexing a
     * whole project needs the names and nothing else — no scope map, no line
     * map, none of what makes a full tokenisation worth its cost.
     *
     * Only `class` declarations are indexed. An interface, a trait and an enum
     * cannot be a parent in valid PHP, and PHPMD reports none of them.
     *
     * @return array<int, array{name: string, line: int, fqcn: string, parent: string|null}>
     */
    private function declarationsIn(string $source): array
    {
        $tokens = @token_get_all($source);
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

            if ($token[0] !== T_CLASS || $this->isClassDeclaration($tokens, $i) === false) {
                continue;
            }

            [$declaration, $i] = $this->readClass($tokens, $i, $count, $namespace, $imports);

            if ($declaration !== null) {
                $declarations[] = $declaration;
            }
        }

        return $declarations;
    }

    /**
     * The namespace a `namespace` keyword opens, the brace depth its
     * declarations sit at, and the index to continue reading from.
     *
     * A braced `namespace Foo { … }` puts its imports one level in, which is
     * what $namespaceDepth carries: without it, an import inside a braced
     * namespace would be mistaken for a trait `use` in a class body.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: string, 1: int, 2: int}
     */
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

            if (is_array($token) === true && $this->isNameToken($token[0]) === true) {
                $name = $token[1];
            }
        }

        return [$name, 0, $count];
    }

    /**
     * Whether a `use` keyword opens a closure's captured-variable list.
     *
     * `function () use ($x) {}` is written at any depth, including the zero a
     * top-level assignment sits at, so depth alone cannot tell it from an
     * import. The parenthesis can.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
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

    /**
     * The import map after reading one `use` statement, and the index of its
     * terminating semicolon.
     *
     * Handles the plain form, the comma-separated list, the `as` alias and the
     * `Foo\{Bar, Baz}` group. A `use function` / `use const` statement — and a
     * `function` / `const` entry inside a group — binds no class name, so it
     * contributes nothing.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, string>                               $imports
     *
     * @return array{0: array<string, string>, 1: int}
     */
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

            if ($token[0] === T_FUNCTION || $token[0] === T_CONST) {
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

    /**
     * Records one import, unless it names a function or a constant.
     *
     * @param array<string, string> $imports
     */
    private function addImport(array &$imports, string $prefix, string $name, ?string $alias, bool $skip): void
    {
        if ($skip === true || $name === '') {
            return;
        }

        $qualified = trim($prefix . '\\' . $name, '\\');
        $segments = explode('\\', $qualified);
        $key = $alias ?? end($segments);

        $imports[strtolower($key)] = strtolower($qualified);
    }

    /**
     * Whether a `class` keyword opens a declaration.
     *
     * PHP's lexer emits T_CLASS for three different things, and only one of
     * them declares a named class. `new class` is an anonymous class, which
     * PHPMD does not report in its own right, and `Foo::class` is a constant
     * expression that names no declaration at all. Both are excluded by the
     * token in front of the keyword.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
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

    /**
     * One class declaration — its short name, the line its `class` keyword
     * sits on, its fully qualified name and its resolved parent — and the
     * index the caller resumes from.
     *
     * Reading stops at the body's opening brace, so an `implements` clause is
     * passed over and a parent is taken only from `extends`. An interface
     * contributes nothing to PDepend's `dit`, which is measured, not assumed.
     *
     * The index handed back is the `class` keyword's own, not the last token
     * this reader looked at: the caller has to walk the header and the body
     * itself, or the braces in them never reach its depth counter and every
     * later `use` in the file is read at the wrong level.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, string>                               $imports
     *
     * @return array{0: array{name: string, line: int, fqcn: string, parent: string|null}|null, 1: int}
     */
    private function readClass(array $tokens, int $i, int $count, string $namespace, array $imports): array
    {
        $line = $tokens[$i][2];
        $name = null;
        $parent = null;
        $inExtends = false;

        for ($j = ($i + 1); $j < $count; $j++) {
            $token = $tokens[$j];

            if ($token === '{' || $token === ';') {
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

            if ($inExtends === true && $parent === null) {
                $parent = $this->resolve($token[1], $namespace, $imports);
            }
        }

        if ($name === null) {
            return [null, $i];
        }

        $fqcn = $namespace === '' ? $name : $namespace . '\\' . $name;

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

    /**
     * A name as written in an `extends` clause, resolved to a fully qualified,
     * lower-cased name the index can be keyed by.
     *
     * The four spellings PHP allows, in the order they are decided: fully
     * qualified (`\App\Base`), relative to the current namespace
     * (`namespace\Base`), qualified or unqualified through an import — where
     * only the *first* segment is what an import binds — and otherwise
     * relative to the current namespace.
     *
     * @param array<string, string> $imports
     */
    private function resolve(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\') === true) {
            return strtolower(ltrim($name, '\\'));
        }

        if (strtolower($name) === 'namespace' || str_starts_with(strtolower($name), 'namespace\\') === true) {
            $relative = substr($name, strlen('namespace'));

            return strtolower(trim($namespace . $relative, '\\'));
        }

        $segments = explode('\\', $name);
        $head = strtolower($segments[0]);

        if (isset($imports[$head]) === true) {
            $segments[0] = $imports[$head];

            return strtolower(implode('\\', $segments));
        }

        return strtolower($namespace === '' ? $name : $namespace . '\\' . $name);
    }

    /**
     * Whether a token is whitespace or a comment — the run that may sit
     * between two tokens whose adjacency is what a reader is deciding on.
     *
     * @param array{0: int, 1: string, 2: int}|string $token
     */
    private function isSkippableToken($token): bool
    {
        return is_array($token) === true
            && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true) === true;
    }

    /**
     * Whether a token carries a name. PHP 8 lexes a qualified name as one
     * token rather than as a run of strings and separators, and this package
     * requires PHP 8.1, so all four forms arrive whole.
     */
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

    /**
     * The token the class declaration starts at: the outermost modifier
     * preceding `class`, or `class` itself when it carries none.
     *
     * PDepend's ASTClass::getStartLine() is the line of that first modifier, so
     * `abstract` sitting on its own line above `class Foo` moves the report up
     * a line. An attribute group above it does not: it is not a modifier, and a
     * live PHPMD run reports the modifier's line, not the attribute's.
     */
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
